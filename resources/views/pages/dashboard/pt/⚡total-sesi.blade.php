<?php

use App\Concerns\QueriesPtSessionMemberships;
use App\Models\Membership;
use App\Models\PtBooking;
use App\Models\PtPaymentBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::pt')] class extends Component
{
    use QueriesPtSessionMemberships;

    protected User $user;
    protected string $dateStart;
    protected string $dateEnd;

    public string $search = '';

    public function boot(): void
    {
        abort_unless(auth()->user()?->role === 'pt', 403);
        $this->user = auth()->user();
        $start = today()->startOfMonth()->day(16);
        if (today()->day < 16) {
            $start->subMonthNoOverflow();
        }
        $this->dateStart = $start->toDateString();
        $this->dateEnd = $start->copy()->addMonthNoOverflow()->day(15)->toDateString();
    }

    /** @return array{start: string, end: string} */
    #[Computed]
    public function period(): array
    {
        return ['start' => $this->dateStart, 'end' => $this->dateEnd];
    }

    #[Computed]
    public function sessionRows(): \Illuminate\Support\Collection
    {
        return $this->ptMembershipsDirect->map(fn (Membership $membership): array => $this->sessionRow($membership, 'Utama'))
            ->concat($this->ptMembershipsBookingOnly->filter(fn (Membership $membership): bool => $membership->berjalan > 0)
                ->map(fn (Membership $membership): array => $this->sessionRow($membership, 'Pengganti/Cadangan')));
    }

    /** @return array{id: int, name: string, role: string, category: string, initial: int, sessions: int, unpaid: int, paid: int, expired: int, substituted: int, remaining: int, free: int, admin: string, sales: string} */
    private function sessionRow(Membership $membership, string $role): array
    {
        $initial = ($membership->total_sessions ?? 0) + ($membership->sesi_ditambahkan ?? 0) - ($membership->bookings_before ?? 0);
        $expired = ($membership->hangus ?? 0) + ($membership->sesi_hangus ?? 0);

        return [
            'id' => $membership->id,
            'name' => $membership->members->pluck('name')->whenEmpty(fn () => collect([$membership->user?->name ?? '-']))->implode(', '),
            'role' => $role,
            'category' => ($membership->ptPackage?->category ?? $membership->gymPackage?->category ?? '-').' / '.$membership->getPtCategoryLabelFor($this->user->id),
            'initial' => $initial,
            'sessions' => $membership->berjalan,
            'unpaid' => $membership->berjalan_belum_dibayar,
            'paid' => $membership->berjalan_dibayar,
            'expired' => $expired,
            'substituted' => $membership->sesi_digantikan,
            'remaining' => $initial - $membership->berjalan - $expired - $membership->sesi_digantikan,
            'free' => $membership->free_berjalan,
            'admin' => $membership->followUp?->name ?? '-',
            'sales' => $membership->followUpTwo?->name ?? '-',
        ];
    }

    #[Computed]
    public function submissions(): \Illuminate\Database\Eloquent\Collection
    {
        return PtPaymentBatch::query()->where('pt_id', auth()->id())
            ->select(['id', 'pt_id', 'date_start', 'date_end', 'status', 'created_at'])
            ->withCount('items')->latest('id')->get();
    }

    public function submitSessions(): void
    {
        abort_unless(auth()->user()?->role === 'pt', 403);
        $count = DB::transaction(function (): int {
            User::query()->whereKey(auth()->id())->lockForUpdate()->firstOrFail();
            $bookings = PtBooking::query()->where('pt_id', auth()->id())
                ->whereBetween('booking_date', [$this->dateStart, $this->dateEnd])
                ->where('attendance', 'attended')->where('is_free', false)->where('is_paid', false)
                ->whereDoesntHave('paymentBatchItem')->lockForUpdate()->get();
            if ($bookings->isEmpty()) {
                return 0;
            }
            $batch = PtPaymentBatch::create([
                'pt_id' => auth()->id(), 'requested_by' => auth()->id(),
                'date_start' => $this->dateStart, 'date_end' => $this->dateEnd, 'status' => 'pending',
            ]);
            $batch->items()->createMany($bookings->map(fn (PtBooking $booking): array => ['pt_booking_id' => $booking->id])->all());

            return $bookings->count();
        });
        unset($this->submissions);
        session()->flash($count ? 'success' : 'error', $count
            ? "Berhasil mengajukan {$count} sesi. Menunggu persetujuan admin."
            : 'Tidak ada sesi baru yang dapat diajukan dalam periode ini.');
    }
};
?>

