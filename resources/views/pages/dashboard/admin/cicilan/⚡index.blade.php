<?php

use App\Models\Membership;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed; 
use Livewire\Attributes\Locked;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination;

    public $search = '';

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

    #[Computed]
    public function memberships()
    {
        // Tambahkan 'members' di dalam array with()
        return Membership::with(['user', 'members', 'admin', 'followUp', 'gymPackage', 'ptPackage'])
            ->whereIn('payment_status', ['partial', 'unpaid'])
            ->where('type', $this->ptOnly ? '=' : '!=', 'pt')
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
    <x-installment-list :memberships="$this->memberships" :search="$search" :pt-only="$ptOnly" />
</div>
