<?php

namespace App\Livewire\Pages\Dashboard\Admin\SesiPt;

use App\Models\Membership;
use App\Models\PtSessionCategory;
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

    public bool $showModal = false;
    public bool $showSlipModal = false;
    public ?int $editingId = null;

    public string $category = '';
    public string $amount = '';
    public string $description = '';

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
            ->with(['user', 'ptPackage', 'gymPackage', 'followUp', 'followUpTwo'])
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

    #[Computed]
    public function ptSessionCategories()
    {
        return PtSessionCategory::where('pt_id', $this->user->id)
            ->latest()
            ->get();
    }

    #[Computed]
    public function slipData()
    {
        $categories = $this->ptSessionCategories;
        $memberships = $this->ptMemberships;
        $rows = [];
        $grandTotalJumlah = 0;
        $grandTotal = 0;

        foreach ($categories as $category) {
            $jumlah = 0;
            $total = 0;

            foreach ($memberships as $membership) {
                if ($this->getPtCategoryLabel($membership) === $category->category) {
                    $jumlah += $membership->berjalan;
                    $total += $membership->berjalan * $category->amount;
                }
            }

            $rows[] = [
                'jenis' => $category->category,
                'jumlah' => $jumlah,
                'total' => $total,
            ];

            $grandTotalJumlah += $jumlah;
            $grandTotal += $total;
        }

        return [
            'rows' => $rows,
            'grandTotalJumlah' => $grandTotalJumlah,
            'grandTotal' => $grandTotal,
        ];
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

    public function openSlipModal(): void
    {
        $this->showSlipModal = true;
    }

    public function closeSlipModal(): void
    {
        $this->showSlipModal = false;
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

    private function getPtCategoryLabel(Membership $membership): string
    {
        $followUpRole = $membership->followUp?->role;
        $followUpTwoRole = $membership->followUpTwo?->role;

        if (($followUpRole !== null && $followUpRole !== 'pt') || ($followUpTwoRole !== null && $followUpTwoRole !== 'pt')) {
            return 'SLS';
        }

        $pricePaid = $membership->price_paid;
        $normalPrice = $membership->normal_price;
        $basePrice = $membership->base_price;
        $netPrice = $membership->net_price;
        $unrecommendedPrice = $membership->unrecommended_price;

        if ($unrecommendedPrice !== null && $pricePaid < $unrecommendedPrice) {
            return 'SPR';
        }

        if ($netPrice !== null && $pricePaid < $netPrice) {
            return 'SPR';
        }

        if (($normalPrice !== null && $pricePaid < $normalPrice) || ($basePrice !== null && $pricePaid < $basePrice)) {
            return 'IR';
        }

        return 'SDR';
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
            <button type="button" wire:click="openSlipModal" class="text-white bg-emerald-600 box-border border border-transparent hover:bg-emerald-700 focus:ring-4 focus:ring-emerald-300 shadow-xs font-medium leading-5 rounded-md text-sm px-4 py-2.5 focus:outline-none">
                Slip
            </button>
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
                        <th scope="col" class="px-6 py-3 font-medium text-center">Berjalan</th>
                        <th scope="col" class="px-6 py-3 font-medium text-center">Hangus</th>
                        <th scope="col" class="px-6 py-3 font-medium text-center">Sisa Sesi</th>
                        <th scope="col" class="px-6 py-3 font-medium text-right">Nominal</th>
                        <th scope="col" class="px-6 py-3 font-medium text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $totalCategoryTotal = 0;
                    @endphp
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

                            $categoryLabel = $this->getPtCategoryLabel($membership);
                            $ptSessionCategory = $this->ptSessionCategories->firstWhere('category', $categoryLabel);
                            $categoryNominal = $ptSessionCategory?->amount ?? 0;
                            $categoryTotal = $membership->berjalan * $categoryNominal;

                            $totalCategoryTotal += $categoryTotal;
                        @endphp
                        <tr wire:key="pt-membership-{{ $membership->id }}" class="bg-neutral-primary-soft border-b border-default hover:bg-neutral-secondary-medium">
                            <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                                {{ $loop->iteration }}
                            </td>
                            <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                                {{ $membership->user->name ?? '-' }}
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
                                Rp {{ number_format($categoryNominal, 0, ',', '.') }}
                            </td>
                            <td class="px-6 py-4 text-right whitespace-nowrap">
                                Rp {{ number_format($categoryTotal, 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="15" class="px-6 py-8 text-center text-gray-500">
                                Belum ada data membership untuk PT ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($this->ptMemberships->count() > 0)
                    <tfoot class="bg-neutral-secondary-medium font-semibold text-heading border-t-2 border-default-medium">
                        <tr>
                            <td colspan="14" class="px-6 py-4 text-right">Sub Total</td>
                            <td class="px-6 py-4 text-right whitespace-nowrap">Rp {{ number_format($totalCategoryTotal, 0, ',', '.') }}</td>
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

    @if ($showSlipModal)
        @php
            $slip = $this->slipData;
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm overflow-y-auto py-10" wire:click.self="closeSlipModal">
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4">
                {{-- Header --}}
                <div class="flex items-center justify-between p-6 border-b-2 border-gray-300">
                    <div class="flex items-center gap-4 w-full">
                        <img src="{{ asset('icon.png') }}" alt="Icon" class="h-12 w-auto">
                        <h2 class="text-2xl font-bold text-heading text-center flex-1">SLIP SESI PT</h2>
                    </div>
                    <button type="button" wire:click="closeSlipModal" class="text-body hover:text-heading ml-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                {{-- Body --}}
                <div class="p-6 space-y-4">
                    <div class="text-sm text-body">
                        @if($dateStart && $dateEnd)
                            <p class="font-medium">
                                @if($dateStart === $dateEnd)
                                    {{ \Carbon\Carbon::parse($dateStart)->translatedFormat('d F Y') }}
                                @else
                                    {{ \Carbon\Carbon::parse($dateStart)->translatedFormat('d F Y') }} - {{ \Carbon\Carbon::parse($dateEnd)->translatedFormat('d F Y') }}
                                @endif
                            </p>
                        @endif
                        <p class="mt-1"><span class="font-semibold text-heading">NAMA:</span> {{ $user->name }}</p>
                    </div>

                    <table class="w-full text-sm text-left text-body border border-default-medium">
                        <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-default-medium">
                            <tr>
                                <th scope="col" class="px-4 py-3 font-medium">JENIS</th>
                                <th scope="col" class="px-4 py-3 font-medium text-center">JUMLAH</th>
                                <th scope="col" class="px-4 py-3 font-medium text-right">TOTAL</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($slip['rows'] as $row)
                                <tr class="bg-white border-b border-default-medium">
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $row['jenis'] }}</td>
                                    <td class="px-4 py-3 text-center whitespace-nowrap">{{ $row['jumlah'] }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">Rp {{ number_format($row['total'], 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-6 text-center text-gray-500">Belum ada data.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="bg-neutral-secondary-medium font-semibold text-heading border-t-2 border-default-medium">
                            <tr>
                                <td class="px-4 py-3">TOTAL</td>
                                <td class="px-4 py-3 text-center">{{ $slip['grandTotalJumlah'] }}</td>
                                <td class="px-4 py-3 text-right">Rp {{ number_format($slip['grandTotal'], 0, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>

                    <div class="mt-4 space-y-1 text-sm">
                        <p class="font-bold text-heading text-base">BERSIH DITERIMA: Rp {{ number_format($slip['grandTotal'], 0, ',', '.') }}</p>
                        <p class="text-body italic">Terbilang: {{ $this->terbilang($slip['grandTotal']) }} rupiah</p>
                    </div>
                </div>

                {{-- Footer Actions --}}
                <div class="p-6 border-t border-default-medium flex gap-3 justify-end">
                    <a href="{{ route('admin.sesi-pt.slip-print', $user->id) }}?date_start={{ $dateStart }}&date_end={{ $dateEnd }}"
                        class="px-4 py-2 text-white bg-emerald-600 hover:bg-emerald-700 rounded-md font-medium text-sm">
                        Download PDF
                    </a>
                    <button type="button" wire:click="closeSlipModal"
                        class="px-4 py-2 text-heading bg-neutral-secondary-medium border border-default-medium rounded-md hover:bg-neutral-secondary-strong font-medium text-sm">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
