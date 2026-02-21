<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Membership;
use Livewire\WithPagination;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination;

    // Method untuk mengupdate status langsung dari tabel
    public function updateStatus($membershipId, $newStatus)
    {
        $membership = Membership::findOrFail($membershipId);
        
        // Validasi ekstra untuk memastikan hanya value ENUM yang valid yang bisa masuk
        if (in_array($newStatus, ['pending', 'active', 'rejected', 'completed'])) {
            $membership->update([
                'status' => $newStatus
            ]);
        }
    }

    public function with(): array
    {
        return [
            // Mengambil data membership beserta relasinya, diurutkan dari yang terbaru
            'memberships' => Membership::with(['user', 'personalTrainer', 'gymPackage'])
                ->latest()
                ->paginate(10),
        ];
    }
};
?>

<div>
    <div class="flex justify-between items-center mb-6">
        <h5 class="text-xl font-semibold text-heading">Membership</h5>
        
        <a href="{{ route('admin.packages.create') }}" wire:navigate class="text-white bg-brand box-border border border-transparent hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium shadow-xs font-medium leading-5 rounded-md text-sm px-4 py-2.5 focus:outline-none">+ Tambah Paket</a>
    </div>

    <div class="relative overflow-x-auto bg-neutral-primary-soft shadow-xs rounded-md border border-default">
        <table class="w-full text-sm text-left rtl:text-right text-body">
            <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-default-medium">
                <tr>
                    <th scope="col" class="px-6 py-3 font-medium">No</th>
                    <th scope="col" class="px-6 py-3 font-medium">Member</th>
                    <th scope="col" class="px-6 py-3 font-medium">Personal Trainer</th>
                    <th scope="col" class="px-6 py-3 font-medium">Paket Gym</th>
                    <th scope="col" class="px-6 py-3 font-medium">Sesi</th>
                    <th scope="col" class="px-6 py-3 font-medium">Harga</th>
                    <th scope="col" class="px-6 py-3 font-medium">Durasi</th>
                    <th scope="col" class="px-6 py-3 font-medium text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($memberships as $membership)
                    <tr wire:key="{{ $membership->id }}" class="bg-neutral-primary-soft border-b border-default hover:bg-neutral-secondary-medium">
                        <td scope="row" class="px-7 py-4 font-medium text-heading whitespace-nowrap">
                            {{ $loop->iteration + ($memberships->currentPage() - 1) * $memberships->perPage() }}
                        </td>
                        <td scope="row" class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            <div>{{ $membership->user->name ?? 'N/A' }}</div>
                            <div class="text-xs text-gray-500">Goal: {{ $membership->member_goal ?? '-' }}</div>
                        </td>
                        <td scope="row" class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            {{ $membership->personalTrainer->name ?? '-' }}
                        </td>
                        <td scope="row" class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            {{ $membership->gymPackage->name ?? '-' }}
                        </td>
                        <td scope="row" class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            <span class="font-semibold {{ $membership->remaining_sessions <= 2 ? 'text-red-600' : 'text-green-600' }}">
                                {{ $membership->remaining_sessions }}
                            </span> 
                            / {{ $membership->total_sessions }}
                        </td>
                        <td scope="row" class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            Rp {{ number_format($membership->price_paid, 0, ',', '.') }}
                        </td>
                        <td scope="row" class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            <div>{{ $membership->start_date ? $membership->start_date->format('d M Y') : '-' }}</div>
                            <div>s/d</div>
                            <div>{{ $membership->end_date ? $membership->end_date->format('d M Y') : '-' }}</div>
                        </td>

                        <td scope="row" class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            <select 
                                wire:change="updateStatus({{ $membership->id }}, $event.target.value)"
                                wire:loading.attr="disabled"
                                wire:target="updateStatus({{ $membership->id }})"
                                class="bg-white border border-gray-300 text-gray-900 text-sm rounded-md focus:ring-brand focus:border-brand block w-31 cursor-pointer shadow-sm disabled:opacity-50"
                            >
                                <option value="pending" {{ $membership->status === 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="active" {{ $membership->status === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="rejected" {{ $membership->status === 'rejected' ? 'selected' : '' }}>Rejected</option>
                                <option value="completed" {{ $membership->status === 'completed' ? 'selected' : '' }}>Completed</option>
                            </select>

                            <div wire:loading wire:target="updateStatus({{ $membership->id }})" class="absolute -bottom-2 left-0 w-full text-center">
                                <span class="text-xs text-brand-strong animate-pulse">Menyimpan...</span>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                            Belum ada data paket membership.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    <div class="mt-4">
        {{ $memberships->links() }}
    </div>
</div>