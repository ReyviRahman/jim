<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use App\Models\GymPackage;
use App\Models\Membership;

new #[Layout('layouts::member')] class extends Component
{
    #[Computed]
    public function memberships()
    {
        // Mengambil data membership milik user yang login beserta relasinya
        return Membership::with(['gymPackage', 'personalTrainer'])
            ->where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();
    }
    
};
?>

<div>
    <div class="flex justify-between items-center mb-6">
        <h5 class="text-xl font-semibold text-heading">Membership Saya</h5>
        
        <a href="{{ route('member.paket.index') }}" wire:navigate class="text-white bg-brand box-border border border-transparent hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium shadow-xs font-medium leading-5 rounded-md text-sm px-4 py-2.5 focus:outline-none">
            Beli Paket Baru
        </a>
    </div>

    <div class="relative overflow-x-auto bg-neutral-primary-soft shadow-xs rounded-md border border-default">
        <table class="w-full text-sm text-left rtl:text-right text-body">
            <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-default-medium">
                <tr>
                    <th scope="col" class="px-6 py-3 font-medium">No</th>
                    <th scope="col" class="px-6 py-3 font-medium">Nama Paket</th>
                    <th scope="col" class="px-6 py-3 font-medium">Personal Trainer</th>
                    <th scope="col" class="px-6 py-3 font-medium">Sesi (Sisa/Total)</th>
                    <th scope="col" class="px-6 py-3 font-medium">Masa Aktif</th>
                    <th scope="col" class="px-6 py-3 font-medium">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->memberships as $membership)
                    <tr class="bg-neutral-primary-soft border-b border-default hover:bg-neutral-secondary-medium">
                        <td class="px-7 py-4 font-medium text-heading whitespace-nowrap">
                            {{ $loop->iteration }}
                        </td>
                        <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            {{ $membership->gymPackage->name ?? 'Paket Tidak Tersedia' }}
                        </td>
                        <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            {{ $membership->personalTrainer->name ?? 'Tanpa PT' }}
                        </td>
                        <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            <span class="font-bold text-brand">{{ $membership->remaining_sessions }}</span> / {{ $membership->total_sessions }} Sesi
                        </td>
                        <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            {{ $membership->start_date ? $membership->start_date->format('d M Y') : '-' }} s/d <br>
                            {{ $membership->end_date ? $membership->end_date->format('d M Y') : '-' }}
                        </td>
                        <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            @if($membership->status === 'active')
                                <span class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded border border-green-400">Aktif</span>
                            @elseif($membership->status === 'expired')
                                <span class="bg-red-100 text-red-800 text-xs font-medium px-2.5 py-0.5 rounded border border-red-400">Expired</span>
                            @else
                                <span class="bg-gray-100 text-gray-800 text-xs font-medium px-2.5 py-0.5 rounded border border-gray-400">{{ ucfirst($membership->status) }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                            Anda belum memiliki paket membership saat ini.
                        </td>
                    </tr>
                @endforelse
                
            </tbody>
        </table>
    </div>
</div>