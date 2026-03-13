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

    // 👇 TAMBAHKAN FUNGSI INI 👇
    public function toggleStatus($id)
    {
        $user = User::findOrFail($id);
        
        // Balikkan status aktifnya (True jadi False, False jadi True)
        $user->is_active = !$user->is_active;
        $user->save();

        $statusMessage = $user->is_active ? 'diaktifkan' : 'dinonaktifkan';
        session()->flash('success', "Akun {$user->name} berhasil {$statusMessage}.");
    }
    // 👆 SELESAI 👆

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

<div>
    @if (session()->has('success'))
        <div class="mb-4 p-3 bg-green-100 border border-green-400 text-green-700 rounded text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="relative overflow-x-auto bg-neutral-primary-soft shadow-xs rounded-base border border-default">
        <div class="flex items-center justify-between flex-col flex-wrap md:flex-row space-y-4 md:space-y-0 p-4">
            
            {{-- Ubah Judul --}}
            <h5 class="text-xl font-semibold text-heading">Rekap Bonus Karyawan</h5>
            
            <div class="flex sm:flex-row flex-col gap-4 items-center">
                <div class="relative">
                    <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                        <svg class="w-4 h-4 text-body" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="m21 21-3.5-3.5M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z"/>
                        </svg>
                    </div>
                    <input type="text" id="table-search" wire:model.live.debounce.300ms="search" 
                        class="block w-full max-w-96 ps-9 pe-3 py-2 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs placeholder:text-body" 
                        placeholder="Cari nama atau email...">
                </div>
            </div>
        </div>

        <table class="w-full text-sm text-left rtl:text-right text-body">
            <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-t border-default-medium">
                <tr>
                    <th scope="col" class="px-6 py-3 font-medium">Nama Kasir</th>
                    <th scope="col" class="px-6 py-3 font-medium">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr class="bg-neutral-primary-soft border-b border-default hover:bg-neutral-secondary-medium">
                        <th scope="row" class="flex items-center px-6 py-4 text-heading whitespace-nowrap">
                            @if($user->photo)
                                <img class="w-10 h-10 rounded-full object-cover" src="{{ asset('storage/' . $user->photo) }}" alt="{{ $user->name }}">
                            @else
                                <img class="w-10 h-10 rounded-full object-cover" src="https://ui-avatars.com/api/?name={{ urlencode($user->name) }}&background=random" alt="{{ $user->name }}">
                            @endif
                            
                            <div class="ps-3">
                                <div class="text-base font-semibold">{{ $user->name }}</div>
                                <div class="font-normal text-body">{{ $user->email }}</div>
                            </div>  
                        </th>
                        <td class="px-6 py-4">
                            <a href="{{ route('admin.rekap-bonus.detail', $user->id) }}" wire:navigate class="underline text-blue-600 font-medium">Lihat Bonus</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-8 text-center text-body">
                            Belum ada data Kasir Gym.
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