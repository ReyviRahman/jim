<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination;

    public $search = '';
    public $filterTime = 'today';
    public $dateStart;
    public $dateEnd;
    
    public $perPage = '50';
    public $shift;

    public function mount()
    {
        $user = Auth::user();
        // Cek apakah user sudah login untuk menghindari error
        if ($user) {
            // Jika role admin, set ke 'all'
            if ($user->role === 'admin') {
                $this->shift = 'all';
            } else {
                // Jika bukan admin, ambil dari kolom shift milik user tersebut di database
                // Asumsi di database kamu ada kolom bernama 'shift' (berisi 'pagi' atau 'siang')
                $this->shift = $user->shift; 
            }
        } else {
            // Fallback default jika user belum login (opsional, sesuaikan kebutuhan)
            $this->shift = 'pagi';
        }
    }

    public function setFilterTime($val)
    {
        $this->filterTime = $val;
        $this->resetPage();
    }

    public function setDateRange($dateStr)
    {
        if (empty($dateStr)) return; 

        $dates = explode(' to ', $dateStr);
        if (count($dates) === 2) {
            $this->dateStart = $dates[0];
            $this->dateEnd = $dates[1];
        } elseif (count($dates) === 1) {
            $this->dateStart = $dates[0];
            $this->dateEnd = $dates[0];
        }

        $this->filterTime = 'custom';
        $this->resetPage();
    }

    public function updatedPerPage() { $this->resetPage(); }
    public function updatedShift() { $this->resetPage(); }

    private function getBaseQuery()
    {
        $query = Expense::with(['admin']);

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->whereHas('admin', function ($subQ) {
                    $subQ->where('name', 'like', '%' . $this->search . '%');
                })
                ->orWhere('description', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->filterTime === 'today') {
            $query->whereDate('expense_date', today());
        } elseif ($this->filterTime === 'week') {
            $query->whereBetween('expense_date', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($this->filterTime === 'month') {
            $query->whereMonth('expense_date', now()->month)
                  ->whereYear('expense_date', now()->year);
        } elseif ($this->filterTime === 'custom' && $this->dateStart && $this->dateEnd) {
            $query->whereBetween('expense_date', [
                $this->dateStart . ' 00:00:00', 
                $this->dateEnd . ' 23:59:59'
            ]);
        }

        if ($this->shift === 'pagi') {
            $query->whereHas('admin', function ($q) {
                $q->where('shift', 'Pagi');
            });
        } elseif ($this->shift === 'siang') {
            $query->whereHas('admin', function ($q) {
                $q->where('shift', 'Siang');
            });
        }

        return $query;
    }

    #[Computed]
    public function expenses()
    {
        $query = $this->getBaseQuery();
        $limit = $this->perPage === 'all' ? 10000 : $this->perPage;
        
        return $query->latest('expense_date')->paginate($limit);
    }

    /**
     * FUNGSI BARU: Untuk menghapus data pengeluaran
     */
    public function delete($id)
    {
        // 1. Keamanan Ganda: Pastikan method ini hanya bisa dijalankan oleh admin
        // Sesuaikan 'role' dengan kolom hak akses di database Anda (misal: 'is_admin' == 1)
        if (auth()->user()->role !== 'admin') {
            abort(403, 'Akses ditolak. Anda tidak memiliki izin untuk menghapus data.');
        }

        // 2. Cari data dan hapus
        $expense = Expense::find($id);
        if ($expense) {
            $expense->delete();
            
            // Opsional: Kirim notifikasi sukses ke tampilan
            session()->flash('success', 'Data pengeluaran berhasil dihapus.');
        }
    }
};
?>

<div>
    <div class="flex sm:flex-row flex-col justify-between items-center mb-6">
        <h5 class="text-xl font-semibold text-heading">
            Riwayat Pengeluaran Shift {{ $this->shift === 'all' ? 'Pagi & Siang' : ucfirst($this->shift) }}
        </h5>
        <div class="flex gap-2">
            <div>
                    {{-- Sesuaikan route ini dengan route untuk halaman create kasir --}}
                    <a href="{{ route('admin.pengeluaran.create') }}" wire:navigate class="text-white bg-brand box-border border border-transparent hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium shadow-xs font-medium leading-5 rounded-md text-sm px-4 py-2.5 focus:outline-none">+ Buat Pengeluaran</a>
                </div>
        </div>
    </div>

    @if (session()->has('success'))
        <div x-data="{ show: true }" x-show="show" x-transition.duration.300ms class="mb-6 flex items-center justify-between p-4 text-sm text-emerald-800 border border-emerald-200 rounded-md bg-emerald-50 shadow-xs">
            <div class="flex items-center gap-2">
                {{-- Ikon Ceklis --}}
                <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                <span class="font-medium">{{ session('success') }}</span>
            </div>
            {{-- Tombol Tutup (Silang) --}}
            <button @click="show = false" type="button" class="text-emerald-600 hover:text-emerald-900 focus:outline-none">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
    @endif

    {{-- Filter Bar --}}
    <div class="relative overflow-x-auto bg-neutral-primary-soft shadow-xs rounded-md border border-default mb-6">
        <div class="p-4 flex flex-col lg:flex-row items-center justify-between gap-4">
            
            {{-- Search --}}
            <div class="relative w-full lg:w-auto flex-1">
                <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                    <svg class="w-4 h-4 text-body" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="m21 21-3.5-3.5M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z"/></svg>
                </div>
                {{-- Update placeholder pencarian --}}
                <input type="text" wire:model.live.debounce.300ms="search" class="block w-full ps-9 pe-3 py-2.5 bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand shadow-xs" placeholder="Cari nama admin atau keterangan...">
            </div>
            
        </div>
        <div class="flex flex-wrap items-center gap-3 w-full lg:w-auto px-4 pb-4">
                
                {{-- Dropdown Per Page --}}
                <select wire:model.live="perPage" class="bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand pr-6 py-2.5 shadow-xs">
                    <option value="10">10 Baris</option>
                    <option value="50">50 Baris</option>
                    <option value="all">Semua</option>
                </select>

                {{-- Dropdown Shift --}}
                <select wire:model.live="shift" class="bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand px-3 py-2.5 shadow-xs pr-6">
                    <option value="all">Semua Shift</option>
                    <option value="pagi">Shift Pagi</option>
                    <option value="siang">Shift Siang</option>
                </select>

                {{-- Datepicker Custom --}}
                <div class="relative w-full sm:w-56" wire:ignore>
                    <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                        <svg class="w-4 h-4 text-body" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 10h16M8 14h8m-4-7V4M7 7V4m10 3V4M5 20h14a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1Z"/></svg>
                    </div>
                    <input type="text" x-data x-init="flatpickr($el, { mode: 'range', dateFormat: 'Y-m-d', placeholder: 'Pilih Tanggal', onClose: function(selectedDates, dateStr) { @this.call('setDateRange', dateStr) } })" class="block w-full ps-9 pe-3 py-2.5 bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand shadow-xs" placeholder="Pilih Rentang Tanggal">
                </div>

                {{-- Filter Presets --}}
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" @click.outside="open = false" class="inline-flex items-center justify-center text-body bg-white border border-default-medium hover:bg-gray-50 focus:ring-4 focus:ring-gray-100 shadow-xs font-medium rounded-md text-sm px-3 py-2.5" type="button">
                        <svg class="w-4 h-4 me-1.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M18.796 4H5.204a1 1 0 0 0-.753 1.659l5.302 6.058a1 1 0 0 1 .247.659v4.874a.5.5 0 0 0 .2.4l3 2.25a.5.5 0 0 0 .8-.4v-7.124a1 1 0 0 1 .247-.659l5.302-6.059c.566-.646.106-1.658-.753-1.658Z"/></svg>
                        @if($filterTime === 'today') Hari Ini
                        @elseif($filterTime === 'week') Minggu Ini
                        @elseif($filterTime === 'month') Bulan Ini
                        @elseif($filterTime === 'custom') Kustom
                        @else Semua Waktu @endif
                        <svg class="w-4 h-4 ms-1.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 9-7 7-7-7"/></svg>
                    </button>
                    
                    <div x-show="open" style="display: none;" class="absolute right-0 z-50 mt-2 bg-white border border-gray-200 rounded-md shadow-lg w-40">
                        <ul class="p-2 text-sm text-gray-700 font-medium">
                            <li><button type="button" wire:click="setFilterTime('all')" @click="open = false" class="w-full text-left p-2 hover:bg-gray-100 rounded">Semua Waktu</button></li>
                            <li><button type="button" wire:click="setFilterTime('today')" @click="open = false" class="w-full text-left p-2 hover:bg-gray-100 rounded">Hari ini</button></li>
                            <li><button type="button" wire:click="setFilterTime('week')" @click="open = false" class="w-full text-left p-2 hover:bg-gray-100 rounded">Minggu ini</button></li>
                            <li><button type="button" wire:click="setFilterTime('month')" @click="open = false" class="w-full text-left p-2 hover:bg-gray-100 rounded">Bulan ini</button></li>
                        </ul>
                    </div>
                </div>
            </div>
        
        <table class="w-full text-sm text-left rtl:text-right text-body">
            <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-default-medium">
                <tr>
                    <th class="px-6 py-3 font-medium">No</th>
                    <th class="px-6 py-3 font-medium">Tanggal</th>
                    <th class="px-6 py-3 font-medium">Keterangan</th>
                    <th class="px-6 py-3 font-medium text-right">Nominal</th>
                    <th class="px-6 py-3 font-medium">Admin</th>
                    
                    {{-- Tampilkan Header "Aksi" HANYA JIKA user adalah admin --}}
                    @if(auth()->check() && auth()->user()->role === 'admin')
                        <th class="px-6 py-3 font-medium text-center">Aksi</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($this->expenses as $expense)
                    <tr wire:key="{{ $expense->id }}" class="bg-white border-b border-gray-100 hover:bg-gray-50">
                        <td class="px-6 py-4">{{ $loop->iteration + ($this->expenses->currentPage() - 1) * $this->expenses->perPage() }}</td>
                        
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="font-medium">{{ $expense->expense_date ? \Carbon\Carbon::parse($expense->expense_date)->format('d M Y') : '-' }}</div>
                        </td>
                        
                        <td class="px-6 py-4">
                            <div class="text-gray-800">{{ $expense->description }}</div>
                        </td>
                        
                        <td class="px-6 py-4 text-right font-bold text-red-600 whitespace-nowrap">
                            Rp {{ number_format($expense->amount, 0, ',', '.') }}
                        </td>
                        
                        <td class="px-6 py-4 whitespace-nowrap">
                            {{ $expense->admin->name ?? '-' }}
                        </td>

                        {{-- Tampilkan Tombol Edit & Delete HANYA JIKA user adalah admin --}}
                        @if(auth()->check() && auth()->user()->role === 'admin')
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="flex items-center justify-center gap-3">
                                    {{-- Tombol Edit: Arahkan ke halaman edit. Sesuaikan nama route-nya --}}
                                    <a href="{{ route('admin.pengeluaran.edit', $expense->id) }}" wire:navigate class="text-blue-600 hover:text-blue-800 font-medium">
                                        Edit
                                    </a>
                                    
                                    {{-- Tombol Hapus: Panggil fungsi delete() dengan konfirmasi --}}
                                    <button type="button" 
                                            wire:click="delete({{ $expense->id }})" 
                                            wire:confirm="Apakah Anda yakin ingin menghapus data pengeluaran ini secara permanen?"
                                            class="text-red-600 hover:text-red-800 font-medium">
                                        Hapus
                                    </button>
                                </div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        {{-- Sesuaikan colspan jadi 6 agar pesan kosongnya tetap rapi di tengah --}}
                        <td colspan="6" class="px-6 py-8 text-center text-gray-500">Belum ada riwayat pengeluaran.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    <div class="mb-6">
        {{ $this->expenses->links() }}
    </div>
</div>