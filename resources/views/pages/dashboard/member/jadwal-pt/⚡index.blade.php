<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use App\Models\Membership;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts::member')] class extends Component
{
    use WithPagination;

    public $search = '';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $userId = Auth::id();

        $query = Membership::with(['user', 'members', 'ptPackage', 'ptSchedule.days', 'personalTrainer'])
            ->whereNotNull('pt_package_id')
            ->where('status', '!=', 'completed')
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                  ->orWhereHas('members', function ($subQ) use ($userId) {
                      $subQ->where('users.id', $userId);
                  });
            });

        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->whereHas('user', function ($subQ) {
                    $subQ->where('name', 'like', '%'.$this->search.'%');
                })->orWhereHas('members', function ($subQ) {
                    $subQ->where('name', 'like', '%'.$this->search.'%');
                })->orWhereHas('personalTrainer', function ($subQ) {
                    $subQ->where('name', 'like', '%'.$this->search.'%');
                });
            });
        }

        $memberships = $query->orderBy('start_date', 'desc')
            ->paginate(10);

        return [
            'memberships' => $memberships,
        ];
    }
};
?>

<div class="max-w-6xl mx-auto p-4 sm:p-6 lg:p-8">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Jadwal PT Saya</h2>
            <p class="text-sm text-gray-500 mt-1">Daftar jadwal latihan personal training Anda.</p>
        </div>

        <div class="relative w-full sm:w-72">
            <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                <svg class="w-4 h-4 text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z"/>
                </svg>
            </div>
            <input 
                type="text" 
                wire:model.live.debounce.300ms="search" 
                class="block w-full ps-10 pe-3 py-2.5 bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 shadow-sm placeholder:text-gray-400" 
                placeholder="Cari nama member atau coach...">
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-600">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th scope="col" class="px-6 py-4 font-semibold">No</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Member</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Coach</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Paket</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Total Sesi</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Tanggal Mulai</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Masa Aktif</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Tipe Jadwal</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Jadwal</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($memberships as $membership)
                        @php $schedule = $membership->ptSchedule; @endphp
                        
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-500">
                                {{ $loop->iteration + ($memberships->currentPage() - 1) * $memberships->perPage() }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-col gap-1">
                                    @php
                                        $memberColors = ['text-blue-600', 'text-green-600', 'text-purple-600', 'text-orange-600', 'text-pink-600', 'text-teal-600', 'text-indigo-600', 'text-cyan-600', 'text-rose-600', 'text-amber-600'];
                                        $allMembers = collect([$membership->user])->merge($membership->members->where('id', '!=', $membership->user_id))->filter();
                                    @endphp
                                    @foreach($allMembers as $index => $member)
                                        <div class="text-sm font-medium {{ $memberColors[$index % count($memberColors)] }}">{{ $member->name }}</div>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm text-gray-700">{{ $membership->personalTrainer?->name ?? '-' }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize
                                    @if(($membership->ptPackage->category ?? '') === 'single') bg-indigo-100 text-indigo-800
                                    @elseif(($membership->ptPackage->category ?? '') === 'couple') bg-purple-100 text-purple-800
                                    @elseif(($membership->ptPackage->category ?? '') === 'group') bg-emerald-100 text-emerald-800
                                    @else bg-gray-100 text-gray-800
                                    @endif">
                                    {{ $membership->ptPackage->category ?? '-' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-700">
                                <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-blue-100 text-blue-800 text-sm font-bold">
                                    {{ $membership->total_sessions ?? 0 }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-700">
                                {{ $membership->start_date?->locale('id')->isoFormat('D MMM YYYY') ?? 'BELUM AKTIF' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-700">
                                {{ $membership->pt_end_date ? $membership->pt_end_date->locale('id')->isoFormat('D MMM YYYY') : 'BELUM AKTIF' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($schedule)
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium capitalize
                                        @if($schedule->type === 'keep') bg-amber-100 text-amber-800
                                        @else bg-blue-100 text-blue-800
                                        @endif">
                                        {{ $schedule->type }}
                                    </span>
                                @else
                                    <span class="text-xs text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if($schedule)
                                    @if($schedule->type === 'fleksibel')
                                        <span class="text-xs text-gray-500">Fleksibel</span>
                                    @else
                                        <div class="flex flex-nowrap items-center gap-x-2">
                                            @foreach($schedule->days as $day)
                                                <div class="flex items-center gap-1 px-2 py-1 bg-gray-50 rounded border border-gray-200 shrink-0">
                                                    <span class="text-xs font-semibold text-gray-700">{{ ucfirst($day->day) }}</span>
                                                    <span class="text-xs text-gray-500">{{ $day->time->format('H:i') }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                @else
                                    <span class="text-xs text-gray-400">Belum ada jadwal</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($schedule)
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium capitalize
                                        @if($schedule->status === 'approved') bg-green-100 text-green-800
                                        @elseif($schedule->status === 'pending') bg-yellow-100 text-yellow-800
                                        @elseif($schedule->status === 'rejected') bg-red-100 text-red-800
                                        @else bg-gray-100 text-gray-800
                                        @endif">
                                        {{ $schedule->status }}
                                    </span>
                                @else
                                    <span class="text-xs text-gray-400">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-6 py-10 text-center">
                                <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                <p class="text-gray-500 font-medium">Tidak ada data ditemukan.</p>
                                @if($search)
                                    <p class="text-xs text-gray-400 mt-1">Tidak ada member dengan nama "{{ $search }}".</p>
                                @else
                                    <p class="text-xs text-gray-400 mt-1">Jadwal akan muncul setelah Anda mendaftar paket PT.</p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($memberships->hasPages())
        <div class="mt-4">
            {{ $memberships->links('components.custom-pagination') }}
        </div>
    @endif
</div>
