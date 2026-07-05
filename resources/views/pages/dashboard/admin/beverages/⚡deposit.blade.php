<?php

namespace App\Livewire\Pages\Dashboard\Admin\Beverages;

use App\Models\DepositBeverage;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination;

    public $search = '';
    public $start_date = '';
    public $end_date = '';

    public function mount()
    {
        $this->start_date = date('Y-m-01');
        $this->end_date = date('Y-m-d');
    }

    public function getDepositListProperty()
    {
        return DepositBeverage::with('beverageSale')
            ->when($this->search, function ($query) {
                $query->where('nama_pelanggan', 'like', '%' . $this->search . '%');
            })
            ->when($this->start_date, function ($query) {
                $query->whereDate('created_at', '>=', $this->start_date);
            })
            ->when($this->end_date, function ($query) {
                $query->whereDate('created_at', '<=', $this->end_date);
            })
            ->latest()
            ->paginate(10);
    }

    public function with(): array
    {
        return [];
    }
};
?>

<div>
    <div class="flex sm:flex-row flex-col justify-between items-center mb-6">
        <h5 class="text-xl font-semibold text-heading">Daftar Deposit Minuman</h5>
        <a href="{{ route('admin.beverages.pos') }}"
            class="mt-2 sm:mt-0 px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white text-sm font-semibold rounded-md transition-colors">
            Kembali ke POS
        </a>
    </div>

    @if (session()->has('success'))
        <div x-data='{ show: true }' x-show='show' x-init='setTimeout(() => show = false, 3000)'
            class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50" role="alert">
            <span class="font-medium">Sukses!</span> {{ session('success') }}
        </div>
    @endif

    <div class="bg-neutral-primary-soft shadow-xs rounded-md border border-default">
        <div class="p-4 border-b border-default-medium">
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                <h6 class="text-lg font-semibold text-heading">Daftar Deposit Pelanggan</h6>
                <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                    <input type="date" wire:model.live="start_date"
                        class="px-2 py-1.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs">
                    <input type="date" wire:model.live="end_date"
                        class="px-2 py-1.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs">
                </div>
            </div>
            <div class="mt-3">
                <input type="text" wire:model.live="search"
                    class="block w-full px-3 py-2 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs placeholder:text-body"
                    placeholder="Cari nama pelanggan...">
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left rtl:text-right text-body">
                <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-default-medium">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-medium">Tanggal</th>
                        <th scope="col" class="px-4 py-3 font-medium">Nama Pelanggan</th>
                        <th scope="col" class="px-4 py-3 font-medium text-right">Nominal Deposit</th>
                        <th scope="col" class="px-4 py-3 font-medium text-right">Sisa Deposit</th>
                        <th scope="col" class="px-4 py-3 font-medium text-center">Status</th>
                        <th scope="col" class="px-4 py-3 font-medium">Asal Transaksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->depositList as $deposit)
                        <tr class="bg-neutral-primary-soft border-b border-default hover:bg-neutral-secondary-medium">
                            <td class="px-4 py-3 whitespace-nowrap">{{ $deposit->created_at->format('d M Y H:i') }}</td>
                            <td class="px-4 py-3 whitespace-nowrap font-semibold text-heading">{{ $deposit->nama_pelanggan }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap font-semibold text-emerald-600">Rp {{ number_format($deposit->nominal, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap font-semibold text-blue-600">Rp {{ number_format($deposit->sisa_nominal, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                @if ($deposit->is_used || $deposit->sisa_nominal <= 0)
                                    <span class="px-2 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded-md">Habis</span>
                                @else
                                    <span class="px-2 py-1 bg-emerald-100 text-emerald-700 text-xs font-semibold rounded-md">Aktif</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if ($deposit->beverageSale)
                                    <span class="text-xs text-body">{{ $deposit->beverageSale->nama_staff }} — {{ $deposit->beverageSale->waktu_transaksi->format('d M Y H:i') }}</span>
                                @else
                                    <span class="text-xs text-gray-500">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">Tidak ada data deposit.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4 px-4">
            {{ $this->depositList->links() }}
        </div>
    </div>
</div>
