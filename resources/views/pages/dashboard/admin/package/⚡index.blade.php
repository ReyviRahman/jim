<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use App\Models\GymPackage;

new #[Layout('layouts::admin')] class extends Component
{
    // Ambil data paket (Computed agar hemat database)
    #[Computed]
    public function packages()
    {
        return GymPackage::latest()->get();
    }

    // Fitur: Hapus Paket
    public function delete($id)
    {
        $package = GymPackage::findOrFail($id);

        // delete data
        $package->delete();

        // flash message
        session()->flash('message', 'Data Paket Berhasil Dihapus.');
    }

    // Fitur: Ubah Status (Aktif/Tidak)
    public function toggleStatus($id)
    {
        $package = GymPackage::find($id);
        if ($package) {
            $package->update(['is_active' => !$package->is_active]);
        }
    }
};
?>

<div>
    <div class="flex justify-between items-center mb-6">
        <h5 class="text-xl font-semibold text-heading">Daftar Paket Membership</h5>
        
        <a href="{{ route('admin.packages.create') }}" wire:navigate class="text-white bg-brand box-border border border-transparent hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium shadow-xs font-medium leading-5 rounded-md text-sm px-4 py-2.5 focus:outline-none">+ Tambah Paket</a>
    </div>

    

<div class="relative overflow-x-auto bg-neutral-primary-soft shadow-xs rounded-md border border-default">
    <table class="w-full text-sm text-left rtl:text-right text-body">
        <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-default-medium">
            <tr>
                <th scope="col" class="px-6 py-3 font-medium">
                    No
                </th>
                <th scope="col" class="px-6 py-3 font-medium">
                    Nama Paket
                </th>
                <th scope="col" class="px-6 py-3 font-medium">
                    Harga
                </th>
                <th scope="col" class="px-6 py-3 font-medium">
                    Sesi
                </th>
                <th scope="col" class="px-6 py-3 font-medium">
                    Aksi
                </th>
            </tr>
        </thead>
        <tbody>
            @forelse ($this->packages as $package)
                <tr class="bg-neutral-primary-soft border-b border-default hover:bg-neutral-secondary-medium">
                    <td scope="row" class="px-7 py-4 font-medium text-heading whitespace-nowrap">
                        {{ $loop->iteration }}
                    </td>
                    <td scope="row" class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                        {{ $package->name }}
                    </td>
                    <td scope="row" class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                        Rp {{ number_format($package->price, 0, ',', '.') }}
                    </td>
                    <td scope="row" class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                        {{ $package->number_of_sessions }} Sesi
                    </td>
                    <td class="flex items-center px-6 py-4">
                        <a href="{{ route('admin.packages.edit', $package->id) }}" wire:navigate class="font-medium text-fg-brand hover:underline">Edit</a>
                        <button 
                            wire:click="delete({{ $package->id }})"
                            wire:confirm="Apakah Anda yakin ingin menghapus paket {{ $package->name }}?"
                            class="font-medium text-danger hover:underline ms-3"
                            >
                            Hapus
                        </button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                        Belum ada data paket membership.
                    </td>
                </tr>
            @endforelse
            
        </tbody>
    </table>
</div>
    
</div>