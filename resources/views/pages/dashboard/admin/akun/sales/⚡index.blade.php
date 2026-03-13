<?php

namespace App\Livewire\Admin; // Sesuaikan namespace jika berbeda

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
        session()->flash('success', "{$user->name} berhasil {$statusMessage}.");
    }

    public function with(): array
    {
        return [
            'users' => User::query()
                ->where('role', 'sales')
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
            <h5 class="text-xl font-semibold text-heading">Master Data Sales Gym</h5>
            
            <div class="flex sm:flex-row flex-col gap-4 items-center">
                <div>
                    {{-- Sesuaikan route ini dengan route untuk halaman create sales --}}
                    <a href="{{ route('admin.akun.sales.create') }}" wire:navigate class="text-white bg-brand box-border border border-transparent hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium shadow-xs font-medium leading-5 rounded-md text-sm px-4 py-2.5 focus:outline-none">+ Buat Sales</a>
                </div>
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
                    <th scope="col" class="px-6 py-3 font-medium">Nama Sales</th>
                    <th scope="col" class="px-6 py-3 font-medium">Status</th>
                    <th scope="col" class="px-6 py-3 font-medium text-center">Aksi</th>
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
                        
                        {{-- Cek status pegawai dari is_active --}}
                        <td class="px-6 py-4">
                            <div class="flex items-center">
                                @if($user->is_active)
                                    <div class="h-2.5 w-2.5 rounded-full bg-green-500 me-2"></div> Aktif
                                @else
                                    <div class="h-2.5 w-2.5 rounded-full bg-red-500 me-2"></div> Nonaktif
                                @endif
                            </div>
                        </td>
                        
                        <td class="px-6 py-4">
                            <div class="flex justify-center items-center gap-3">
                                {{-- Tombol Edit --}}
                                <a href="{{ route('admin.akun.sales.edit', $user->id) }}" wire:navigate title="Edit Akun" class="font-medium text-blue-600 hover:text-blue-800 hover:underline flex justify-center items-center">
                                    <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                    </svg>
                                </a>

                                {{-- Tombol Toggle Aktif/Nonaktif --}}
                                <button type="button" 
                                    wire:click="toggleStatus({{ $user->id }})"
                                    wire:confirm="Yakin ingin {{ $user->is_active ? 'menonaktifkan' : 'mengaktifkan' }} akun {{ $user->name }}?"
                                    title="{{ $user->is_active ? 'Nonaktifkan Akun' : 'Aktifkan Akun' }}"
                                    class="font-medium focus:outline-none transition-colors {{ $user->is_active ? 'text-red-500 hover:text-red-700' : 'text-green-500 hover:text-green-700' }}">
                                    
                                    @if($user->is_active)
                                        {{-- Ikon Blokir (Banned) --}}
                                        <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                        </svg>
                                    @else
                                        {{-- Ikon Centang (Check) --}}
                                        <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    @endif
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-8 text-center text-body">
                            Belum ada data Sales.
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