<div>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-heading">Total Sesi</h1>
            <p class="mt-2 text-sm text-body">Periode {{ \Carbon\Carbon::parse($this->period['start'])->translatedFormat('d F Y') }} sampai {{ \Carbon\Carbon::parse($this->period['end'])->translatedFormat('d F Y') }}</p>
            <p class="mt-1 text-xs text-body">Periode otomatis tanggal 16 sampai tanggal 15. Tanggal tidak dapat diubah.</p>
        </div>
    </div>
    @foreach (['success', 'error'] as $messageType)
        @if (session()->has($messageType))
            <div role="status" @class(['mb-4 rounded-base border p-4 text-sm', 'bg-green-50 text-green-800 border-green-200' => $messageType === 'success', 'bg-red-50 text-red-800 border-red-200' => $messageType === 'error'])>{{ session($messageType) }}</div>
        @endif
    @endforeach
    <div class="mb-4">
        <label for="session-member-search" class="mb-2 block text-sm text-heading">Cari nama member</label>
        <input id="session-member-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama member..." class="w-full rounded-base border border-default-medium bg-neutral-secondary-medium px-3 py-2.5 text-sm text-heading sm:w-64">
    </div>
    <div class="overflow-hidden rounded-md border border-default bg-neutral-primary-soft shadow-xs">
        <table data-responsive-table data-responsive-breakpoint="xl" class="w-full text-left text-sm text-body">
            <thead class="border-b border-default-medium bg-neutral-secondary-medium"><tr>
                @foreach (['Nama Member', 'PT', 'Kategori', 'Sesi Awal', 'Belum Bayar', 'Sudah Bayar', 'Hangus', 'Sesi Digantikan', 'Sisa Sesi', 'Free Berjalan', 'Admin Follow Up', 'Sales Follow Up'] as $heading)
                    <th scope="col" class="px-4 py-3 font-medium">{{ $heading }}</th>
                @endforeach
            </tr></thead>
            <tbody>
                @forelse ($this->sessionRows as $row)
                    <tr wire:key="pt-total-session-{{ $row['id'] }}" class="border-b border-default">
                        <td class="px-4 py-4 font-medium text-heading">{{ $row['name'] }}</td>
                        @foreach (['role', 'category', 'initial', 'unpaid', 'paid', 'expired', 'substituted', 'remaining', 'free', 'admin', 'sales'] as $field)
                            <td class="px-4 py-4">{{ $row[$field] }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="12" class="px-4 py-8 text-center">Tidak ada data sesi pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
        <p class="p-4 font-semibold text-heading">Total Sesi: {{ $this->sessionRows->sum('sessions') }}</p>
        <div class="px-4 pb-4">
            <button type="button" wire:click="submitSessions" wire:confirm="Ajukan semua sesi yang belum diajukan pada periode ini?" wire:loading.attr="disabled" wire:target="submitSessions" class="rounded-base bg-brand px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-strong disabled:opacity-50">
                <span wire:loading.remove wire:target="submitSessions">Ajukan Sesi</span>
                <span wire:loading wire:target="submitSessions">Mengajukan...</span>
            </button>
        </div>
        <p class="px-4 pb-4 text-xs text-body">Pengajuan mencakup seluruh sesi yang memenuhi syarat dalam periode ini, termasuk yang tidak tampil karena pencarian.</p>
    </div>
    <h2 class="mb-4 mt-8 text-xl font-semibold text-heading">Riwayat Pengajuan Sesi</h2>
    <div class="overflow-hidden rounded-md border border-default bg-neutral-primary-soft shadow-xs">
        <table data-responsive-table class="w-full text-left text-sm text-body">
            <thead class="border-b border-default-medium bg-neutral-secondary-medium"><tr>
                @foreach (['ID Pengajuan', 'Periode', 'Jumlah Sesi', 'Status', 'Tanggal Pengajuan'] as $heading)
                    <th scope="col" class="px-4 py-3 font-medium">{{ $heading }}</th>
                @endforeach
            </tr></thead>
            <tbody>
                @forelse ($this->submissions as $batch)
                    <tr wire:key="pt-submission-{{ $batch->id }}" class="border-b border-default">
                        <td class="px-4 py-4">#{{ $batch->id }}</td>
                        <td class="px-4 py-4">{{ $batch->date_start->format('d/m/Y') }} sampai {{ $batch->date_end->format('d/m/Y') }}</td>
                        <td class="px-4 py-4">{{ $batch->items_count }}</td>
                        <td class="px-4 py-4">{{ $batch->status === 'pending' ? 'Menunggu persetujuan' : 'Approved' }}</td>
                        <td class="px-4 py-4">{{ $batch->created_at->format('d/m/Y H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center">Belum ada riwayat pengajuan sesi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
