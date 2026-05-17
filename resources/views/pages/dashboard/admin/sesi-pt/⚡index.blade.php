<?php

namespace App\Livewire\Pages\Dashboard\Admin\SesiPt;

use App\Models\User;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

new #[Layout('layouts::admin')] class extends Component
{
    public $search = '';

    #[Computed]
    public function ptUsers()
    {
        return User::where('role', 'pt')
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%');
            })
            ->orderBy('name')
            ->get();
    }
};
?>

<div>
    <div class="flex sm:flex-row flex-col justify-between items-center mb-6">
        <h5 class="text-xl font-semibold text-heading">Data Personal Trainer</h5>
    </div>

    @if (session()->has('success'))
        <div x-data="{ show: true }" x-show="show" x-transition.duration.300ms class="mb-6 flex items-center justify-between p-4 text-sm text-emerald-800 border border-emerald-200 rounded-md bg-emerald-50 shadow-xs">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                <span class="font-medium">{{ session('success') }}</span>
            </div>
            <button @click="show = false" type="button" class="text-emerald-600 hover:text-emerald-900 focus:outline-none">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
    @endif

    @if (session()->has('error'))
        <div x-data="{ show: true }" x-show="show" x-transition.duration.300ms class="mb-6 flex items-center justify-between p-4 text-sm text-red-800 border border-red-200 rounded-md bg-red-50 shadow-xs">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"></path></svg>
                <span class="font-medium">{{ session('error') }}</span>
            </div>
            <button @click="show = false" type="button" class="text-red-600 hover:text-red-900 focus:outline-none">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
    @endif

    <div class="bg-neutral-primary-soft shadow-xs rounded-md border border-default">
        <div class="p-4 flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="flex-1 w-full md:w-auto">
                <input type="text" wire:model.live.debounce.300ms="search" class="block w-full ps-9 pe-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs placeholder:text-body" placeholder="Cari nama PT...">
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left rtl:text-right text-body">
                <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-default-medium">
                    <tr>
                        <th scope="col" class="px-6 py-3 font-medium">No</th>
                        <th scope="col" class="px-6 py-3 font-medium">Nama PT</th>
                        <th scope="col" class="px-6 py-3 font-medium">Email</th>
                        <th scope="col" class="px-6 py-3 font-medium text-center">Telepon</th>
                        <th scope="col" class="px-6 py-3 font-medium text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->ptUsers as $pt)
                        <tr wire:key="pt-{{ $pt->id }}" class="bg-neutral-primary-soft border-b border-default hover:bg-neutral-secondary-medium">
                            <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                                {{ $loop->iteration }}
                            </td>
                            <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                                {{ $pt->name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                {{ $pt->email ?? '-' }}
                            </td>
                            <td class="px-6 py-4 text-center whitespace-nowrap">
                                {{ $pt->phone ?? '-' }}
                            </td>
                            <td class="px-6 py-4 text-center whitespace-nowrap">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="{{ route('admin.sesi-pt.detail', $pt->id) }}" wire:navigate class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-brand-700 bg-brand-50 border border-brand-200 rounded-md hover:bg-brand-100 focus:ring-2 focus:ring-brand-300 transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                        Detail
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                Belum ada data personal trainer.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
