<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination;

    public $search = '';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    #[Computed]
    public function members()
    {
        return User::where('role', 'member')
            ->whereDoesntHave('memberships', function ($query) {
                $query->whereIn('status', ['active', 'pending']);
            })
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%')
                      ->orWhere('phone', 'like', '%' . $this->search . '%');
                });
            })
            ->latest()
            ->paginate(20);
    }
};
?>

<div>
    <div class="flex sm:flex-row flex-col justify-between items-center mb-6">
        <h5 class="text-xl font-semibold text-heading">Member Tanpa Membership</h5>
    </div>

    @if (session()->has('success'))
        <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50" role="alert">
            <span class="font-medium">Sukses!</span> {{ session('success') }}
        </div>
    @endif

    <div class="relative overflow-x-auto bg-neutral-primary-soft shadow-xs rounded-md border border-default mb-6">
        <div class="p-4 flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="relative w-full md:w-auto md:flex-1">
                <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                    <svg class="w-4 h-4 text-body" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="m21 21-3.5-3.5M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z"/></svg>
                </div>
                <input type="text" wire:model.live.debounce.300ms="search" class="block w-full max-w-sm ps-9 pe-3 py-2.5 bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand shadow-xs placeholder:text-body" placeholder="Cari nama, email, atau no. hp...">
            </div>
        </div>

        <table class="w-full text-sm text-left rtl:text-right text-body">
            <thead class="text-xs text-body bg-neutral-secondary-medium border-b border-default-medium">
                <tr>
                    <th class="px-6 py-3 font-medium">No</th>
                    <th class="px-6 py-3 font-medium">Nama</th>
                    <th class="px-6 py-3 font-medium">Email</th>
                    <th class="px-6 py-3 font-medium">No. HP</th>
                    <th class="px-6 py-3 font-medium">Jenis Kelamin</th>
                    <th class="px-6 py-3 font-medium text-center">Tgl Daftar Akun</th>
                    {{-- <th class="px-6 py-3 font-medium text-right">Aksi</th> --}}
                </tr>
            </thead>
            <tbody>
                @forelse ($this->members as $index => $user)
                    <tr wire:key="{{ $user->id }}" class="bg-white border-b border-gray-100 hover:bg-gray-50">
                        <td class="px-6 py-4">{{ $loop->iteration + ($this->members->currentPage() - 1) * $this->members->perPage() }}</td>
                        <td class="px-6 py-4 font-bold text-gray-800 whitespace-nowrap">
                            <div class="flex items-center gap-3">
                                @if($user->photo)
                                    <img class="w-8 h-8 rounded-full object-cover" src="{{ asset('storage/' . $user->photo) }}" alt="{{ $user->name }}">
                                @else
                                    <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-xs font-bold text-gray-500">
                                        {{ strtoupper(substr($user->name, 0, 2)) }}
                                    </div>
                                @endif
                                {{ $user->name }}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">{{ $user->email ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">{{ $user->phone ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap capitalize">{{ $user->gender ?? '-' }}</td>
                        <td class="px-6 py-4 text-center whitespace-nowrap">
                            {{ $user->created_at ? \Carbon\Carbon::parse($user->created_at)->format('d M Y') : '-' }}
                        </td>
                        {{-- <td class="px-6 py-4 text-right whitespace-nowrap">
                            <a href="{{ route('admin.membership.paket', ['users' => [$user->id]]) }}" wire:navigate class="inline-flex items-center text-white bg-brand hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium font-medium rounded-md text-xs px-3 py-2 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                                Daftarkan
                            </a>
                        </td> --}}
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                            Tidak ada member yang tanpa membership aktif atau pending.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mb-6">
        {{ $this->members->links() }}
    </div>
</div>
