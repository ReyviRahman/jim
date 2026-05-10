<?php

namespace App\Livewire\Admin;

use App\Models\Membership;
use App\Models\PtSchedule;
use App\Models\PtScheduleDay;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination;

    public $search = '';

    public $filterPt = '';

    public $showScheduleModal = false;

    public $scheduleMembershipId = null;

    public $scheduleType = 'fleksibel';

    public $selectedDays = [];

    public $dayTimes = [];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingFilterPt()
    {
        $this->resetPage();
    }

    public function isHeadCoach(): bool
    {
        return Auth::user()?->role === 'head_coach';
    }

    public function openScheduleModal($membershipId)
    {
        $this->scheduleMembershipId = $membershipId;
        $this->scheduleType = 'fleksibel';
        $this->selectedDays = [];
        $this->dayTimes = [];

        $existing = PtSchedule::with('days')
            ->where('membership_id', $membershipId)
            ->first();

        if ($existing) {
            $this->scheduleType = $existing->type;

            foreach ($existing->days as $day) {
                $this->selectedDays[] = $day->day;
                $this->dayTimes[$day->day] = $day->time->format('H:i');
            }
        }

        $this->showScheduleModal = true;
    }

    public function closeScheduleModal()
    {
        $this->showScheduleModal = false;
        $this->scheduleMembershipId = null;
        $this->scheduleType = 'fleksibel';
        $this->selectedDays = [];
        $this->dayTimes = [];
    }

    public function saveSchedule()
    {
        $this->validate([
            'scheduleType' => 'required|in:fleksibel,keep',
        ]);

        if ($this->scheduleType === 'keep' && empty($this->selectedDays)) {
            $this->addError('selectedDays', 'Pilih minimal satu hari untuk jadwal Keep.');

            return;
        }

        $isHeadCoach = $this->isHeadCoach();

        $ptSchedule = PtSchedule::updateOrCreate(
            ['membership_id' => $this->scheduleMembershipId],
            [
                'type' => $this->scheduleType,
                'status' => $isHeadCoach ? 'approved' : 'pending',
                'created_by' => Auth::id(),
                'approved_by' => $isHeadCoach ? Auth::id() : null,
                'approved_at' => $isHeadCoach ? now() : null,
            ]
        );

        $ptSchedule->days()->delete();

        if ($this->scheduleType === 'keep') {
            foreach ($this->selectedDays as $day) {
                if (! empty($this->dayTimes[$day])) {
                    PtScheduleDay::create([
                        'pt_schedule_id' => $ptSchedule->id,
                        'day' => $day,
                        'time' => $this->dayTimes[$day],
                    ]);
                }
            }
        }

        $this->closeScheduleModal();
    }

    public function deleteSchedule($membershipId)
    {
        $schedule = PtSchedule::where('membership_id', $membershipId)->first();
        if ($schedule) {
            $schedule->delete();
        }
    }

    public function approveSchedule($membershipId)
    {
        if (! $this->isHeadCoach()) {
            return;
        }

        $schedule = PtSchedule::where('membership_id', $membershipId)->first();
        if ($schedule) {
            $schedule->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);
        }
    }

    public function rejectSchedule($membershipId)
    {
        if (! $this->isHeadCoach()) {
            return;
        }

        $schedule = PtSchedule::where('membership_id', $membershipId)->first();
        if ($schedule) {
            $schedule->update([
                'status' => 'rejected',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);
        }
    }

    #[Computed]
    public function ptList()
    {
        return User::where('role', 'pt')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function memberships()
    {
        $query = Membership::with(['user', 'members', 'personalTrainer', 'ptPackage', 'ptSchedule.days', 'ptSchedule.creator', 'ptSchedule.approver'])
            ->leftJoin('pt_schedules', 'memberships.id', '=', 'pt_schedules.membership_id')
            ->whereNotNull('pt_package_id')
            ->where('memberships.status', '!=', 'completed')
            ->select('memberships.*')
            ->orderByRaw("CASE WHEN pt_schedules.status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('memberships.start_date', 'desc');

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

        if (! empty($this->filterPt)) {
            $query->where('memberships.pt_id', $this->filterPt);
        }

        return $query->paginate(20);
    }
}; ?>

<div>
    <div class="flex sm:flex-row flex-col justify-between items-center mb-6">
        <h5 class="text-xl font-semibold text-heading">Jadwal PT</h5>
    </div>

    <div class="relative overflow-x-auto bg-neutral-primary-soft shadow-xs rounded-md border border-default">
        <div class="p-4 flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="relative w-full md:w-auto md:flex-1">
                <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                    <svg class="w-4 h-4 text-body" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="m21 21-3.5-3.5M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z"/></svg>
                </div>
                <input type="text" wire:model.live.debounce.300ms="search" class="block w-full max-w-sm ps-9 pe-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs placeholder:text-body" placeholder="Cari nama member atau coach...">
            </div>
            
            <div class="flex items-center gap-3 w-full md:w-auto">
                <select wire:model.live="filterPt" class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs block w-full md:w-48 ps-3 pe-8 py-2.5">
                    <option value="">Semua PT</option>
                    @foreach($this->ptList as $pt)
                        <option value="{{ $pt->id }}">{{ $pt->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <table class="w-full text-sm text-left rtl:text-right text-body">
            <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-default-medium">
                <tr>
                    <th scope="col" class="px-6 py-3 font-medium">No</th>
                    <th scope="col" class="px-6 py-3 font-medium">Tanggal Join</th>
                    <th scope="col" class="px-6 py-3 font-medium">Masa Aktif</th>
                    <th scope="col" class="px-6 py-3 font-medium">Sesi</th>
                    <th scope="col" class="px-6 py-3 font-medium">Coach</th>
                    <th scope="col" class="px-6 py-3 font-medium">Nama Member</th>
                    <th scope="col" class="px-6 py-3 font-medium">Tipe</th>
                    <th scope="col" class="px-6 py-3 font-medium">Jadwal</th>
                    <th scope="col" class="px-6 py-3 font-medium">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->memberships as $membership)
                    @php
                        $schedule = $membership->ptSchedule;
                    @endphp
                    <tr wire:key="{{ $membership->id }}" class="bg-neutral-primary-soft border-b border-default hover:bg-neutral-secondary-medium">
                        <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            {{ $loop->iteration + ($this->memberships->currentPage() - 1) * $this->memberships->perPage() }}
                        </td>
                        <td class="px-6 py-4 text-heading whitespace-nowrap">
                            {{ $membership->start_date?->locale('id')->isoFormat('D MMM YYYY') ?? 'BELUM AKTIF' }}
                        </td>
                        <td class="px-6 py-4 text-heading whitespace-nowrap">
                            {{ $membership->pt_end_date?->locale('id')->isoFormat('D MMM YYYY') ?? 'BELUM AKTIF' }}
                        </td>
                        <td class="px-6 py-4 text-heading whitespace-nowrap">
                            {{ $membership->total_sessions ?? 0 }} Sesi 
                            @php
                                $category = $membership->ptPackage->category ?? '-';
                                $display = $category === 'single' ? 'Personal' : $category;
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize
                                @if($category === 'single') bg-indigo-100 text-indigo-800
                                @elseif($category === 'couple') bg-purple-100 text-purple-800
                                @elseif($category === 'group') bg-emerald-100 text-emerald-800
                                @else bg-gray-100 text-gray-800
                                @endif">
                                {{ $display }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-heading whitespace-nowrap">
                            {{ $membership->personalTrainer?->name ?? '-' }}
                        </td>
                        <td class="px-6 py-4 font-medium">
                            @php
                                $memberColors = ['text-blue-600', 'text-green-600', 'text-purple-600', 'text-orange-600', 'text-pink-600', 'text-teal-600', 'text-indigo-600', 'text-cyan-600', 'text-rose-600', 'text-amber-600'];
                                $additionalMembers = $membership->members->filter(function($member) use ($membership) {
                                    return $member->id !== $membership->user_id;
                                });
                            @endphp
                            <div class="flex flex-col gap-1.5">
                                <div class="font-semibold text-heading">{{ $membership->user?->name ?? '-' }}</div>
                                @forelse($additionalMembers as $index => $member)
                                    <div class="{{ $memberColors[$index % count($memberColors)] }}">{{ $member->name }}</div>
                                @empty
                                @endforelse
                            </div>
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
                        <td class="px-6 py-4 text-heading whitespace-nowrap">
                            @if($schedule)
                                <div class="flex flex-nowrap items-center gap-x-1">
                                    @if($schedule->type === 'fleksibel')
                                        <span class="text-xs font-medium text-body">Fleksibel</span>
                                    @else
                                        @foreach($schedule->days as $index => $day)
                                            <span class="text-xs whitespace-nowrap flex items-center gap-x-1">
                                                <span class="font-medium text-body">{{ ucfirst($day->day) }}</span>
                                                <span class="font-semibold text-heading">{{ $day->time->format('H:i') }}</span>
                                            </span>
                                            @if(!$loop->last)
                                                <span class="text-gray-300 mx-0.5">|</span>
                                            @endif
                                        @endforeach
                                    @endif
                                    
                                    @if($schedule->status !== 'approved')
                                        <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium capitalize whitespace-nowrap
                                            @if($schedule->status === 'pending') bg-yellow-100 text-yellow-800
                                            @elseif($schedule->status === 'rejected') bg-red-100 text-red-800
                                            @endif">
                                            {{ $schedule->status }}
                                        </span>
                                    @endif
                                </div>
                            @else
                                <span class="text-xs text-gray-400">Belum ada jadwal</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center gap-2">
                                @if($schedule)
                                    @if($schedule->status === 'pending' && $this->isHeadCoach())
                                        <button wire:click="approveSchedule({{ $membership->id }})" wire:confirm="Yakin ingin menyetujui jadwal ini?" class="inline-flex items-center px-2 py-1 text-xs font-medium text-white bg-green-600 rounded hover:bg-green-700 transition-colors" title="Approve">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        </button>
                                        <button wire:click="rejectSchedule({{ $membership->id }})" wire:confirm="Yakin ingin menolak jadwal ini?" class="inline-flex items-center px-2 py-1 text-xs font-medium text-white bg-red-600 rounded hover:bg-red-700 transition-colors" title="Reject">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    @endif
                                    <button wire:click="openScheduleModal({{ $membership->id }})" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-white bg-brand rounded hover:bg-brand-dark transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                        Edit
                                    </button>
                                    <button wire:click="deleteSchedule({{ $membership->id }})" wire:confirm="Yakin ingin menghapus jadwal ini?" class="inline-flex items-center px-2 py-1.5 text-xs font-medium text-red-600 bg-red-50 rounded hover:bg-red-100 transition-colors" title="Hapus">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                @else
                                    <button wire:click="openScheduleModal({{ $membership->id }})" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-white bg-brand rounded hover:bg-brand-dark transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                        Jadwal
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-6 py-8 text-center text-gray-500">
                            Tidak ada data jadwal PT yang ditemukan.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $this->memberships->links() }}
    </div>

    @if($showScheduleModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" wire:click.self="closeScheduleModal">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between p-4 border-b border-gray-200 sticky top-0 bg-white">
                    <h3 class="text-lg font-semibold text-heading">
                        {{ $membership->ptSchedule ? 'Edit Jadwal' : 'Tambah Jadwal' }}
                    </h3>
                    <button wire:click="closeScheduleModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form wire:submit="saveSchedule" class="p-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-body mb-2">Tipe Jadwal</label>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 cursor-pointer px-4 py-2 rounded-lg border transition-colors
                                {{ $scheduleType === 'fleksibel' ? 'border-brand bg-brand/5' : 'border-default-medium' }}">
                                <input type="radio" wire:model.live="scheduleType" value="fleksibel" class="w-4 h-4 text-brand border-default-medium focus:ring-brand">
                                <span class="text-sm text-heading">Fleksibel</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer px-4 py-2 rounded-lg border transition-colors
                                {{ $scheduleType === 'keep' ? 'border-brand bg-brand/5' : 'border-default-medium' }}">
                                <input type="radio" wire:model.live="scheduleType" value="keep" class="w-4 h-4 text-brand border-default-medium focus:ring-brand">
                                <span class="text-sm text-heading">Keep</span>
                            </label>
                        </div>
                        @error('scheduleType') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>

                    @if($scheduleType === 'keep')
                        <div>
                            <label class="block text-sm font-medium text-body mb-2">Pilih Hari & Waktu</label>
                            <div class="space-y-2">
                                @foreach(['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu'] as $day)
                                    <div wire:key="day-{{ $day }}" 
                                         class="flex items-center gap-3 p-3 rounded-lg border transition-all
                                         {{ in_array($day, $selectedDays) ? 'border-brand bg-brand/5' : 'border-default-medium bg-neutral-secondary-medium/30' }}">
                                        <input type="checkbox" 
                                               wire:model.live="selectedDays" 
                                               value="{{ $day }}"
                                               id="day-{{ $day }}"
                                               class="w-4 h-4 text-brand border-default-medium rounded focus:ring-brand">
                                        <label for="day-{{ $day }}" class="text-sm text-heading w-20 font-medium">{{ ucfirst($day) }}</label>
                                        
                                        @if(in_array($day, $selectedDays))
                                            <input type="time" 
                                                   wire:model="dayTimes.{{ $day }}" 
                                                   class="bg-white border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand px-2 py-1">
                                        @else
                                            <span class="text-xs text-gray-400">-</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            @error('selectedDays') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    @if(!$this->isHeadCoach())
                        <div class="p-3 bg-yellow-50 border border-yellow-200 rounded text-sm text-yellow-800">
                            Jadwal akan diajukan untuk persetujuan Head Coach.
                        </div>
                    @endif

                    <div class="flex justify-end gap-3 pt-2 sticky bottom-0 bg-white pb-2">
                        <button type="button" wire:click="closeScheduleModal" class="px-4 py-2 text-sm font-medium text-body bg-neutral-secondary-medium border border-default-medium rounded hover:bg-neutral-secondary-dark transition-colors">
                            Batal
                        </button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-brand rounded hover:bg-brand-dark transition-colors">
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
