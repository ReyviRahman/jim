<?php

namespace App\Livewire\Pages\Dashboard\Admin\SesiPt;

use App\Models\Membership;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

new #[Layout('layouts::admin')] class extends Component
{
    public User $user;

    public $dateStart = null;
    public $dateEnd = null;

    public function mount(User $user)
    {
        $this->user = $user;
        $this->dateStart = Carbon::today()->toDateString();
        $this->dateEnd = Carbon::today()->toDateString();
    }

    public function setDateRange($rangeStr)
    {
        if (str_contains($rangeStr, ' to ')) {
            $dates = explode(' to ', $rangeStr);
            $this->dateStart = $dates[0];
            $this->dateEnd = $dates[1];
        } elseif ($rangeStr) {
            $this->dateStart = $rangeStr;
            $this->dateEnd = $rangeStr;
        } else {
            $this->dateStart = null;
            $this->dateEnd = null;
        }
    }

    #[Computed]
    public function ptMemberships()
    {
        return Membership::where('pt_id', $this->user->id)
            ->when($this->dateStart && $this->dateEnd, function ($query) {
                $query->whereDate('start_date', '<=', $this->dateEnd)
                    ->whereDate('pt_end_date', '>=', $this->dateStart);
            })
            ->with(['user', 'ptPackage', 'gymPackage'])
            ->withCount([
                'ptBookings as berjalan' => function ($q) {
                    $q->where('attendance', 'attended');
                    if ($this->dateStart && $this->dateEnd) {
                        $q->whereBetween('booking_date', [
                            $this->dateStart . ' 00:00:00',
                            $this->dateEnd . ' 23:59:59',
                        ]);
                    }
                },
                'ptBookings as hangus' => function ($q) {
                    $q->where('attendance', 'noshow');
                    if ($this->dateStart && $this->dateEnd) {
                        $q->whereBetween('booking_date', [
                            $this->dateStart . ' 00:00:00',
                            $this->dateEnd . ' 23:59:59',
                        ]);
                    }
                },
                'ptBookings as bookings_before' => function ($q) {
                    if ($this->dateStart) {
                        $q->whereDate('booking_date', '<', $this->dateStart);
                    }
                },
            ])
            ->orderBy('created_at', 'desc')
            ->get();
    }
};
?>

