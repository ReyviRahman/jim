<?php

namespace App\Livewire\Pages\Dashboard\Admin\SesiPt;

use App\Models\Membership;
use App\Models\PtBooking;
use App\Models\PtPaymentBatch;
use App\Models\PtPaymentBatchItem;
use App\Models\PtSessionCategory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

new #[Layout('layouts::admin')] class extends Component
{
    public User $user;

    public $dateStart = null;
    public $dateEnd = null;

    public bool $showModal = false;
    public ?int $editingId = null;

    public string $category = '';
    public string $amount = '';
    public string $description = '';

    public bool $showPaymentDetailModal = false;
    public ?int $selectedPaymentBatchId = null;

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

    private function getMembershipQuery()
    {
        return Membership::query()
            ->when($this->dateStart && $this->dateEnd, function ($query) {
                $query->whereDate('start_date', '<=', $this->dateEnd)
                    ->whereDate('pt_end_date', '>=', $this->dateStart);
            })
            ->with(['user', 'ptPackage', 'gymPackage', 'followUp', 'followUpTwo', 'members'])
            ->withCount([
                'ptBookings as berjalan' => function ($q) {
                    $q->where('attendance', 'attended')
                      ->where('is_free', false)
                      ->where('pt_id', $this->user->id);
                    if ($this->dateStart && $this->dateEnd) {
                        $q->whereBetween('booking_date', [
                            $this->dateStart . ' 00:00:00',
                            $this->dateEnd . ' 23:59:59',
                        ]);
                    }
                },
                'ptBookings as berjalan_dibayar' => function ($q) {
                    $q->where('attendance', 'attended')
                      ->where('is_free', false)
                      ->where('is_paid', true)
                      ->where('pt_id', $this->user->id);
                    if ($this->dateStart && $this->dateEnd) {
                        $q->whereBetween('booking_date', [
                            $this->dateStart . ' 00:00:00',
                            $this->dateEnd . ' 23:59:59',
                        ]);
                    }
                },
                'ptBookings as berjalan_belum_dibayar' => function ($q) {
                    $q->where('attendance', 'attended')
                      ->where('is_free', false)
                      ->where('is_paid', false)
                      ->where('pt_id', $this->user->id);
                    if ($this->dateStart && $this->dateEnd) {
                        $q->whereBetween('booking_date', [
                            $this->dateStart . ' 00:00:00',
                            $this->dateEnd . ' 23:59:59',
                        ]);
                    }
                },
                'ptBookings as hangus' => function ($q) {
                    $q->where('attendance', 'noshow')
                      ->where('pt_id', $this->user->id);
                    if ($this->dateStart && $this->dateEnd) {
                        $q->whereBetween('booking_date', [
                            $this->dateStart . ' 00:00:00',
                            $this->dateEnd . ' 23:59:59',
                        ]);
                    }
                },
                'ptBookings as bookings_before' => function ($q) {
                    $q->whereIn('attendance', ['attended', 'noshow'])
                      ->where('is_free', false);
                    if ($this->dateStart) {
                        $q->whereDate('booking_date', '<', $this->dateStart);
                    }
                },
                'ptBookings as free_total' => function ($q) {
                    $q->where('is_free', true)
                      ->where('pt_id', $this->user->id);
                },
                'ptBookings as free_berjalan' => function ($q) {
                    $q->where('is_free', true)
                      ->where('attendance', 'attended')
                      ->where('pt_id', $this->user->id);
                },
                'ptBookings as sesi_digantikan' => function ($q) {
                    $q->where('attendance', 'attended')
                      ->where('is_free', false)
                      ->where('pt_id', '!=', $this->user->id);
                    if ($this->dateStart && $this->dateEnd) {
                        $q->whereBetween('booking_date', [
                            $this->dateStart . ' 00:00:00',
                            $this->dateEnd . ' 23:59:59',
                        ]);
                    }
                },
            ]);
    }

    #[Computed]
    public function ptMembershipsDirect()
    {
        return $this->getMembershipQuery()
            ->where('pt_id', $this->user->id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    #[Computed]
    public function ptMembershipsBookingOnly()
    {
        return $this->getMembershipQuery()
            ->where('pt_id', '!=', $this->user->id)
            ->whereExists(function ($subQuery) {
                $subQuery->select(DB::raw(1))
                    ->from('pt_bookings')
                    ->whereColumn('pt_bookings.membership_id', 'memberships.id')
                    ->where('pt_bookings.pt_id', $this->user->id);
            })
            ->orderBy('created_at', 'desc')
            ->get();
    }

    #[Computed]
    public function ptSessionCategories()
    {
        return PtSessionCategory::where('pt_id', $this->user->id)
            ->latest()
            ->get();
    }

    #[Computed]
    public function ptPaymentBatches()
    {
        return PtPaymentBatch::where('pt_id', $this->user->id)
            ->with([
                'paidBy',
                'items.ptBooking.member',
                'items.ptBooking.membership.followUp',
                'items.ptBooking.membership.followUpTwo',
            ])
            ->latest()
            ->get();
    }

    public function paySessions(): void
    {
        $query = PtBooking::query()
            ->where('pt_id', $this->user->id)
            ->where('attendance', 'attended')
            ->where('is_free', false)
            ;

        if ($this->dateStart && $this->dateEnd) {
            $query->whereBetween('booking_date', [
                $this->dateStart . ' 00:00:00',
                $this->dateEnd . ' 23:59:59',
            ]);
        }

        $bookings = $query->with(['membership.followUp', 'membership.followUpTwo'])->get();

        if ($bookings->isEmpty()) {
            session()->flash('error', 'Tidak ada sesi berjalan dalam periode ini.');
            return;
        }

        $totalAmount = 0;
        foreach ($bookings as $booking) {
            $categoryLabel = $booking->membership->getPtCategoryLabel();
            $ptSessionCategory = $this->ptSessionCategories->firstWhere('category', $categoryLabel);
            $totalAmount += $ptSessionCategory?->amount ?? 0;
        }

        $batch = PtPaymentBatch::create([
            'pt_id' => $this->user->id,
            'date_start' => $this->dateStart,
            'date_end' => $this->dateEnd,

            'paid_by' => auth()->id(),
        ]);

        foreach ($bookings as $booking) {
            PtPaymentBatchItem::create([
                'pt_payment_batch_id' => $batch->id,
                'pt_booking_id' => $booking->id,
            ]);
            $booking->update(['is_paid' => true]);
        }

        session()->flash('success', 'Berhasil membayar ' . $bookings->count() . ' sesi PT.');
        $this->dispatch('scroll-to-history');
    }

    public function openPaymentDetailModal(int $id): void
    {
        $this->selectedPaymentBatchId = $id;
        $this->showPaymentDetailModal = true;
    }

    public function closePaymentDetailModal(): void
    {
        $this->showPaymentDetailModal = false;
        $this->selectedPaymentBatchId = null;
    }

    public function deletePaymentBatch(int $id): void
    {
        $batch = PtPaymentBatch::with('items.ptBooking')->findOrFail($id);

        foreach ($batch->items as $item) {
            $booking = $item->ptBooking;
            if ($booking) {
                $stillInOtherBatch = PtPaymentBatchItem::where('pt_booking_id', $booking->id)
                    ->where('pt_payment_batch_id', '!=', $batch->id)
                    ->exists();

                if (! $stillInOtherBatch) {
                    $booking->update(['is_paid' => false]);
                }
            }
        }

        $batch->delete();
        session()->flash('success', 'Riwayat pembayaran berhasil dihapus.');
    }

    private function terbilang(int $number): string
    {
        $angka = [
            0 => 'nol',
            1 => 'satu', 2 => 'dua', 3 => 'tiga', 4 => 'empat',
            5 => 'lima', 6 => 'enam', 7 => 'tujuh', 8 => 'delapan', 9 => 'sembilan',
            10 => 'sepuluh', 11 => 'sebelas', 12 => 'dua belas',
            13 => 'tiga belas', 14 => 'empat belas', 15 => 'lima belas',
            16 => 'enam belas', 17 => 'tujuh belas', 18 => 'delapan belas', 19 => 'sembilan belas',
            20 => 'dua puluh', 30 => 'tiga puluh', 40 => 'empat puluh',
            50 => 'lima puluh', 60 => 'enam puluh', 70 => 'tujuh puluh',
            80 => 'delapan puluh', 90 => 'sembilan puluh',
        ];

        if ($number < 0) {
            return 'minus ' . $this->terbilang(-$number);
        }

        if ($number < 21) {
            return $angka[$number];
        }

        if ($number < 100) {
            $puluh = floor($number / 10) * 10;
            $sisa = $number % 10;

            return $angka[$puluh] . ($sisa > 0 ? ' ' . $angka[$sisa] : '');
        }

        if ($number < 1000) {
            $ratus = floor($number / 100);
            $sisa = $number % 100;
            $prefix = $ratus === 1 ? 'seratus' : $angka[$ratus] . ' ratus';

            return $prefix . ($sisa > 0 ? ' ' . $this->terbilang($sisa) : '');
        }

        if ($number < 1000000) {
            $ribu = floor($number / 1000);
            $sisa = $number % 1000;
            $prefix = $ribu === 1 ? 'seribu' : $this->terbilang($ribu) . ' ribu';

            return $prefix . ($sisa > 0 ? ' ' . $this->terbilang($sisa) : '');
        }

        if ($number < 1000000000) {
            $juta = floor($number / 1000000);
            $sisa = $number % 1000000;

            return $this->terbilang($juta) . ' juta' . ($sisa > 0 ? ' ' . $this->terbilang($sisa) : '');
        }

        $miliar = floor($number / 1000000000);
        $sisa = $number % 1000000000;

        return $this->terbilang($miliar) . ' miliar' . ($sisa > 0 ? ' ' . $this->terbilang($sisa) : '');
    }

    public function openModal(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->category = '';
        $this->amount = '';
        $this->description = '';
    }

    public function save(): void
    {
        $validated = $this->validate([
            'category' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:1000',
        ]);

        if ($this->editingId) {
            $ptSessionCategory = PtSessionCategory::findOrFail($this->editingId);
            $ptSessionCategory->update($validated);
            session()->flash('success', 'Kategori sesi PT berhasil diperbarui.');
        } else {
            PtSessionCategory::create([
                'pt_id' => $this->user->id,
                'category' => $validated['category'],
                'amount' => $validated['amount'],
                'description' => $validated['description'],
            ]);
            session()->flash('success', 'Kategori sesi PT berhasil ditambahkan.');
        }

        $this->closeModal();
    }

    public function edit(int $id): void
    {
        $ptSessionCategory = PtSessionCategory::findOrFail($id);

        $this->editingId = $ptSessionCategory->id;
        $this->category = $ptSessionCategory->category;
        $this->amount = (string) $ptSessionCategory->amount;
        $this->description = $ptSessionCategory->description ?? '';
        $this->showModal = true;
    }

    public function delete(int $id): void
    {
        PtSessionCategory::findOrFail($id)->delete();
        session()->flash('success', 'Kategori sesi PT berhasil dihapus.');
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
                        <th scope="col" class="px-6 py-3 font-medium">Admin Follow Up</th>
                        <th scope="col" class="px-6 py-3 font-medium">Sales Follow Up</th>
                        <th scope="col" class="px-6 py-3 font-medium">Harga</th>
                        <th scope="col" class="px-6 py-3 font-medium text-center" colspan="2">Kategori</th>
                        <th scope="col" class="px-6 py-3 font-medium text-center">Sesi Awal</th>
                        <th scope="col" class="px-6 py-3 font-medium text-center">Sesi Ditambahkan</th>
                        <th scope="col" class="px-6 py-3 font-medium text-center">Total Sesi</th>
                        <th scope="col" class="px-6 py-3 font-medium text-center">Belum Bayar</th>
                        <th scope="col" class="px-6 py-3 font-medium text-center">Sudah Bayar</th>
                        <th scope="col" class="px-6 py-3 font-medium text-center">Hangus</th>
                        <th scope="col" class="px-6 py-3 font-medium text-center">Sesi Digantikan</th>
                        <th scope="col" class="px-6 py-3 font-medium text-center">Sisa Sesi</th>
                        <th scope="col" class="px-6 py-3 font-medium text-right">Nominal</th>
                        <th scope="col" class="px-6 py-3 font-medium text-right">Total</th>
                        <th scope="col" class="px-6 py-3 font-medium text-center">Free</th>
                        <th scope="col" class="px-6 py-3 font-medium text-center">Free Berjalan</th>
                        <th scope="col" class="px-6 py-3 font-medium text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $totalCategoryTotal = 0;
                        $rowNumber = 1;
                        $bookingOnlyWithSessions = $this->ptMembershipsBookingOnly->filter(function($m) {
                            return $m->berjalan > 0;
                        });
                    @endphp

                    {{-- Section 1: Direct PT Memberships --}}
                    @if($this->ptMembershipsDirect->count() > 0)
                        <tr class="bg-blue-50 border-b border-blue-200">
                            <td colspan="20" class="px-6 py-3 font-semibold text-blue-800 text-sm">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    Membership Langsung (PT Utama)
                                </div>
                            </td>
                        </tr>
                        @foreach($this->ptMembershipsDirect as $membership)
                            @php
                                $category = $membership->ptPackage->category ?? $membership->gymPackage->category ?? '-';
                                $bookingsBefore = $membership->bookings_before ?? 0;
                                $totalFromTable = ($membership->total_sessions ?? 0) + ($membership->sesi_ditambahkan ?? 0);

                                if ($bookingsBefore == 0) {
                                    $sesiAwal = ($membership->total_sessions ?? 0);
                                    $sesiDitambahkan = $membership->sesi_ditambahkan ?? 0;
                                } else {
                                    $sesiAwal = $totalFromTable - $bookingsBefore;
                                    $sesiDitambahkan = 0;
                                }

                                $totalSessions = $sesiAwal + $sesiDitambahkan;
                                $hangus = ($membership->hangus ?? 0) + ($membership->sesi_hangus ?? 0);
                                $sisaSesi = $sesiAwal + $sesiDitambahkan - $membership->berjalan - $hangus - $membership->sesi_digantikan;

                                $priceLabelData = $membership->getPriceLabel();

                                $categoryLabel = $membership->getPtCategoryLabel();
                                $ptSessionCategory = $this->ptSessionCategories->firstWhere('category', $categoryLabel);
                                $categoryNominal = $ptSessionCategory?->amount ?? 0;
                                $categoryTotal = $membership->berjalan * $categoryNominal;

                                $totalCategoryTotal += $categoryTotal;
                            @endphp
                            <tr wire:key="pt-membership-direct-{{ $membership->id }}" class="bg-neutral-primary-soft border-b border-default hover:bg-neutral-secondary-medium">
                                <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                                    {{ $rowNumber++ }}
                                </td>
                                <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                                    {{ $membership->user->name ?? '-' }}
                                    @if($membership->members && $membership->members->count() > 1)
                                        <div class="text-xs text-body font-normal mt-0.5">
                                            @foreach($membership->members->where('id', '!=', $membership->user_id) as $member)
                                                {{ $member->name }}@if(!$loop->last), @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ $membership->followUp->name ?? '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ $membership->followUpTwo->name ?? '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div>Rp {{ number_format($membership->price_paid ?? 0, 0, ',', '.') }}</div>
                                    @if($priceLabelData)
                                        <div class="mt-1">
                                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full {{ $priceLabelData['color'] }}">
                                                {{ $priceLabelData['label'] }}
                                            </span>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap capitalize">
                                    {{ $category }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap font-semibold text-heading">
                                    {{ $categoryLabel }}
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
                                <td class="px-6 py-4 text-center whitespace-nowrap font-semibold text-amber-600">
                                    {{ $membership->berjalan_belum_dibayar }}
                                </td>
                                <td class="px-6 py-4 text-center whitespace-nowrap font-semibold text-emerald-600">
                                    {{ $membership->berjalan_dibayar }}
                                </td>
                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    {{ $hangus }}
                                </td>
                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    {{ $membership->sesi_digantikan }}
                                </td>
                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    {{ $sisaSesi }}
                                </td>
                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    Rp {{ number_format($categoryNominal, 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    Rp {{ number_format($categoryTotal, 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    {{ $membership->free_total }}
                                </td>
                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    {{ $membership->free_berjalan }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <a href="{{ route('admin.sesi-pt.membership-detail', $membership->id) }}" wire:navigate class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium text-white bg-brand rounded hover:bg-brand-strong transition-colors">
                                        Detail
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    @endif

                    {{-- Section 2: Booking-Only Memberships --}}
                    @if($bookingOnlyWithSessions->count() > 0)
                        <tr class="bg-amber-50 border-b border-amber-200">
                            <td colspan="20" class="px-6 py-3 font-semibold text-amber-800 text-sm">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                    Membership Booking Saja (PT Pengganti/Cadangan)
                                </div>
                            </td>
                        </tr>
                        @foreach($bookingOnlyWithSessions as $membership)
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

                                $priceLabel = null;
                                $labelColor = '';
                                $pricePaid = $membership->price_paid;
                                $normalPrice = $membership->normal_price;
                                $basePrice = $membership->base_price;
                                $netPrice = $membership->net_price;
                                $unrecommendedPrice = $membership->unrecommended_price;

                                if ($unrecommendedPrice !== null && $pricePaid < $unrecommendedPrice) {
                                    $priceLabel = 'Harga Tidak Disarankan';
                                    $labelColor = 'bg-red-100 text-red-800';
                                } elseif ($netPrice !== null && $pricePaid < $netPrice) {
                                    $priceLabel = 'Harga Tidak Disarankan';
                                    $labelColor = 'bg-red-100 text-red-800';
                                } elseif (($normalPrice !== null && $pricePaid < $normalPrice) || ($basePrice !== null && $pricePaid < $basePrice)) {
                                    $priceLabel = 'Harga Net';
                                    $labelColor = 'bg-emerald-100 text-emerald-800';
                                } else {
                                    $priceLabel = 'Harga Normal';
                                    $labelColor = 'bg-blue-100 text-blue-800';
                                }

                                $categoryLabel = $membership->getPtCategoryLabel();
                                $ptSessionCategory = $this->ptSessionCategories->firstWhere('category', $categoryLabel);
                                $categoryNominal = $ptSessionCategory?->amount ?? 0;
                                $categoryTotal = $membership->berjalan * $categoryNominal;

                                $totalCategoryTotal += $categoryTotal;
                            @endphp
                            <tr wire:key="pt-membership-booking-{{ $membership->id }}" class="bg-neutral-primary-soft border-b border-default hover:bg-neutral-secondary-medium">
                                <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                                    {{ $rowNumber++ }}
                                </td>
                                <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                                    {{ $membership->user->name ?? '-' }}
                                    @if($membership->members && $membership->members->count() > 1)
                                        <div class="text-xs text-body font-normal mt-0.5">
                                            @foreach($membership->members->where('id', '!=', $membership->user_id) as $member)
                                                {{ $member->name }}@if(!$loop->last), @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ $membership->followUp->name ?? '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ $membership->followUpTwo->name ?? '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div>Rp {{ number_format($membership->price_paid ?? 0, 0, ',', '.') }}</div>
                                    @if($priceLabel)
                                        <div class="mt-1">
                                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full {{ $labelColor }}">
                                                {{ $priceLabel }}
                                            </span>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap capitalize">
                                    {{ $category }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap font-semibold text-heading">
                                    {{ $categoryLabel }}
                                </td>
                                <td class="px-6 py-4 text-center whitespace-nowrap text-gray-400">-</td>
                                <td class="px-6 py-4 text-center whitespace-nowrap text-gray-400">-</td>
                                <td class="px-6 py-4 text-center whitespace-nowrap text-gray-400">-</td>
                                <td class="px-6 py-4 text-center whitespace-nowrap font-semibold text-amber-600">
                                    {{ $membership->berjalan_belum_dibayar }}
                                </td>
                                <td class="px-6 py-4 text-center whitespace-nowrap font-semibold text-emerald-600">
                                    {{ $membership->berjalan_dibayar }}
                                </td>
                                <td class="px-6 py-4 text-center whitespace-nowrap text-gray-400">-</td>
                                <td class="px-6 py-4 text-center whitespace-nowrap text-gray-400">-</td>
                                <td class="px-6 py-4 text-center whitespace-nowrap text-gray-400">-</td>
                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    Rp {{ number_format($categoryNominal, 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    Rp {{ number_format($categoryTotal, 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    {{ $membership->free_total }}
                                </td>
                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    {{ $membership->free_berjalan }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <a href="{{ route('admin.sesi-pt.membership-detail', $membership->id) }}" wire:navigate class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium text-white bg-brand rounded hover:bg-brand-strong transition-colors">
                                        Detail
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    @endif

                    @if($this->ptMembershipsDirect->count() == 0 && $bookingOnlyWithSessions->count() == 0)
                        <tr>
                            <td colspan="20" class="px-6 py-8 text-center text-gray-500">
                                Belum ada data membership untuk PT ini.
                            </td>
                        </tr>
                    @endif
                </tbody>
                @if ($this->ptMembershipsDirect->count() > 0 || $bookingOnlyWithSessions->count() > 0)
                    <tfoot class="bg-neutral-secondary-medium font-semibold text-heading border-t-2 border-default-medium">
                        <tr>
                            <td colspan="15" class="px-6 py-4 text-right">Sub Total</td>
                            <td class="px-6 py-4 text-right whitespace-nowrap">Rp {{ number_format($totalCategoryTotal, 0, ',', '.') }}</td>
                            <td colspan="4" class="px-6 py-4 text-center whitespace-nowrap">
                                <button
                                    type="button"
                                    wire:click="paySessions"
                                    wire:confirm="Yakin ingin membayar semua sesi berjalan dalam periode ini? Sesi yang sudah dibayar akan dimasukkan ulang."
                                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-white bg-emerald-600 rounded hover:bg-emerald-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                    @if($this->ptMembershipsDirect->sum('berjalan') + $bookingOnlyWithSessions->sum('berjalan') <= 0) disabled @endif
                                >
                                    Bayar Sesi
                                </button>
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- Tabel Kategori Sesi PT --}}
    <div class="mt-8">
        <div class="flex sm:flex-row flex-col justify-between items-center mb-6">
            <h5 class="text-xl font-semibold text-heading">Kategori Sesi PT</h5>
            <button type="button" wire:click="openModal" class="text-white bg-brand box-border border border-transparent hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium shadow-xs font-medium leading-5 rounded-md text-sm px-4 py-2.5 focus:outline-none">
                + Tambah Kategori
            </button>
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

        <div class="bg-neutral-primary-soft shadow-xs rounded-md border border-default">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left rtl:text-right text-body">
                    <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-default-medium">
                        <tr>
                            <th scope="col" class="px-6 py-3 font-medium">No</th>
                            <th scope="col" class="px-6 py-3 font-medium">Kategori</th>
                            <th scope="col" class="px-6 py-3 font-medium text-right">Nominal</th>
                            <th scope="col" class="px-6 py-3 font-medium">Keterangan</th>
                            <th scope="col" class="px-6 py-3 font-medium text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->ptSessionCategories as $ptSessionCategory)
                            <tr wire:key="pt-session-category-{{ $ptSessionCategory->id }}" class="bg-neutral-primary-soft border-b border-default hover:bg-neutral-secondary-medium">
                                <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                                    {{ $loop->iteration }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ $ptSessionCategory->category }}
                                </td>
                                <td class="px-6 py-4 text-right whitespace-nowrap font-medium text-heading">
                                    Rp {{ number_format($ptSessionCategory->amount, 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ $ptSessionCategory->description ?? '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="flex items-center justify-center gap-3">
                                        <button type="button" wire:click="edit({{ $ptSessionCategory->id }})" class="text-blue-600 hover:text-blue-800 font-medium text-sm">
                                            Edit
                                        </button>
                                        <button type="button"
                                            wire:click="delete({{ $ptSessionCategory->id }})"
                                            wire:confirm="Apakah Anda yakin ingin menghapus kategori ini?"
                                            class="text-red-600 hover:text-red-800 font-medium text-sm">
                                            Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                    Belum ada data kategori sesi PT.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Riwayat Pembayaran Sesi PT --}}
    <div id="riwayat-pembayaran" class="mt-8"
         x-data="{}"
         x-init="() => {
             const handler = () => {
                 $el.scrollIntoView({ behavior: 'smooth', block: 'start' });
             };
             window.addEventListener('scroll-to-history', handler);
             return () => window.removeEventListener('scroll-to-history', handler);
         }">

        <div class="flex sm:flex-row flex-col justify-between items-center mb-6">
            <h5 class="text-xl font-semibold text-heading">Riwayat Pembayaran Sesi</h5>
        </div>

        @if (session()->has('error'))
            <div x-data="{ show: true }" x-show="show" x-transition.duration.300ms class="mb-6 flex items-center justify-between p-4 text-sm text-red-800 border border-red-200 rounded-md bg-red-50 shadow-xs">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span class="font-medium">{{ session('error') }}</span>
                </div>
                <button @click="show = false" type="button" class="text-red-600 hover:text-red-900 focus:outline-none">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
        @endif

        <div class="bg-neutral-primary-soft shadow-xs rounded-md border border-default">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left rtl:text-right text-body">
                    <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-default-medium">
                        <tr>
                            <th scope="col" class="px-6 py-3 font-medium">No</th>
                            <th scope="col" class="px-6 py-3 font-medium">ID Bayar</th>
                            <th scope="col" class="px-6 py-3 font-medium">Periode Tanggal</th>
                            <th scope="col" class="px-6 py-3 font-medium text-center">Jumlah Sesi</th>
                            <th scope="col" class="px-6 py-3 font-medium text-right">Total Nominal</th>
                            <th scope="col" class="px-6 py-3 font-medium">Dibayar Oleh</th>
                            <th scope="col" class="px-6 py-3 font-medium">Tanggal Bayar</th>
                            <th scope="col" class="px-6 py-3 font-medium text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->ptPaymentBatches as $batch)
                            <tr wire:key="pt-payment-batch-{{ $batch->id }}" class="bg-neutral-primary-soft border-b border-default hover:bg-neutral-secondary-medium">
                                <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                                    {{ $loop->iteration }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap font-semibold text-heading">
                                    #{{ $batch->id }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($batch->date_start && $batch->date_end)
                                        @if($batch->date_start->equalTo($batch->date_end))
                                            {{ $batch->date_start->translatedFormat('d F Y') }}
                                        @else
                                            {{ $batch->date_start->translatedFormat('d F Y') }} - {{ $batch->date_end->translatedFormat('d F Y') }}
                                        @endif
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    {{ $batch->items->count() }}
                                </td>
                                <td class="px-6 py-4 text-right whitespace-nowrap font-medium text-heading">
                                    @php
                                        $batchTotalAmount = 0;
                                        foreach ($batch->items as $item) {
                                            $categoryLabel = $item->ptBooking?->membership?->getPtCategoryLabel();
                                            $category = $this->ptSessionCategories->firstWhere('category', $categoryLabel);
                                            $batchTotalAmount += $category?->amount ?? 0;
                                        }
                                    @endphp
                                    Rp {{ number_format($batchTotalAmount, 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ $batch->paidBy?->name ?? '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ $batch->created_at->translatedFormat('d F Y H:i') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <button type="button" wire:click="openPaymentDetailModal({{ $batch->id }})" class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium text-white bg-brand rounded hover:bg-brand-strong transition-colors">
                                            Detail
                                        </button>
                                        <button type="button" wire:click="deletePaymentBatch({{ $batch->id }})" wire:confirm="Apakah Anda yakin ingin menghapus riwayat pembayaran ini?"
                                            class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium text-white bg-red-600 rounded hover:bg-red-700 transition-colors">
                                            Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                                    Belum ada riwayat pembayaran.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm" wire:click.self="closeModal">
            <div class="bg-white rounded-lg shadow-xl max-w-lg w-full mx-4">
                <div class="p-6 border-b border-default-medium flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-heading">
                        {{ $editingId ? 'Edit Kategori Sesi PT' : 'Tambah Kategori Sesi PT' }}
                    </h3>
                    <button type="button" wire:click="closeModal" class="text-body hover:text-heading">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="p-6 space-y-4">
                    <div>
                        <label for="category" class="block text-sm font-medium text-heading">Kategori</label>
                        <input type="text" wire:model="category" id="category" class="mt-1 block w-full rounded-md border-default-medium shadow-sm focus:border-brand focus:ring-brand sm:text-sm bg-neutral-secondary-medium px-3 py-2" placeholder="Masukkan kategori">
                        @error('category') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="amount" class="block text-sm font-medium text-heading">Nominal</label>
                        <input type="number" wire:model="amount" id="amount" class="mt-1 block w-full rounded-md border-default-medium shadow-sm focus:border-brand focus:ring-brand sm:text-sm bg-neutral-secondary-medium px-3 py-2" placeholder="Masukkan nominal">
                        @error('amount') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-medium text-heading">Keterangan</label>
                        <textarea wire:model="description" id="description" rows="3" class="mt-1 block w-full rounded-md border-default-medium shadow-sm focus:border-brand focus:ring-brand sm:text-sm bg-neutral-secondary-medium px-3 py-2" placeholder="Masukkan keterangan (opsional)"></textarea>
                        @error('description') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="p-6 border-t border-default-medium flex gap-3 justify-end">
                    <button type="button" wire:click="closeModal"
                        class="px-4 py-2 text-heading bg-neutral-secondary-medium border border-default-medium rounded-md hover:bg-neutral-secondary-strong font-medium text-sm">
                        Batal
                    </button>
                    <button type="button" wire:click="save"
                        class="px-4 py-2 text-white bg-brand hover:bg-brand-strong rounded-md font-medium text-sm">
                        Simpan
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if ($showPaymentDetailModal && $selectedPaymentBatchId)
        @php
            $selectedBatch = $this->ptPaymentBatches->firstWhere('id', $selectedPaymentBatchId);
            $batchCategories = $this->ptSessionCategories;
            $batchRows = [];
            $batchGrandTotalJumlah = 0;
            $batchGrandTotal = 0;

            foreach ($batchCategories as $category) {
                $jumlah = 0;
                foreach ($selectedBatch->items as $item) {
                    if ($item->ptBooking?->membership?->getPtCategoryLabel() === $category->category) {
                        $jumlah++;
                    }
                }
                if ($jumlah > 0) {
                    $total = $jumlah * $category->amount;
                    $batchRows[] = ['jenis' => $category->category, 'jumlah' => $jumlah, 'total' => $total];
                    $batchGrandTotalJumlah += $jumlah;
                    $batchGrandTotal += $total;
                }
            }
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm overflow-y-auto py-10" wire:click.self="closePaymentDetailModal">
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4">
                {{-- Header --}}
                <div class="flex items-center justify-between p-6 border-b-2 border-gray-300">
                    <div class="flex items-center gap-4 w-full">
                        <img src="{{ asset('icon.png') }}" alt="Icon" class="h-12 w-auto">
                        <h2 class="text-2xl font-bold text-heading text-center flex-1">DETAIL PEMBAYARAN SESI PT #{{ $selectedBatch?->id }}</h2>
                    </div>
                    <button type="button" wire:click="closePaymentDetailModal" class="text-body hover:text-heading ml-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                {{-- Body --}}
                <div class="p-6 space-y-4">
                    <div class="text-sm text-body">
                        @if($selectedBatch?->date_start && $selectedBatch?->date_end)
                            <p class="font-medium">
                                @if($selectedBatch->date_start->equalTo($selectedBatch->date_end))
                                    {{ $selectedBatch->date_start->translatedFormat('d F Y') }}
                                @else
                                    {{ $selectedBatch->date_start->translatedFormat('d F Y') }} - {{ $selectedBatch->date_end->translatedFormat('d F Y') }}
                                @endif
                            </p>
                        @endif
                        <p class="mt-1"><span class="font-semibold text-heading">NAMA:</span> {{ $user->name }}</p>
                    </div>

                    @if(count($batchRows) > 0)
                        <div class="mb-4">
                            <table class="w-full text-sm text-left text-body border border-default-medium">
                                <thead class="text-sm text-body bg-blue-50 border-b border-blue-200">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 font-medium">JENIS</th>
                                        <th scope="col" class="px-4 py-3 font-medium text-center">JUMLAH</th>
                                        <th scope="col" class="px-4 py-3 font-medium text-right">TOTAL</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($batchRows as $row)
                                        <tr class="bg-white border-b border-default-medium">
                                            <td class="px-4 py-3 whitespace-nowrap">{{ $row['jenis'] }}</td>
                                            <td class="px-4 py-3 text-center whitespace-nowrap">{{ $row['jumlah'] }}</td>
                                            <td class="px-4 py-3 text-right whitespace-nowrap">Rp {{ number_format($row['total'], 0, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-blue-50 font-semibold text-heading border-t border-blue-200">
                                    <tr>
                                        <td class="px-4 py-3">TOTAL</td>
                                        <td class="px-4 py-3 text-center">{{ $batchGrandTotalJumlah }}</td>
                                        <td class="px-4 py-3 text-right">Rp {{ number_format($batchGrandTotal, 0, ',', '.') }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @else
                        <p class="text-gray-500 text-center py-6">Belum ada data.</p>
                    @endif

                    <div class="border-t-2 border-default-medium pt-4 mt-4">
                        <div class="flex justify-between items-center font-bold text-heading">
                            <span>TOTAL KESELURUHAN</span>
                            <span>Rp {{ number_format($batchGrandTotal, 0, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="mt-4 space-y-1 text-sm">
                        <p class="font-bold text-heading text-base">BERSIH DITERIMA: Rp {{ number_format($batchGrandTotal, 0, ',', '.') }}</p>
                        <p class="text-body italic">Terbilang: {{ $this->terbilang($batchGrandTotal) }} rupiah</p>
                    </div>
                </div>

                {{-- Footer Actions --}}
                <div class="p-6 border-t border-default-medium flex gap-3 justify-end">
                    <a href="{{ route('admin.sesi-pt.payment-batch-print', $selectedPaymentBatchId) }}"
                        class="px-4 py-2 text-white bg-emerald-600 hover:bg-emerald-700 rounded-md font-medium text-sm">
                        Download PDF
                    </a>
                    <button type="button" wire:click="closePaymentDetailModal"
                        class="px-4 py-2 text-heading bg-neutral-secondary-medium border border-default-medium rounded-md hover:bg-neutral-secondary-strong font-medium text-sm">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
