<?php

use App\Actions\MembershipAddonApproval;
use App\Actions\MembershipOperationalApproval;
use App\Models\MembershipAddon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    #[\Livewire\Attributes\Url(as: 'addonApproval')]
    public string $status = 'pending';
    public array $rejectionReasons = [];
    public string $successMessage = '';

    public function boot(): void
    {
        abort_unless(in_array(auth()->user()?->role, MembershipOperationalApproval::ROLES, true), 403);
    }

    public function updatedStatus(): void
    {
        $this->resetPage(pageName: 'addonApprovalPage');
    }

    #[\Livewire\Attributes\On('operational-approvals-updated')]
    public function refreshRequests(): void
    {
        $this->resetPage(pageName: 'addonApprovalPage');
    }

    public function getRequestsProperty(): LengthAwarePaginator
    {
        return MembershipAddon::with(['membership.user', 'membership.members', 'requester', 'decider'])
            ->when(auth()->user()->role !== 'admin', fn ($query) => $query->where('requested_by', auth()->id()))
            ->where('approval_status', in_array($this->status, ['pending', 'approved', 'rejected'], true) ? $this->status : 'pending')
            ->latest('id')->paginate(10, pageName: 'addonApprovalPage');
    }

    public function approve(int $id): void
    {
        app(MembershipAddonApproval::class)->approve(auth()->user(), $id);
        $this->dispatch('addon-approvals-updated');
        $this->successMessage = 'Add-on disetujui. Akses mengikuti tanggal paket dan aktivasi PT.';
    }

    public function reject(int $id): void
    {
        app(MembershipAddonApproval::class)->reject(auth()->user(), $id, $this->rejectionReasons[$id] ?? '');
        $this->dispatch('addon-approvals-updated');
        unset($this->rejectionReasons[$id]);
        $this->successMessage = 'Add-on ditolak. Paket PT tidak dibatalkan.';
    }
};
?>

<section id="membership-addon-approvals" class="mt-6 scroll-mt-24 space-y-4 border-t border-default pt-6" aria-labelledby="addon-approval-title">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h3 id="addon-approval-title" class="text-lg font-semibold text-heading">Pengajuan Add-on</h3>
        <div class="flex items-center gap-2">
            <label for="addon-approval-status" class="sr-only">Status pengajuan add-on</label>
            <select id="addon-approval-status" wire:model.live="status" class="rounded-md border border-default-medium bg-neutral-primary-soft p-2 text-sm text-heading">
                <option value="pending">Menunggu</option><option value="approved">Disetujui</option><option value="rejected">Ditolak</option>
            </select>
            <button type="button" wire:click="$refresh" class="rounded-md border border-default-medium p-2 text-sm">Muat ulang</button>
        </div>
    </div>
    @if($successMessage)<p role="status" class="rounded-md bg-green-50 p-3 text-green-800">{{ $successMessage }}</p>@endif
    @if($errors->any())<div role="alert" class="rounded-md bg-red-50 p-3 text-red-700">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @forelse($this->requests as $addon)
        <details wire:key="addon-request-{{ $addon->id }}" class="rounded-md border border-default bg-neutral-primary-soft p-4">
            <summary class="cursor-pointer text-sm font-semibold text-heading">#{{ $addon->id }} · {{ $addon->name }} · Gratis · {{ ['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'][$addon->approval_status] }}</summary>
            <div class="mt-4 space-y-2 text-sm text-body">
                <p>Member: {{ collect([$addon->membership->user])->concat($addon->membership->members)->filter()->unique('id')->pluck('name')->join(', ') }}</p>
                <p>Paket PT #{{ $addon->membership_id }}: {{ $addon->membership->pt_package_name_snapshot ?: $addon->membership->package_name }}</p>
                <p>Durasi: {{ $addon->duration_months }} bulan, {{ $addon->duration_weeks }} minggu, {{ $addon->duration_days }} hari ({{ $addon->durationInDays() }} hari)</p>
                <p>Mulai: {{ $addon->start_date?->format('d/m/Y') ?? 'Menunggu aktivasi PT' }} · Selesai: {{ $addon->end_date?->format('d/m/Y') ?? 'Belum ditentukan' }}</p>
                <p>Pembayaran PT: {{ $addon->membership->payment_status === 'paid' ? 'Lunas' : 'Belum lunas' }} · Aktivasi PT: {{ $addon->membership->is_active ? 'Sudah aktif' : 'Tidak aktif' }}</p>
                <p>Status add-on: {{ ['pending' => 'Menunggu', 'active' => 'Aktif sesuai tanggal', 'completed' => 'Selesai', 'rejected' => 'Ditolak / dibatalkan'][$addon->status] }}</p>
                <p>Pengaju: {{ $addon->requester?->name ?? 'Petugas dihapus' }} · {{ $addon->requested_at->format('d/m/Y H:i') }}</p>
                @if($addon->decided_at)<p>Diputuskan oleh {{ $addon->decider?->name ?? 'Admin dihapus' }} · {{ $addon->decided_at->format('d/m/Y H:i') }}</p>@endif
                @if($addon->rejection_reason)<p class="text-red-600">Alasan penolakan: {{ $addon->rejection_reason }}</p>@endif
                @if($addon->approval_status === 'pending' && auth()->user()->role === 'admin')
                    <div class="space-y-3 border-t border-default pt-3">
                        <p>Tanggal tetap mengikuti aktivasi PT. Persetujuan tidak memperpanjang masa berlaku add-on.</p>
                        @if($addon->status !== 'rejected')
                            <button type="button" wire:click="approve({{ $addon->id }})" wire:confirm="Setujui add-on dengan tanggal yang mengikuti aktivasi PT?" wire:loading.attr="disabled" class="rounded-md bg-emerald-600 px-4 py-2 text-white disabled:opacity-50">Setujui add-on</button>
                        @endif
                        <label for="addon-reject-{{ $addon->id }}" class="block">Alasan penolakan</label>
                        <textarea id="addon-reject-{{ $addon->id }}" wire:model="rejectionReasons.{{ $addon->id }}" maxlength="1000" rows="2" class="w-full rounded-md border border-default-medium bg-neutral-primary-soft p-2"></textarea>
                        <button type="button" wire:click="reject({{ $addon->id }})" wire:loading.attr="disabled" class="rounded-md border border-red-600 px-4 py-2 text-red-700 disabled:opacity-50">Tolak add-on</button>
                    </div>
                @endif
            </div>
        </details>
    @empty
        <p class="rounded-md border border-default p-4 text-body">Tidak ada pengajuan add-on pada status ini.</p>
    @endforelse
    {{ $this->requests->links() }}
</section>
