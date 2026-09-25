<?php

use App\Models\Membership;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed; 
use Livewire\Attributes\Locked;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination;

    public $search = '';
    public string $installmentFilter = 'active';
    public string $successMessage = '';

    #[Locked]
    public bool $ptOnly = false;

    public function mount(bool $ptOnly = false): void
    {
        $this->ptOnly = $ptOnly;
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatedInstallmentFilter(): void
    {
        $this->resetPage();
    }

    public function markExpired(int $id): void
    {
        $this->setInstallmentExpired($id, true);
    }

    public function restoreInstallment(int $id): void
    {
        $this->setInstallmentExpired($id, false);
    }

    private function setInstallmentExpired(int $id, bool $expired): void
    {
        $actor = auth()->user();
        abort_unless($this->ptOnly && $actor && (in_array($actor->role, ['admin', 'kasir_gym'], true) || $actor->isHeadCoach()), 403);
        $this->resetValidation();
        $this->successMessage = '';
        DB::transaction(function () use ($id, $expired): void {
            $membership = Membership::lockForUpdate()->findOrFail($id);
            if ($membership->type !== 'pt' || ! in_array($membership->payment_status, ['partial', 'unpaid'], true)) {
                throw ValidationException::withMessages(['installment' => 'Hanya cicilan PT yang belum lunas dapat ditandai Hangus atau dipulihkan.']);
            }
            if (($membership->pt_installment_expired_at !== null) === $expired) {
                return;
            }
            $membership->update([
                'pt_installment_expired_at' => $expired ? now() : null,
                'pt_installment_expired_by' => $expired ? auth()->id() : null,
            ]);
        }, 3);
        unset($this->memberships);
        $this->resetPage();
        $this->successMessage = $expired ? 'Cicilan PT ditandai Hangus.' : 'Cicilan PT dipulihkan ke daftar Aktif.';
    }

    #[Computed]
    public function memberships()
    {
        // Tambahkan 'members' di dalam array with()
        return Membership::with(['user', 'members', 'admin', 'followUp', 'gymPackage', 'ptPackage'])
            ->whereIn('payment_status', ['partial', 'unpaid'])
            ->where('type', $this->ptOnly ? '=' : '!=', 'pt')
            ->when($this->ptOnly, function ($query) {
                if ($this->installmentFilter === 'expired') {
                    $query->whereNotNull('pt_installment_expired_at');
                } else {
                    $query->whereNull('pt_installment_expired_at');
                }
            })
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    // Cari di nama pendaftar utama
                    $q->whereHas('user', function ($subQ) {
                        $subQ->where('name', 'like', '%' . $this->search . '%');
                    })
                    // ATAU cari di nama anggota pivot (pasangannya)
                    ->orWhereHas('members', function ($subQ) {
                        $subQ->where('name', 'like', '%' . $this->search . '%');
                    });
                });
            })
            ->latest()
            ->paginate(10);
    }
}
?>

<div>
    <x-installment-list :memberships="$this->memberships" :search="$search" :pt-only="$ptOnly" :installment-filter="$installmentFilter" :success-message="$successMessage" />
</div>
