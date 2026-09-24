<?php

namespace App\Actions;

use App\Models\BeverageInvoice;
use App\Models\BeverageRestock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class CancelBeverageInvoice
{
    public function __construct(private BeverageStockImpact $impact) {}

    /** @return array<string, mixed> */
    public function preview(int $id): array
    {
        abort_unless(in_array(auth()->user()?->role, ['admin', 'kasir_gym'], true), 403);

        return DB::transaction(function () use ($id): array {
            $invoice = BeverageInvoice::query()->lockForUpdate()->findOrFail($id);

            return $this->inspect($invoice);
        });
    }

    /** @return array<string, mixed> */
    private function inspect(BeverageInvoice $invoice): array
    {
        if (! $invoice->stock_posted_at) {
            return ['invoice' => $invoice->no_faktur, 'legacy' => true, 'products' => [], 'errors' => [], 'error_count' => 0, 'fingerprint' => hash('sha256', $invoice->toJson().'|'.$invoice->items()->get()->toJson())];
        }
        $quantities = $invoice->items()->get()->groupBy('beverage_id')->map(fn ($items) => (int) $items->sum('total_pcs'));
        $preview = $this->impact->inspect($quantities, $this->impact->lockProducts($quantities), $invoice->tanggal_menerima->toDateString(), -1);
        $preview['fingerprint'] = hash('sha256', $preview['fingerprint'].'|'.$invoice->toJson().'|'.$invoice->items()->get()->toJson());

        return $preview + ['invoice' => $invoice->no_faktur, 'legacy' => false];
    }

    /** Null means deleted or already missing; an array requires another review.
     * @return array<string, mixed>|null
     */
    public function execute(int $id, string $fingerprint): ?array
    {
        abort_unless(in_array(auth()->user()?->role, ['admin', 'kasir_gym'], true), 403);
        $image = null;
        $result = DB::transaction(function () use ($id, $fingerprint, &$image): ?array {
            $invoice = BeverageInvoice::query()->lockForUpdate()->find($id);
            if (! $invoice) {
                return null;
            }
            $preview = $this->inspect($invoice);
            if ($preview['error_count'] || ! hash_equals($preview['fingerprint'], $fingerprint)) {
                return $preview;
            }
            if ($invoice->stock_posted_at) {
                $quantities = $invoice->items()->get()->groupBy('beverage_id')->map(fn ($items) => (int) $items->sum('total_pcs'));
                $this->impact->apply($quantities, $invoice->tanggal_menerima->toDateString(), -1);
                BeverageRestock::query()->whereIn('beverage_invoice_item_id', $invoice->items()->select('id'))->delete();
            }
            $image = $invoice->image_path;
            $invoice->items()->delete();
            $invoice->delete();

            return null;
        }, 3);
        if ($result === null && $image) {
            Storage::disk('local')->delete($image);
        }

        return $result;
    }
}
