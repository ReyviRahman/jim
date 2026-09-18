<?php

use App\Actions\StoreMembershipHold;
use App\Models\Membership;
use App\Models\MembershipHold;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::admin')] class extends Component
{
    use WithFileUploads;

    public Membership $membership;

    #[Locked]
    public string $submissionToken;

    #[Locked]
    public string $expectedEndDate = '';

    #[Locked]
    public int $returnUserId;

    #[Locked]
    public ?int $savedHoldId = null;

    public $months = 1;
    public $total_amount = '';
    public string $payment_date = '';
    public $admin_id = '';
    public string $notes = '';
    public bool $is_split_payment = false;
    public string $payment_method = 'cash';

    /** @var array<string, mixed> */
    public array $amounts = ['cash' => '', 'transfer' => '', 'qris' => '', 'debit' => ''];

    /** @var array<string, mixed> */
    public array $proofs = [];

    public function mount(Membership $membership): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
        abort_unless($membership->type === 'pt', 404);
        $this->membership = $membership;
        $this->expectedEndDate = $membership->pt_end_date?->toDateString() ?? '';
        $this->submissionToken = (string) Str::uuid();
        $this->payment_date = today()->toDateString();
        $this->returnUserId = $membership->user_id;
        $returnUser = (int) request()->query('user', $membership->user_id);

        if ($membership->members()->where('users.id', $returnUser)->exists()) {
            $this->returnUserId = $returnUser;
        }
    }

    #[Computed]
    public function adminUsers(): Collection
    {
        return User::with('assignedShift')->where('role', 'kasir_gym')->where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function previewEndDate(): ?Carbon
    {
        if ($this->expectedEndDate === '' || filter_var($this->months, FILTER_VALIDATE_INT) === false || (int) $this->months < 1) {
            return null;
        }

        $date = Carbon::parse($this->expectedEndDate);
        $maxMonths = (9999 - $date->year) * 12 + 12 - $date->month;

        return (int) $this->months <= $maxMonths ? $date->addMonthsNoOverflow((int) $this->months) : null;
    }

    #[Computed]
    public function totalAmount(): int
    {
        return filter_var($this->total_amount, FILTER_VALIDATE_INT) !== false && (int) $this->total_amount > 0
            ? (int) $this->total_amount : 0;
    }

    #[Computed]
    public function savedHold(): ?MembershipHold
    {
        return $this->savedHoldId ? $this->membership->holds()->with('transactions')->findOrFail($this->savedHoldId) : null;
    }

    public function updatedPaymentMethod(): void
    {
        $this->reset('proofs');
        $this->resetValidation();
    }

    public function updatedIsSplitPayment(): void
    {
        $this->reset('proofs');
        $this->resetValidation();
    }

    public function save(StoreMembershipHold $storeHold): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        if ($this->savedHoldId) {
            return;
        }

        $this->resetValidation();

        try {
            $hold = $storeHold->execute($this->membership, auth()->user(), $this->submissionToken, $this->expectedEndDate, [
                'months' => $this->months,
                'total_amount' => $this->total_amount,
                'payment_date' => $this->payment_date,
                'admin_id' => $this->admin_id,
                'notes' => $this->notes,
                'is_split_payment' => $this->is_split_payment,
                'payment_method' => $this->payment_method,
                'amounts' => $this->amounts,
                'proofs' => $this->proofs,
            ]);
        } catch (ValidationException $exception) {
            $this->membership->refresh();
            $this->expectedEndDate = $this->membership->pt_end_date?->toDateString() ?? '';
            unset($this->previewEndDate, $this->totalAmount);

            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);
            $this->addError('save', 'Hold belum tersimpan. Silakan coba kembali.');

            return;
        }

        $this->savedHoldId = $hold->id;
        $this->membership->refresh();
        $this->reset('proofs');
    }
};
?>

