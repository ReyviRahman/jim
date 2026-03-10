<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use App\Models\Expense;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts::admin')] class extends Component
{
    // Aturan validasi langsung menggunakan Attributes bawaan Livewire 3
    #[Rule('required|string|max:255')]
    public $description;

    #[Rule('required|numeric|min:1')]
    public $amount;

    #[Rule('required|date')]
    public $expense_date;

    public function mount()
    {
        // Set nilai default tanggal ke hari ini saat halaman pertama kali dibuka
        $this->expense_date = date('Y-m-d');
    }

    public function save()
    {
        // Jalankan validasi
        $this->validate();

        // Simpan ke database
        Expense::create([
            'admin_id' => Auth::id(), // Ambil ID dari admin yang sedang login
            'description' => $this->description,
            'amount' => $this->amount,
            'expense_date' => $this->expense_date,
        ]);

        // Beri pesan sukses (opsional, jika Anda punya komponen alert)
        session()->flash('success', 'Pengeluaran berhasil dicatat!');

        // Redirect kembali ke halaman tabel pengeluaran 
        // (Sesuaikan "/admin/expenses" dengan URL route tabel Anda)
        return $this->redirect(route('admin.pengeluaran.index'), navigate: true);
    }
};
?>

<div>
    <div class="flex sm:flex-row flex-col justify-between items-center mb-6">
        <h5 class="text-xl font-semibold text-heading">Tambah Pengeluaran Baru</h5>
    </div>

    <div class="bg-white rounded-md border border-default-medium shadow-xs p-6 max-w-2xl">
        <form wire:submit="save" class="space-y-6">
            
            {{-- Tanggal Pengeluaran --}}
            <div>
                <label class="block text-sm font-medium text-heading mb-1">
                    Tanggal Pengeluaran <span class="text-red-500">*</span>
                </label>
                {{-- Kita pakai input date HTML5 standar yang lebih simpel untuk form Create --}}
                <input type="date" wire:model="expense_date" class="block w-full px-3 py-2.5 bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand shadow-xs">
                @error('expense_date') <span class="text-sm text-red-600 mt-1 block">{{ $message }}</span> @enderror
            </div>

            {{-- Keterangan / Deskripsi --}}
            <div>
                <label class="block text-sm font-medium text-heading mb-1">
                    Keterangan <span class="text-red-500">*</span>
                </label>
                <textarea wire:model="description" rows="3" class="block w-full px-3 py-2.5 bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand shadow-xs" placeholder="Contoh: Beli air galon, Bayar listrik bulanan, dll" required></textarea>
                @error('description') <span class="text-sm text-red-600 mt-1 block">{{ $message }}</span> @enderror
            </div>

            {{-- Nominal --}}
            <div x-data="{ 
                    // Ambil nilai lama jika ada (misal saat form gagal validasi)
                    displayAmount: '{{ $amount }}' ? new Intl.NumberFormat('id-ID').format('{{ $amount }}') : '', 
                    
                    formatAmount() {
                        // 1. Hapus semua karakter selain angka
                        let numericValue = this.displayAmount.replace(/\D/g, '');
                        
                        // 2. Jika input kosong, kembalikan ke kosong
                        if (numericValue === '') {
                            this.displayAmount = '';
                            $wire.amount = ''; 
                            return;
                        }
                        
                        // 3. Format angka dengan titik ribuan ala Indonesia
                        this.displayAmount = new Intl.NumberFormat('id-ID').format(numericValue);
                        
                        // 4. Kirim angka bersih (tanpa titik) ke PHP Livewire
                        $wire.amount = numericValue; 
                    } 
                }">
                <label class="block text-sm font-medium text-heading mb-1">
                    Nominal (Rp) <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                        <span class="text-gray-500 sm:text-sm">Rp</span>
                    </div>
                    {{-- Ubah type='number' menjadi type='text' agar bisa membaca titik --}}
                    {{-- Gunakan x-model dari Alpine, lalu jalankan formatAmount() setiap kali ada ketikan (@input) --}}
                    <input type="text" x-model="displayAmount" @input="formatAmount()" class="block w-full ps-10 pe-3 py-2.5 bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand shadow-xs" placeholder="0" required>
                </div>
                @error('amount') <span class="text-sm text-red-600 mt-1 block">{{ $message }}</span> @enderror
            </div>

            {{-- Action Buttons --}}
            <div class="flex items-center gap-3 pt-4 border-t border-gray-100">
                {{-- Tombol Simpan dengan indikator loading Livewire --}}
                <button type="submit" class="px-5 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 shadow-xs flex items-center justify-center min-w-[160px]">
                    <span wire:loading.remove wire:target="save">Simpan Pengeluaran</span>
                    <span wire:loading wire:target="save">Menyimpan...</span>
                </button>
                
                {{-- Tombol Batal (Kembali ke tabel) --}}
                <a href={{ route('admin.pengeluaran.index') }} wire:navigate class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-default-medium rounded-md hover:bg-gray-50 focus:ring-4 focus:ring-gray-100 shadow-xs">
                    Batal
                </a>
            </div>
        </form>
    </div>
</div>