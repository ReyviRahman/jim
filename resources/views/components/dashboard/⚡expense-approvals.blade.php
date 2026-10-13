<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use App\Actions\ExpenseApproval;
use App\Models\ExpenseRequest;

new class extends Component
{
    use WithPagination;

    #[Locked]
    public string $source;
    public string $status = 'pending';
    public array $rejectionReasons = [];
    public string $successMessage = '';

    public function mount(string $source): void
    {
        $this->source = $source;
        $this->authorizeSource();
    }

    public function updatedStatus(): void
    {
        $this->resetPage(pageName: 'expenseApprovalPage');
    }

    #[On('expense-approvals-updated')]
    public function refreshRequests(): void
    {
        $this->resetPage(pageName: 'expenseApprovalPage');
    }

    public function getRequestsProperty(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $this->authorizeSource();
        return ExpenseRequest::with('decider')->where('source', $this->source)
            ->when(auth()->user()->role !== 'admin', fn ($query) => $query->where('requested_by', auth()->id()))
            ->where('status', in_array($this->status, ['pending', 'approved', 'rejected'], true) ? $this->status : 'pending')
            ->latest('id')->paginate(10, pageName: 'expenseApprovalPage');
    }

    public function approve(int $id): void
    {
        $this->authorizeSource();
        app(ExpenseApproval::class)->approve(auth()->user(), $id);
        $this->dispatch('expense-approvals-updated');
        $this->successMessage = 'Pengeluaran disetujui dan sudah masuk laporan.';
    }

    public function reject(int $id): void
    {
        $this->authorizeSource();
        app(ExpenseApproval::class)->reject(auth()->user(), $id, $this->rejectionReasons[$id] ?? '');
        unset($this->rejectionReasons[$id]);
        $this->dispatch('expense-approvals-updated');
        $this->successMessage = 'Pengajuan pengeluaran ditolak.';
    }

    public function deletePending(int $id): void
    {
        $this->authorizeSource();
        app(ExpenseApproval::class)->deletePending(auth()->user(), $id);
        unset($this->rejectionReasons[$id]);
        $this->dispatch('expense-approvals-updated');
        $this->successMessage = 'Pengajuan pengeluaran dihapus.';
    }

    private function authorizeSource(): void
    {
        abort_unless(in_array(auth()->user()?->role, ExpenseApproval::ROLES[$this->source] ?? [], true), 403);
    }
};
?>

<div id="expense-approvals" class="my-6 scroll-mt-24 rounded-md border border-default bg-neutral-primary-soft p-4">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-semibold text-heading">Approval Pengeluaran {{ $source === 'gym' ? 'Gym' : 'Minuman' }}</h2>
        <div class="flex items-center gap-2">
            <label for="expense-approval-status" class="sr-only">Status pengajuan pengeluaran</label>
            <select id="expense-approval-status" wire:model.live="status" class="rounded-md border border-default-medium p-2 text-sm">
                <option value="pending">Menunggu</option><option value="approved">Disetujui</option><option value="rejected">Ditolak</option>
            </select>
            <button type="button" wire:click="$refresh" class="rounded-md border border-default-medium p-2 text-sm">Muat ulang</button>
        </div>
    </div>
    <p class="mb-4 text-sm text-body">Pengajuan menunggu dan ditolak belum masuk laporan. Pengeluaran baru dicatat setelah admin menyetujui.</p>
    @if($successMessage)<p role="status" class="mb-4 rounded-md bg-green-50 p-3 text-green-800">{{ $successMessage }}</p>@endif
    @if($errors->any())<div role="alert" class="mb-4 rounded-md bg-red-50 p-3 text-red-700">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <div class="space-y-3">
        @forelse($this->requests as $request)
            <details wire:key="expense-request-{{ $request->id }}" class="rounded-md border border-default p-4">
                <summary class="cursor-pointer text-sm font-semibold text-heading">#{{ $request->id }} · {{ $request->requested_by_name }} · {{ $request->expense_date->format('d/m/Y') }} · Rp {{ number_format($request->amount, 0, ',', '.') }} · {{ ['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'][$request->status] }}</summary>
                <div class="mt-4 space-y-3 text-sm text-body">
                    <p class="whitespace-pre-wrap break-words">{{ $request->description }}</p>
                    <p>Shift: {{ $request->shift ?? '-' }} · Diajukan: {{ $request->requested_at->format('d/m/Y H:i') }}</p>
                    @if($request->decided_at)<p>Diproses oleh {{ $request->decider?->name }} pada {{ $request->decided_at->format('d/m/Y H:i') }}</p>@endif
                    @if($request->rejection_reason)<p class="text-red-700">Alasan penolakan: {{ $request->rejection_reason }}</p>@endif
                    @if($request->status === 'pending')
                        @if(auth()->user()->role === 'admin')
                            <button type="button" wire:click="approve({{ $request->id }})" wire:confirm="Setujui pengeluaran dan masukkan ke laporan sesuai tanggal pengajuan?" wire:loading.attr="disabled" class="rounded-md bg-emerald-600 px-4 py-2 text-white disabled:opacity-50">Setujui</button>
                            <label for="expense-reject-{{ $request->id }}" class="block">Alasan penolakan</label>
                            <textarea id="expense-reject-{{ $request->id }}" wire:model="rejectionReasons.{{ $request->id }}" maxlength="1000" rows="2" class="w-full rounded-md border border-default-medium p-2"></textarea>
                            <button type="button" wire:click="reject({{ $request->id }})" wire:loading.attr="disabled" class="rounded-md border border-red-600 px-4 py-2 text-red-700 disabled:opacity-50">Tolak</button>
                        @endif
                        <button type="button" wire:click="deletePending({{ $request->id }})" wire:confirm="Hapus permanen pengajuan pengeluaran ini?" wire:loading.attr="disabled" class="rounded-md border border-red-600 px-4 py-2 text-red-700 disabled:opacity-50">Hapus pengajuan</button>
                    @endif
                </div>
            </details>
        @empty
            <p class="text-sm text-body">Tidak ada pengajuan pengeluaran pada status ini.</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $this->requests->links() }}</div>
</div>
