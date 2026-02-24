<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed; // Tambahkan ini
use Livewire\WithPagination;
use App\Models\User;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination;

    public $search = '';

    // Reset halaman ke 1 setiap kali user mengetik di pencarian
    public function updatedSearch()
    {
        $this->resetPage();
    }

    // Menggunakan Computed Property agar lebih efisien dan modern
    // Menggunakan Computed Property agar lebih efisien dan modern
    #[Computed]
    public function users()
    {
        return User::where('is_active', true)
            ->where('role', 'member')
            // ⬇️ TAMBAHKAN BLOK INI ⬇️
            // Kecualikan user yang memiliki membership dengan status 'active' atau 'pending'
            ->whereDoesntHave('memberships', function ($query) {
                $query->whereIn('status', ['active', 'pending']);
            })
            // ⬆️ ------------------ ⬆️
            ->where(function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%');
            })
            ->latest()
            ->paginate(10);
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
        <div class="flex items-center justify-between flex-column flex-wrap md:flex-row space-y-4 md:space-y-0 p-4">
            <div>
                <h5 class="text-xl font-semibold text-heading">Pilih Akun</h5>
            </div>
            
            <label for="table-search" class="sr-only">Search</label>
            <div class="relative">
                <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                    <svg class="w-4 h-4 text-body" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="m21 21-3.5-3.5M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z"/>
                    </svg>
                </div>
                {{-- Input pencarian tetap menggunakan wire:model.live --}}
                <input type="text" id="table-search" wire:model.live="search" 
                    class="block w-full max-w-96 ps-9 pe-3 py-2 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs placeholder:text-body" 
                    placeholder="Cari nama atau email...">
            </div>
        </div>

        <table class="w-full text-sm text-left rtl:text-right text-body">
            <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-t border-default-medium">
                <tr>
                    <th scope="col" class="px-6 py-3 font-medium">Nama</th>
                    <th scope="col" class="px-6 py-3 font-medium">Pekerjaan</th>
                    <th scope="col" class="px-6 py-3 font-medium text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                {{-- Ubah $users menjadi $this->users --}}
                @forelse ($this->users as $user)
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
                            {{ $user->occupation ?? '-' }}
                        </td>
                        <td class="px-6 py-4 text-center">
                            <a href="{{ route('admin.membership.paket', $user->id) }}" 
                            wire:navigate 
                            class="text-white bg-brand box-border border border-transparent hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium shadow-xs font-medium leading-5 rounded-base text-sm px-4 py-2.5 focus:outline-none">
                            Pilih Paket
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-6 py-4 text-center text-body">
                            Data user tidak ditemukan.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        
        {{-- Jangan lupa ubah juga di bagian pagination --}}
        {{ $this->users->links('components.custom-pagination') }}
    </div>
</div>