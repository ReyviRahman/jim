<?php

use App\Models\Membership;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed; 

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination;

    public $search = '';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    #[Computed]
    public function memberships()
    {
        // Tambahkan 'members' di dalam array with()
        return Membership::with(['user', 'members', 'gymPackage', 'ptPackage'])
            ->whereIn('payment_status', ['partial', 'unpaid'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    // Cari di nama pendaftar utama
                    $q->whereHas('user', function ($subQ) {
                        $subQ->where('name', 'like', '%' . $this->search . '%');
                    })
                    // ATAU cari di nama anggota pivot (pasangannya)
                    ->orWhereHas('members', function ($subQ) {
                        $subQ->where('name', 'like', '%' . $this->search . '%');
                    });
                });
            })
            ->latest()
            ->paginate(10);
    }
}
?>

<div>
    <div class="mb-6 flex justify-between items-end">
        <div>
            <h5 class="text-xl font-semibold text-heading mb-2">Daftar Tagihan & Cicilan</h5>
            <p class="text-body text-sm">Daftar member yang masih memiliki sisa tagihan membership atau PT.</p>
        </div>
        <div class="w-72">
            <input type="text" wire:model.live.debounce.500ms="search" class="bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2" placeholder="Cari nama member...">
        </div>
    </div>

    <div class="bg-white rounded-md border border-default shadow-xs overflow-hidden">
        <table class="w-full text-sm text-left text-body">
            <thead class="text-xs text-heading uppercase bg-neutral-primary-soft border-b border-default">
                <tr>
                    <th class="px-4 py-3">Member</th>
                    <th class="px-4 py-3">Paket Layanan</th>
                    <th class="px-4 py-3 text-right">Total Tagihan</th>
                    <th class="px-4 py-3 text-right">Sudah Dibayar</th>
                    <th class="px-4 py-3 text-right">Sisa Tagihan</th>
                    <th class="px-4 py-3 text-center">Status Akses</th>
                    <th class="px-4 py-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->memberships as $m)
                    <tr class="border-b border-default hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-heading">
                            <div class="flex flex-col gap-1 mb-1.5">
                                @if($m->members && $m->members->count() > 0)
                                    @foreach($m->members as $member)
                                        <div>
                                            {{ $member->name }}
                                        </div>
                                    @endforeach
                                @else
                                    <div>
                                        {{ $m->user->name ?? 'User Dihapus' }}
                                        <span class="text-[10px] font-normal text-emerald-600 ml-1 border border-emerald-200 bg-emerald-50 px-1 py-0.5 rounded">Pendaftar</span>
                                    </div>
                                @endif
                            </div>
                            <div class="text-xs text-gray-500 font-normal">
                                Tgl: {{ $m->start_date ? \Carbon\Carbon::parse($m->start_date)->format('d M Y') : '-' }}
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            @if($m->type === 'visit') Visit Harian
                            @elseif($m->gym_package_id) {{ $m->gymPackage->name }}
                            @elseif($m->pt_package_id) {{ $m->ptPackage->name }}
                            @else Paket Custom @endif
                        </td>
                        <td class="px-4 py-3 text-right font-medium text-heading">Rp {{ number_format($m->price_paid, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right text-green-600">Rp {{ number_format($m->total_paid, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right text-red-600 font-bold">Rp {{ number_format($m->price_paid - $m->total_paid, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-center whitespace-nowrap">
                            @if($m->status === 'active')
                                <span class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">Aktif</span>
                            @else
                                <span class="bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">Belum Lunas</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center whitespace-nowrap">
                            {{-- Ganti rute ini sesuai dengan rute halaman pembayaran kamu --}}
                            <a href="{{ route('admin.cicilan.pay', $m->id) }}" wire:navigate class="text-white bg-brand hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium font-medium rounded-md text-xs px-3 py-1.5 transition-colors">
                                Bayar Cicilan
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-400">
                            Tidak ada data cicilan atau tagihan yang tertunda. Semua lunas! 🎉
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        
        <div class="p-4 border-t border-default">
            {{ $this->memberships->links() }}
        </div>
    </div>
</div>