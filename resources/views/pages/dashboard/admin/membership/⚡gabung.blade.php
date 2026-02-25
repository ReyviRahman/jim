<?php

namespace App\Livewire\Admin; // Sesuaikan namespace Anda

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed; 
use Livewire\WithPagination;
use App\Models\User;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination;

    public $search = '';
    
    // Properti untuk menyimpan ID user yang dicentang
    public array $selectedUsers = []; 

    public function updatedSearch()
    {
        $this->resetPage();
    }

    // Fungsi saat tombol "Lanjutkan Checkout" ditekan
    public function lanjutkanCheckout()
    {
        $jumlahTerpilih = count($this->selectedUsers);

        if ($jumlahTerpilih === 0) {
            session()->flash('error', 'Silakan pilih minimal 1 member untuk didaftarkan.');
            return;
        }

        // Bawa array ID user ke rute halaman pilih paket/checkout
        // Gunakan parameter array agar di URL menjadi ?users[0]=1&users[1]=2
        return $this->redirectRoute('admin.membership.paket', [
            'users' => $this->selectedUsers
        ], navigate: true);
    }

    #[Computed]
    public function users()
    {
        return User::where('is_active', true)
            ->where('role', 'member')
            ->whereDoesntHave('memberships', function ($query) {
                $query->whereIn('status', ['active', 'pending']);
            })
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
    @if (session()->has('error'))
        <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded text-sm">
            {{ session('error') }}
        </div>
    @endif
    @if (session()->has('success'))
        <div class="mb-4 p-3 bg-green-100 border border-green-400 text-green-700 rounded text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="relative overflow-x-auto bg-neutral-primary-soft shadow-xs rounded-base border border-default">
        <div class="flex items-center justify-between flex-column flex-wrap md:flex-row space-y-4 md:space-y-0 p-4">
            <div>
                <h5 class="text-xl font-semibold text-heading">Pilih Akun Member</h5>
                <p class="text-sm text-body mt-1">Pilih 1 orang (Single), 2 orang (Couple), atau 3 orang lebih (Group).</p>
            </div>
            
            <label for="table-search" class="sr-only">Search</label>
            <div class="relative">
                <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                    <svg class="w-4 h-4 text-body" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="m21 21-3.5-3.5M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z"/>
                    </svg>
                </div>
                <input type="text" id="table-search" wire:model.live="search" 
                    class="block w-full max-w-96 ps-9 pe-3 py-2 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs placeholder:text-body" 
                    placeholder="Cari nama atau email...">
            </div>
        </div>

        {{-- TOMBOL LANJUTKAN CHECKOUT (Muncul Dinamis jika ada yang dicentang) --}}
        @if(count($selectedUsers) > 0)
            <div class="bg-brand-soft border-t border-b border-brand-medium p-3 flex justify-between items-center px-4">
                <div class="text-sm font-medium text-brand-strong">
                    Terpilih: <span class="font-bold text-lg">{{ count($selectedUsers) }}</span> Member
                </div>
                <button wire:click="lanjutkanCheckout" class="text-white bg-brand hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium font-medium rounded-md text-sm px-5 py-2 transition-colors">
                    Lanjutkan ke Checkout &rarr;
                </button>
            </div>
        @endif

        <table class="w-full text-sm text-left rtl:text-right text-body">
            <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-t border-default-medium">
                <tr>
                    <th scope="col" class="px-6 py-3 font-medium w-4">
                        {{-- Dikosongkan untuk header checkbox --}}
                    </th>
                    <th scope="col" class="px-6 py-3 font-medium">Nama</th>
                    <th scope="col" class="px-6 py-3 font-medium">Pekerjaan</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->users as $user)
                    <tr wire:key="user-{{ $user->id }}" class="bg-neutral-primary-soft border-b border-default hover:bg-neutral-secondary-medium">
                        
                        {{-- KOLOM CHECKBOX --}}
                        <td class="px-6 py-4">
                            <div class="flex items-center">
                                <input id="checkbox-{{ $user->id }}" type="checkbox" value="{{ $user->id }}" wire:model.live="selectedUsers" 
                                    class="w-4 h-4 text-brand bg-gray-100 border-gray-300 rounded focus:ring-brand focus:ring-2 cursor-pointer">
                            </div>
                        </td>

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
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-6 py-8 text-center text-body">
                            Data user tidak ditemukan atau semua member sudah memiliki membership aktif.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        
        <div class="p-4 border-t border-default-medium">
            {{ $this->users->links('components.custom-pagination') }}
        </div>
    </div>
</div>