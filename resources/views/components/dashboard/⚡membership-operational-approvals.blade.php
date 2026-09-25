<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Actions\MembershipOperationalApproval;
use App\Models\MembershipOperationalRequest;

new class extends Component
{
    use WithPagination;

    public string $status = 'pending';
    public array $rejectionReasons = [];
    public string $successMessage = '';

    public function mount(): void
    {
        abort_unless(in_array(auth()->user()?->role, MembershipOperationalApproval::ROLES, true), 403);
        if (request()->query('approval') === 'pending') {
            $this->status = 'pending';
            $this->resetPage(pageName: 'membershipApprovalPage');
        }
    }

    public function updatedStatus(): void
    {
        $this->resetPage(pageName: 'membershipApprovalPage');
    }

    public function getRequestsProperty(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        abort_unless(in_array(auth()->user()?->role, MembershipOperationalApproval::ROLES, true), 403);
        return MembershipOperationalRequest::with(['decider', 'membership'])
            ->when(auth()->user()->role !== 'admin', fn ($query) => $query->where('requested_by', auth()->id()))
            ->where('status', in_array($this->status, ['pending', 'approved', 'rejected'], true) ? $this->status : 'pending')
            ->latest('id')->paginate(10, pageName: 'membershipApprovalPage');
    }

    public function approve(int $id): void
    {
        app(MembershipOperationalApproval::class)->approve(auth()->user(), $id);
        $this->dispatch('operational-approvals-updated');
        $this->successMessage = 'Pengajuan disetujui. Membership Operasional sudah dicatat.';
    }

    public function reject(int $id): void
    {
        app(MembershipOperationalApproval::class)->reject(auth()->user(), $id, $this->rejectionReasons[$id] ?? '');
        unset($this->rejectionReasons[$id]);
        $this->dispatch('operational-approvals-updated');
        $this->successMessage = 'Pengajuan ditolak.';
    }

    public function deletePending(int $id): void
    {
        app(MembershipOperationalApproval::class)->deletePending(auth()->user(), $id);
        unset($this->rejectionReasons[$id]);
        $this->resetPage(pageName: 'membershipApprovalPage');
        $this->dispatch('operational-approvals-updated');
        $this->successMessage = 'Pengajuan dihapus permanen.';
    }
};
?>