<div>
    <div class="mb-6">
        <a href="{{ route('admin.sesi-pt.index') }}" wire:navigate class="inline-flex items-center text-sm font-medium text-body hover:text-heading transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Kembali ke Data Personal Trainer
        </a>
    </div>

    <div class="flex sm:flex-row flex-col justify-between items-center mb-6">
        <h5 class="text-xl font-semibold text-heading">Detail Sesi PT: {{ $user->name }}</h5>
    </div>

    <div class="bg-neutral-primary-soft shadow-xs rounded-md border border-default">
        <div class="p-4 flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-3 w-full md:w-auto">
                <div class="w-full md:w-auto">
                    <div class="relative w-full md:w-56" wire:ignore>
                        <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                            <svg class="w-4 h-4 text-body" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 10h16M8 14h8m-4-7V4M7 7V4m10 3V4M5 20h14a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1Z"/></svg>
                        </div>
                        <input type="text" x-data
                            x-init="flatpickr($el, {
                                mode: 'range',
                                dateFormat: 'Y-m-d',
                                defaultDate: ['{{ $dateStart }}', '{{ $dateEnd }}'],
                                placeholder: 'Pilih Tanggal',
                                onClose: function(selectedDates, dateStr, instance) {
                                    @this.call('setDateRange', dateStr)
                                }
                            })"
                            class="block w-full ps-9 pe-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs placeholder:text-body"
                            placeholder="Pilih Rentang Tanggal">
                    </div>
                    @if($dateStart && $dateEnd)
                        <p class="mt-1.5 text-xs text-body">
                            @if($dateStart === $dateEnd)
                                {{ \Carbon\Carbon::parse($dateStart)->translatedFormat('d F Y') }}
                            @else
                                {{ \Carbon\Carbon::parse($dateStart)->translatedFormat('d F Y') }} - {{ \Carbon\Carbon::parse($dateEnd)->translatedFormat('d F Y') }}
                            @endif
                        </p>
                    @endif
                </div>


            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left rtl:text-right text-body">
                <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-default-medium">
                    <tr>
                        <th scope="col" class="px-6 py-3 font-medium">No</th>
                        <th scope="col" class="px-6 py-3 font-medium">Nama Member</th>
                        <th scope="col" class="px-6 py-3 font-medium">Harga</th>
                        <th scope="col" class="px-6 py-3 font-medium">Kategori</th>
                        <th scope="col" class="px-6 py-3 font-medium">SPR</th>
                        <th scope="col" class="px-6 py-3 font-medium text-center">Sesi Awal</th>
                        <th scope="col" class="px-6 py-3 font-medium text-center">Sesi Ditambahkan</th>
                        <th scope="col" class="px-6 py-3 font-medium text-center">Total Sesi</th>
                        <th scope="col" class="px-6 py-3 font-medium text-center">Berjalan</th>
                        <th scope="col" class="px-6 py-3 font-medium text-center">Hangus</th>
                        <th scope="col" class="px-6 py-3 font-medium text-center">Sisa Sesi</th>
                        <th scope="col" class="px-6 py-3 font-medium text-right">Nominal</th>
                        <th scope="col" class="px-6 py-3 font-medium text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->ptMemberships as $membership)
                        @php
                            $category = $membership->ptPackage->category ?? $membership->gymPackage->category ?? '-';
                            $bookingsBefore = $membership->bookings_before ?? 0;
                            $totalFromTable = ($membership->total_sessions ?? 0) + ($membership->sesi_ditambahkan ?? 0);

                            if ($bookingsBefore == 0) {
                                $sesiAwal = $membership->total_sessions ?? 0;
                                $sesiDitambahkan = $membership->sesi_ditambahkan ?? 0;
                            } else {
                                $sesiAwal = $totalFromTable - $bookingsBefore;
                                $sesiDitambahkan = 0;
                            }

                            $totalSessions = $sesiAwal + $sesiDitambahkan;
                            $hangus = ($membership->hangus ?? 0) + ($membership->sesi_hangus ?? 0);
                            $sisaSesi = $sesiAwal + $sesiDitambahkan - $membership->berjalan - $hangus;
                        @endphp
                        <tr wire:key="pt-membership-{{ $membership->id }}" class="bg-neutral-primary-soft border-b border-default hover:bg-neutral-secondary-medium">
                            <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                                {{ $loop->iteration }}
                            </td>
                            <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                                {{ $membership->user->name ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                Rp {{ number_format($membership->price_paid ?? 0, 0, ',', '.') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap capitalize">
                                {{ $category }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                -
                            </td>
                            <td class="px-6 py-4 text-center whitespace-nowrap">
                                {{ $sesiAwal }}
                            </td>
                            <td class="px-6 py-4 text-center whitespace-nowrap">
                                {{ $sesiDitambahkan }}
                            </td>
                            <td class="px-6 py-4 text-center whitespace-nowrap">
                                {{ $totalSessions }}
                            </td>
                            <td class="px-6 py-4 text-center whitespace-nowrap">
                                {{ $membership->berjalan }}
                            </td>
                            <td class="px-6 py-4 text-center whitespace-nowrap">
                                {{ $hangus }}
                            </td>
                            <td class="px-6 py-4 text-center whitespace-nowrap">
                                {{ $sisaSesi }}
                            </td>
                            <td class="px-6 py-4 text-right whitespace-nowrap">
                                Rp {{ number_format($membership->price_paid ?? 0, 0, ',', '.') }}
                            </td>
                            <td class="px-6 py-4 text-right whitespace-nowrap">
                                Rp {{ number_format($membership->total_paid ?? 0, 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="13" class="px-6 py-8 text-center text-gray-500">
                                Belum ada data membership untuk PT ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
