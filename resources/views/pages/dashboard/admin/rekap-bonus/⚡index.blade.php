<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\User;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination;

    public $search = '';

    public function updatedSearch()
    {
        $this->resetPage();
    }

    // ?? TAMBAHKAN FUNGSI INI ??
    public function toggleStatus($id)
    {
        $user = User::findOrFail($id);
        
        // Balikkan status aktifnya (True jadi False, False jadi True)
        $user->is_active = !$user->is_active;
        $user->save();

        $statusMessage = $user->is_active ? 'diaktifkan' : 'dinonaktifkan';
        session()->flash('success', "Akun {$user->name} berhasil {$statusMessage}.");
    }
    // ?? SELESAI ??

    public function with(): array
    {
        return [
            'users' => User::query()
                ->whereIn('role', ['pt', 'sales', 'kasir_gym']) // Mencari role 'pt' atau 'sales'
                ->where('is_active', 1)            // Menambahkan filter is_active (1 atau true)
                ->where(function ($query) {
                    $query->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%');
                })
                ->latest()
                ->paginate(10)
        ];
    }
};
?>

<div class="bonus-recap-page">
    <div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-heading">Rekap <span class="text-brand">Bonus Karyawan</span></h1>
            <p class="mt-2 text-sm text-body">Pilih karyawan untuk melihat rincian bonus dan komisi.</p>
        </div>
    </div>

    @if (session()->has('success'))
        <div class="mb-6 rounded-md border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 shadow-xs" role="status">
            {{ session('success') }}
        </div>
    @endif

    <div class="relative overflow-hidden rounded-md border border-default bg-neutral-primary-soft shadow-xs">
        <div class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="relative w-full sm:max-w-md">
                <label for="table-search" class="sr-only">Cari nama atau email karyawan</label>
                <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                    <svg class="w-4 h-4 text-body" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="m21 21-3.5-3.5M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z"/>
                    </svg>
                </div>
                <input type="text" id="table-search" wire:model.live.debounce.300ms="search"
                    class="block w-full rounded-md border border-default-medium bg-white py-2.5 ps-9 pe-3 text-sm text-heading shadow-xs placeholder:text-body focus:border-brand focus:ring-brand"
                    placeholder="Cari nama atau email...">
            </div>
            <p class="text-sm text-body" role="status">{{ $users->total() }} karyawan</p>
        </div>

        <table data-responsive-table data-responsive-breakpoint="lg" class="table-fixed w-full text-sm text-left rtl:text-right text-body">
            <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-t border-default-medium">
                <tr>
                    <th scope="col" class="px-6 py-3 font-medium">Nama Karyawan</th>
                    <th scope="col" class="px-6 py-3 text-center font-medium lg:w-48">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr wire:key="bonus-employee-{{ $user->id }}" class="border-b border-default bg-neutral-primary-soft hover:bg-neutral-secondary-medium">
                        <th scope="row" class="px-6 py-4 text-heading">
                            <div class="flex items-center gap-3">
                                @if($user->photo)
                                    <img class="h-10 w-10 shrink-0 rounded-full object-cover" src="{{ asset('storage/' . $user->photo) }}" alt="{{ $user->name }}">
                                @else
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-neutral-secondary-medium text-sm font-semibold text-heading" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                                @endif

                                <div class="min-w-0">
                                    <div class="break-words font-semibold">{{ $user->name }}</div>
                                    <div class="break-all font-normal text-body">{{ $user->email }}</div>
                                </div>
                            </div>
                        </th>
                        <td class="px-6 py-4 text-center">
                            <a href="{{ route('admin.rekap-bonus.detail', $user->id) }}" wire:navigate class="inline-flex items-center justify-center rounded-md border border-transparent bg-brand px-4 py-2.5 text-sm font-medium leading-5 text-white shadow-xs hover:bg-brand-strong focus:outline-none focus:ring-4 focus:ring-brand-medium" aria-label="Lihat bonus {{ $user->name }}">Lihat Bonus</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="px-6 py-8 text-center text-body">
                            {{ $search !== '' ? 'Tidak ada karyawan yang sesuai dengan pencarian.' : 'Belum ada data karyawan aktif.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        
        <div class="p-4 border-t border-default-medium">
            {{ $users->links('components.custom-pagination') }}
        </div>
    </div>
</div>