<div id="membership-operational-approvals" class="scroll-mt-24">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-semibold text-heading">Approval Membership</h2>
        <div class="flex items-center gap-2">
            <label for="approval-status" class="sr-only">Status pengajuan</label>
            <select id="approval-status" wire:model.live="status" class="rounded-md border border-default-medium p-2 text-sm">
                <option value="pending">Menunggu</option><option value="approved">Disetujui</option><option value="rejected">Ditolak</option>
            </select>
            <button type="button" wire:click="$refresh" class="rounded-md border border-default-medium p-2 text-sm">Muat ulang</button>
        </div>
    </div>
    @if($successMessage)<p role="status" class="mb-4 rounded-md bg-green-50 p-3 text-green-800">{{ $successMessage }}</p>@endif
    @if($errors->any())<div role="alert" class="mb-4 rounded-md bg-red-50 p-3 text-red-700">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <div class="space-y-3">
        @forelse($this->requests as $request)
            @php($snapshot = $request->snapshot)
            @php($package = $snapshot['membership'])
            <details wire:key="membership-request-{{ $request->id }}" class="rounded-md border border-default bg-neutral-primary-soft p-4">
                <summary class="cursor-pointer text-sm font-semibold text-heading">#{{ $request->id }} · {{ $request->requested_by_name }} · {{ $request->requested_at->locale('id')->translatedFormat('l, d/m/Y H:i') }} · Rp {{ number_format($package['price_paid'], 0, ',', '.') }} · {{ ['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'][$request->status] }}</summary>
                <div class="mt-4 space-y-2 text-sm text-body">
                    <p>Member: {{ collect($snapshot['members'])->pluck('name')->join(', ') }}</p>
                    <p>Jenis: {{ $package['type'] }} · Paket: {{ $snapshot['gym_package_name'] ?? '—' }} / {{ $snapshot['pt_package_name'] ?? '—' }}</p>
                    <p>Petugas: {{ $snapshot['admin_name'] }} · Shift: {{ $snapshot['shift'] ?? '—' }} · Trainer: {{ $snapshot['trainer_name'] ?? '—' }}</p>
                    <p>Harga: Rp {{ number_format($package['base_price'], 0, ',', '.') }} · Diskon: Rp {{ number_format($package['discount_applied'], 0, ',', '.') }} · Biaya admin: Rp {{ number_format($package['admin_fee'], 0, ',', '.') }}</p>
                    <p>Sesi PT: {{ $package['total_sessions'] ?? '—' }} · Aktivasi: {{ $package['is_active'] ? 'Aktif sesuai tanggal' : 'Belum diaktifkan' }}</p>
                    <p>Tanggal mulai: {{ $package['start_date'] ?? '—' }} · Akhir Gym: {{ $package['membership_end_date'] ?? '—' }} · Akhir PT: {{ $package['pt_end_date'] ?? '—' }}</p>
                    <p>Tanggal pencatatan: {{ $snapshot['payment_date'] }}</p>
                    <p class="whitespace-pre-wrap break-words">Alasan: {{ $request->reason }}</p>
                    <p class="whitespace-pre-wrap break-words">Catatan: {{ $package['notes'] }}</p>
                    <p>Persetujuan dan tanda tangan: {{ count($request->documents) }} member tersimpan.</p>
                    @if($request->decided_at)<p>{{ $request->decider?->name }} · {{ $request->decided_at->locale('id')->translatedFormat('l, d/m/Y H:i') }}</p>@endif
                    @if($request->rejection_reason)<p class="text-red-700">Alasan penolakan: {{ $request->rejection_reason }}</p>@endif
                    @if($request->membership)<a href="{{ route('admin.riwayat.detail', $request->membership->user_id) }}" wire:navigate class="text-brand underline">Lihat riwayat membership</a>@endif
                    @if($request->status === 'pending')
                        <div class="space-y-3 border-t border-default pt-3">
                            @if(auth()->user()->role === 'admin')
                                <p class="text-xs text-body">Tanggal paket tetap mengikuti pengajuan. Paket yang sudah kedaluwarsa hanya dicatat untuk riwayat.</p>
                                <button type="button" wire:click="approve({{ $request->id }})" wire:confirm="Setujui pengajuan dan buat membership sesuai tanggal tersebut?" wire:loading.attr="disabled" class="rounded-md bg-emerald-600 px-4 py-2 text-white disabled:opacity-50">Setujui</button>
                                <label for="reject-{{ $request->id }}" class="block">Alasan penolakan</label>
                                <textarea id="reject-{{ $request->id }}" wire:model="rejectionReasons.{{ $request->id }}" maxlength="1000" rows="2" class="w-full rounded-md border border-default-medium p-2"></textarea>
                                <button type="button" wire:click="reject({{ $request->id }})" wire:loading.attr="disabled" class="rounded-md border border-red-600 px-4 py-2 text-red-700 disabled:opacity-50">Tolak</button>
                            @endif
                            <button type="button" wire:click="deletePending({{ $request->id }})" wire:confirm="Hapus permanen pengajuan dan dokumennya? Tindakan ini tidak dapat dibatalkan." wire:loading.attr="disabled" class="rounded-md border border-red-600 px-4 py-2 text-red-700 disabled:opacity-50">Hapus pengajuan</button>
                        </div>
                    @endif
                </div>
            </details>
        @empty
            <p class="rounded-md border border-default p-4 text-body">Tidak ada pengajuan pada status ini.</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $this->requests->links() }}</div>
</div>