<div>
    <a href="{{ route('admin.riwayat.detail', $returnUserId) }}" wire:navigate class="mb-6 inline-flex text-sm font-medium text-body hover:text-heading">&larr; Kembali ke Riwayat</a>
    <h1 class="mb-6 text-xl font-semibold text-heading">Hold Paket PT</h1>

    @if($this->savedHold)
        <section class="rounded-md border border-green-200 bg-green-50 p-6 text-green-900" role="status">
            <h2 class="text-lg font-semibold">Hold berhasil disimpan</h2>
            <p class="mt-2">Masa aktif PT diperpanjang {{ $this->savedHold->months }} bulan sampai {{ $this->savedHold->new_end_date->format('d M Y') }}.</p>
            @if($this->savedHold->new_end_date->lt(today()))
                <p class="mt-2">Tanggal akhir masih kedaluwarsa. Paket belum aktif kembali.</p>
            @endif
            <div class="mt-4 flex flex-wrap gap-3">
                @foreach($this->savedHold->transactions as $transaction)
                    <a wire:key="hold-invoice-{{ $transaction->id }}" href="{{ route('admin.penjualan.invoice', $transaction) }}" class="rounded-md border border-green-300 bg-white px-4 py-2 text-sm font-medium">Unduh Invoice {{ strtoupper($transaction->payment_method) }}</a>
                @endforeach
                <a href="{{ route('admin.riwayat.membership.invoice', $membership) }}" class="rounded-md border border-green-300 bg-white px-4 py-2 text-sm font-medium">Unduh Invoice Membership</a>
            </div>
        </section>
    @else
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <section class="self-start rounded-md border border-default bg-neutral-primary-soft p-6 shadow-xs">
                <h2 class="mb-4 border-b border-default-medium pb-3 text-lg font-semibold text-heading">Informasi Paket</h2>
                <dl class="space-y-4 text-sm">
                    <div><dt class="text-body">Anggota</dt><dd class="mt-1 font-medium text-heading">{{ collect([$membership->user])->merge($membership->members)->filter()->unique('id')->pluck('name')->join(', ') }}</dd></div>
                    <div><dt class="text-body">Paket PT</dt><dd class="mt-1 font-medium text-heading">{{ $membership->ptPackage?->name ?? $membership->package_name ?? '-' }}</dd></div>
                    <div><dt class="text-body">Personal Trainer</dt><dd class="mt-1 font-medium text-heading">{{ $membership->personalTrainer?->name ?? '-' }}</dd></div>
                    <div><dt class="text-body">Sisa Sesi</dt><dd class="mt-1 font-medium text-heading">{{ $membership->remaining_sessions }} sesi</dd></div>
                    <div><dt class="text-body">Tanggal Akhir Lama</dt><dd class="mt-1 font-medium text-heading">{{ $expectedEndDate ?: '-' }}</dd></div>
                </dl>
                <p class="mt-5 text-sm text-body">Hold memperpanjang masa aktif tanpa menambah sesi. Pembayarannya terpisah dari cicilan paket PT.</p>
            </section>

            <form wire:submit="save" class="space-y-6 rounded-md border border-default bg-neutral-primary-soft p-6 shadow-xs lg:col-span-2">
                @if($membership->holdIneligibilityReason())
                    <p class="rounded-md bg-red-50 p-4 text-sm text-red-700" role="alert">{{ $membership->holdIneligibilityReason() }}</p>
                @endif
                @if($errors->any())
                    <div role="alert" class="rounded-md bg-red-50 p-4 text-sm text-red-700">
                        @foreach($errors->all() as $error)
                            <p wire:key="hold-error-{{ $loop->index }}">{{ $error }}</p>
                        @endforeach
                    </div>
                @endif
                <h2 class="border-b border-default-medium pb-3 text-lg font-semibold text-heading">Durasi Hold</h2>
                <div>
                    <label for="hold-months" class="mb-2 block text-sm font-medium text-heading">Jumlah Bulan</label>
                    <input id="hold-months" type="number" min="1" step="1" wire:model.live="months" required class="block w-full rounded-md border border-default-medium bg-white px-3 py-2.5 text-sm text-heading focus:border-brand focus:ring-brand">
                    <p class="mt-2 text-sm text-body">Masa hold dihitung dari tanggal akhir lama.</p>
                </div>
                <div>
                    <label for="hold-amount" class="mb-2 block text-sm font-medium text-heading">Nominal Total Hold (Rp)</label>
                    <input id="hold-amount" type="text" inputmode="numeric" pattern="[0-9]+" maxlength="12" wire:model.live="total_amount" required placeholder="Masukkan nominal total hold" class="block w-full rounded-md border border-default-medium bg-white px-3 py-2.5 text-sm text-heading focus:border-brand focus:ring-brand">
                    <p class="mt-2 text-sm text-body">Isi total biaya untuk seluruh durasi hold, bukan harga per bulan.</p>
                </div>
                <dl class="grid gap-4 rounded-md bg-neutral-secondary-medium p-4 sm:grid-cols-2" aria-live="polite">
                    <div><dt class="text-sm text-body">Tanggal Akhir Baru</dt><dd class="mt-1 font-semibold text-heading">{{ $this->previewEndDate?->format('d M Y') ?? '-' }}</dd></div>
                    <div><dt class="text-sm text-body">Total Biaya Hold</dt><dd class="mt-1 text-xl font-bold text-heading">Rp {{ number_format($this->totalAmount, 0, ',', '.') }}</dd></div>
                </dl>
                @if($this->previewEndDate?->lt(today()))
                    <p class="rounded-md bg-amber-50 p-3 text-sm text-amber-800" role="status">Tanggal akhir baru masih kedaluwarsa. Paket belum akan aktif kembali.</p>
                @endif
                <h2 class="border-b border-default-medium pb-3 text-lg font-semibold text-heading">Pembayaran</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="hold-date" class="mb-2 block text-sm font-medium text-heading">Tanggal Pembayaran</label>
                        <input id="hold-date" type="date" wire:model="payment_date" required class="block w-full rounded-md border border-default-medium bg-white px-3 py-2.5 text-sm text-heading">
                    </div>
                    <div>
                        <label for="hold-cashier" class="mb-2 block text-sm font-medium text-heading">Kasir / Shift</label>
                        <select id="hold-cashier" wire:model="admin_id" required class="block w-full rounded-md border border-default-medium bg-white px-3 py-2.5 text-sm text-heading">
                            <option value="">Pilih Kasir</option>
                            @foreach($this->adminUsers as $cashier)
                                <option wire:key="hold-cashier-{{ $cashier->id }}" value="{{ $cashier->id }}">{{ $cashier->name }} / {{ $cashier->shiftSnapshot() ?? 'Tanpa shift' }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm font-medium text-heading"><input type="checkbox" wire:model.live="is_split_payment" class="rounded border-gray-300 text-brand focus:ring-brand"> Split Payment (Pisah Metode Bayar)</label>
                @if($is_split_payment)
                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach(['cash', 'transfer', 'qris', 'debit'] as $method)
                            <div wire:key="hold-split-{{ $method }}" class="space-y-3 rounded-md border border-default-medium p-4">
                                <label for="hold-{{ $method }}" class="block text-sm font-medium text-heading">{{ strtoupper($method) }}</label>
                                <input id="hold-{{ $method }}" type="number" min="0" step="1" wire:model.live="amounts.{{ $method }}" placeholder="0" class="block w-full rounded-md border border-default-medium bg-white px-3 py-2 text-sm text-heading">
                                @if($method !== 'cash' && (float) ($amounts[$method] ?? 0) > 0)
                                    <x-payment-proof-upload :model="'proofs.'.$method" :proof="$proofs[$method] ?? null" :label="'Bukti Pembayaran '.strtoupper($method)" />
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <p class="text-sm text-body">Total split: Rp {{ number_format(collect($amounts)->sum(fn ($amount) => is_numeric($amount) ? (float) $amount : 0), 0, ',', '.') }} / Rp {{ number_format($this->totalAmount, 0, ',', '.') }}</p>
                @else
                    <div>
                        <label for="hold-method" class="mb-2 block text-sm font-medium text-heading">Metode Pembayaran</label>
                        <select id="hold-method" wire:model.live="payment_method" class="block w-full rounded-md border border-default-medium bg-white px-3 py-2.5 text-sm text-heading">
                            @foreach(['cash', 'transfer', 'qris', 'debit'] as $method)
                                <option wire:key="hold-method-{{ $method }}" value="{{ $method }}">{{ strtoupper($method) }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if(in_array($payment_method, ['transfer', 'qris', 'debit'], true))
                        <x-payment-proof-upload wire:key="hold-proof-{{ $payment_method }}" :model="'proofs.'.$payment_method" :proof="$proofs[$payment_method] ?? null" />
                    @endif
                @endif
                <div>
                    <label for="hold-notes" class="mb-2 block text-sm font-medium text-heading">Catatan</label>
                    <textarea id="hold-notes" wire:model="notes" rows="3" maxlength="2000" class="block w-full rounded-md border border-default-medium bg-white px-3 py-2 text-sm text-heading"></textarea>
                </div>
                <button type="submit" wire:loading.attr="disabled" @disabled($membership->holdIneligibilityReason() !== null) class="w-full rounded-md bg-brand px-4 py-3 text-sm font-medium text-heading hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium disabled:cursor-not-allowed disabled:opacity-50">
                    <span wire:loading.remove>Simpan Hold & Pembayaran</span><span wire:loading>Memproses...</span>
                </button>
            </form>
        </div>
    @endif
</div>
