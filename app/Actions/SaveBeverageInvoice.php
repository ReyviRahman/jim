<?php

namespace App\Actions;

use App\Models\Beverage;
use App\Models\BeverageInvoice;
use App\Models\BeverageRestock;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

final class SaveBeverageInvoice
{
    public function __construct(private BeverageStockImpact $impact) {}

    /** @param array<string, mixed> $data */
    public function execute(array $data, ?UploadedFile $image = null, ?int $id = null): BeverageInvoice
    {
        abort_unless(auth()->check() && ($id ? auth()->user()->role === 'admin' : in_array(auth()->user()->role, ['admin', 'kasir_gym', 'kasir_minum'], true)), 403);
        Validator::make(['image' => $image], ['image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:10240']])->validate();
        $newPath = null;
        $oldPath = null;
        try {
            if ($image) {
                $newPath = $image->store('beverage-invoices', 'local');
                if (! $newPath) {
                    throw ValidationException::withMessages(['image' => 'Foto invoice gagal disimpan.']);
                }
            }
            $invoice = DB::transaction(function () use ($data, $id, $newPath, &$oldPath): BeverageInvoice {
                $invoice = $id ? BeverageInvoice::query()->lockForUpdate()->findOrFail($id) : null;
                $linked = ! $invoice || $invoice->stock_posted_at !== null;
                $rules = [
                    'no_faktur' => ['required', 'string', 'max:255', Rule::unique('beverage_invoices', 'no_faktur')->ignore($id)],
                    'tanggal_order' => ['required', 'date_format:Y-m-d'],
                    'tanggal_menerima' => $linked ? ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_order', 'before_or_equal:'.now('Asia/Jakarta')->toDateString()] : ['nullable', 'date_format:Y-m-d'],
                    'diterima_oleh' => ['nullable', 'string', 'max:255'],
                    'status' => ['required', 'in:pending,lunas'],
                    'metode_pembayaran' => ['required', 'in:cash,tf_bca,qris,hutang'],
                    'items' => ['required', 'array', 'min:1'],
                    'items.*.qty' => ['required', 'integer', 'min:1'],
                    'items.*.harga_perdus' => ['required', 'integer', 'min:0'],
                    'items.*.biaya_ppn' => ['nullable', 'integer', 'min:0'],
                ];
                if ($linked) {
                    $rules['items.*.beverage_id'] = ['required', 'integer'];
                    $rules['items.*.total_pcs'] = ['required', 'integer', 'min:1'];
                } else {
                    $rules['items.*.nama_barang'] = ['required', 'string', 'max:255'];
                    $rules['items.*.total_pcs'] = ['nullable', 'integer', 'min:1'];
                    $rules['items.*.beverage_id'] = ['nullable', 'integer', Rule::exists(Beverage::class, 'id')];
                }
                $validated = Validator::make($data, $rules)->validate();
                $existing = $invoice?->items()->get()->keyBy('id');
                if ($invoice?->stock_posted_at) {
                    $ids = collect($data['items'])->pluck('id')->map(fn ($value) => (int) $value)->sort()->values()->all();
                    if ($ids !== $existing->keys()->sort()->values()->all() || $data['tanggal_menerima'] !== $invoice->tanggal_menerima->toDateString()) {
                        throw ValidationException::withMessages(['items' => 'Produk, total pcs, item, dan tanggal menerima tidak boleh diubah.']);
                    }
                    foreach ($data['items'] as $item) {
                        $old = $existing->get((int) $item['id']);
                        if ((int) $item['beverage_id'] !== $old->beverage_id || (int) $item['total_pcs'] !== $old->total_pcs || ($item['nama_barang'] ?? '') !== $old->nama_barang) {
                            throw ValidationException::withMessages(['items' => 'Produk dan total pcs sudah dicatat ke stok dan tidak boleh diubah.']);
                        }
                    }
                }
                $products = collect();
                if (! $invoice) {
                    $quantities = collect($validated['items'])->groupBy('beverage_id')->map(fn ($items) => (int) $items->sum('total_pcs'));
                    $products = $this->impact->lockProducts($quantities);
                    foreach ($quantities as $productId => $quantity) {
                        if (! $products->has($productId) || $products[$productId]->trashed()) {
                            throw ValidationException::withMessages(['items' => 'Pilih produk aktif yang tersedia.']);
                        }
                    }
                    $preview = $this->impact->inspect($quantities, $products, $validated['tanggal_menerima'], 1);
                    if ($preview['error_count']) {
                        throw ValidationException::withMessages(['stock' => $preview['errors']]);
                    }
                }
                $header = collect($validated)->except('items')->all();
                $oldPath = $invoice?->image_path;
                if ($newPath) {
                    $header['image_path'] = $newPath;
                }
                $creating = ! $invoice;
                if ($creating) {
                    $invoice = BeverageInvoice::create($header + ['stock_posted_at' => now()]);
                } else {
                    $invoice->update($header);
                }
                $kept = [];
                foreach ($data['items'] as $item) {
                    $attributes = [
                        'qty' => (int) $item['qty'], 'harga_perdus' => (int) $item['harga_perdus'],
                        'biaya_ppn' => (int) ($item['biaya_ppn'] ?? 0),
                        'total' => (int) $item['qty'] * (int) $item['harga_perdus'] + (int) ($item['biaya_ppn'] ?? 0),
                    ];
                    if ($creating) {
                        $attributes += ['beverage_id' => (int) $item['beverage_id'], 'total_pcs' => (int) $item['total_pcs'], 'nama_barang' => $products[(int) $item['beverage_id']]->nama_produk];
                    } elseif (! $linked) {
                        $attributes['nama_barang'] = $item['nama_barang'];
                        $attributes['total_pcs'] = filled($item['total_pcs'] ?? null) ? (int) $item['total_pcs'] : null;
                        $attributes['beverage_id'] = filled($item['beverage_id'] ?? null) ? (int) $item['beverage_id'] : null;
                    }
                    if (! $creating && ! empty($item['id'])) {
                        $row = $invoice->items()->findOrFail($item['id']);
                        $row->update($attributes);
                    } else {
                        $row = $invoice->items()->create($attributes);
                    }
                    $kept[] = $row->id;
                    if ($creating) {
                        BeverageRestock::create([
                            'beverage_invoice_item_id' => $row->id, 'beverage_id' => $row->beverage_id,
                            'tanggal' => $invoice->tanggal_menerima, 'jumlah_tambah' => $row->total_pcs,
                            'tipe' => 'restock', 'keterangan' => 'Invoice '.$invoice->no_faktur,
                        ]);
                    }
                }
                $invoice->items()->whereNotIn('id', $kept)->delete();
                if ($creating) {
                    $this->impact->apply($quantities, $validated['tanggal_menerima'], 1);
                }

                return $invoice;
            }, 3);
        } catch (Throwable $exception) {
            if ($newPath) {
                Storage::disk('local')->delete($newPath);
            }
            throw $exception;
        }
        if ($newPath && $oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        return $invoice;
    }
}
