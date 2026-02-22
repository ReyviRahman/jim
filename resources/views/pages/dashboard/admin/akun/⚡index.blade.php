<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\User;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination; // Menggunakan trait pagination

    public $search = '';

    // Reset halaman ke 1 setiap kali user mengetik di pencarian
    public function updatedSearch()
    {
        $this->resetPage();
    }

    // Fungsi untuk mengubah status is_active user
    public function toggleStatus($userId)
    {
        $user = User::findOrFail($userId);
        $user->is_active = !$user->is_active;
        $user->save();

        // Opsional: Beri notifikasi sukses
        session()->flash('success', 'Status user berhasil diperbarui.');
    }

    // Mengirim data ke view
    public function with(): array
    {
        return [
            'users' => User::where('name', 'like', '%' . $this->search . '%')
                ->orWhere('email', 'like', '%' . $this->search . '%')
                ->latest()
                ->paginate(10) // Tampilkan 10 data per halaman
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
        <div class="flex items-center justify-between flex-column flex-wrap md:flex-row space-y-4 md:space-y-0 p-4">
            <div>
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

        <table class="w-full text-sm text-left rtl:text-right text-body">
            <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-t border-default-medium">
                <tr>
                    <th scope="col" class="px-6 py-3 font-medium">Nama</th>
                    <th scope="col" class="px-6 py-3 font-medium">Pekerjaan</th>
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
                        <td class="px-6 py-4">
                            {{ $user->occupation ?? '-' }}
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center">
                                <div class="h-2.5 w-2.5 rounded-full {{ $user->is_active ? 'bg-green-500' : 'bg-red-500' }} me-2"></div> 
                                {{ $user->is_active ? 'Aktif' : 'Tidak Aktif' }}
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            
                            <div x-data="{ open: false }" class="relative inline-block text-left">
                                <button @click="open = !open" @click.outside="open = false" type="button" class="inline-flex items-center p-2 text-sm font-medium text-center text-body bg-neutral-secondary-medium rounded-lg hover:bg-neutral-tertiary-medium focus:ring-4 focus:outline-none focus:ring-neutral-primary-medium">
                                    <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 16 3">
                                        <path d="M2 0a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Zm6.041 0a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM14 0a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Z"/>
                                    </svg>
                                </button>

                                <div x-show="open" style="display: none;" class="absolute right-0 z-10 w-44 mt-2 bg-neutral-primary-medium border border-default-medium rounded-base shadow-lg">
                                    <ul class="p-2 text-sm text-body font-medium">
                                        <li>
                                            <a href="{{ route('admin.akun.detail', $user->id) }}" wire:navigate class="inline-flex items-center w-full p-2 hover:bg-neutral-tertiary-medium hover:text-heading rounded">
                                                Lihat Detail
                                            </a>
                                        </li>
                                        <li>
                                            <button type="button" 
                                                wire:click="toggleStatus({{ $user->id }})" 
                                                @click="open = false"
                                                class="inline-flex items-center w-full p-2 hover:bg-neutral-tertiary-medium rounded {{ $user->is_active ? 'text-red-500 hover:text-red-600' : 'text-green-500 hover:text-green-600' }}">
                                                {{ $user->is_active ? 'Nonaktifkan Akun' : 'Aktifkan Akun' }}
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-4 text-center text-body">
                            Data user tidak ditemukan.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        {{ $users->links('components.custom-pagination') }}
    </div>
</div>