<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use App\Models\Expense;

new #[Layout('layouts::admin')] class extends Component
{
    // Menyimpan ID pengeluaran yang sedang diedit
    public $expense_id;

    #[Rule('required|string|max:255')]
    public $description;

    #[Rule('required|numeric|min:1')]
    public $amount;

    #[Rule('required|date')]
    public $expense_date;

    /**
     * Fungsi mount() berjalan otomatis saat halaman pertama kali dimuat.
     * Kita menggunakan Route Model Binding untuk langsung menarik data Expense.
     */
    public function mount(Expense $expense)
    {
        // Pengecekan Keamanan Ganda: Pastikan hanya admin yang bisa mengakses halaman ini
        // (Sesuaikan 'role' dengan kolom hak akses di database Anda)
        if (auth()->user()->role !== 'admin') {
            abort(403, 'Akses ditolak. Anda tidak memiliki izin untuk mengedit data.');
        }

        // Isi properti form dengan data dari database
        $this->expense_id = $expense->id;
        $this->description = $expense->description;
        $this->amount = $expense->amount;
        
        // Format tanggal agar sesuai dengan input type="date" HTML (Y-m-d)
        $this->expense_date = \Carbon\Carbon::parse($expense->expense_date)->format('Y-m-d');
    }

    public function update()
    {
        // 1. Jalankan validasi
        $this->validate();

        // 2. Cari data berdasarkan ID, lalu perbarui
        $expense = Expense::findOrFail($this->expense_id);
        
        $expense->update([
            'description' => $this->description,
            'amount' => $this->amount,
            'expense_date' => $this->expense_date,
            // Perhatikan: admin_id tidak kita ubah agar riwayat asli pembuatnya tidak hilang.
            // Namun jika Anda ingin mencatat siapa yang mengedit, Anda perlu menambah 
            // kolom baru di database, misalnya 'updated_by_admin_id'.
        ]);

        // 3. Beri notifikasi (jika ada komponen flash message)
        session()->flash('success', 'Data pengeluaran berhasil diperbarui!');

        // 4. Kembali ke halaman tabel riwayat
        return $this->redirect(route('admin.pengeluaran.index'), navigate: true);
    }
};
?>

<div>
    <div class="flex sm:flex-row flex-col justify-between items-center mb-6">
        <h5 class="text-xl font-semibold text-heading">Edit Data Pengeluaran</h5>
    </div>

    <div class="bg-white rounded-md border border-default-medium shadow-xs p-6 max-w-2xl">
        {{-- Panggil fungsi update() saat form di-submit --}}
        <form wire:submit="update" class="space-y-6">
            
            {{-- Tanggal Pengeluaran --}}
            <div>
                <label class="block text-sm font-medium text-heading mb-1">
                    Tanggal Pengeluaran <span class="text-red-500">*</span>
                </label>
                <input type="date" wire:model="expense_date" class="block w-full px-3 py-2.5 bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand shadow-xs">
                @error('expense_date') <span class="text-sm text-red-600 mt-1 block">{{ $message }}</span> @enderror
            </div>

            {{-- Keterangan / Deskripsi --}}
            <div>
                <label class="block text-sm font-medium text-heading mb-1">
                    Keterangan <span class="text-red-500">*</span>
                </label>
                <textarea wire:model="description" rows="3" class="block w-full px-3 py-2.5 bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand shadow-xs"></textarea>
                @error('description') <span class="text-sm text-red-600 mt-1 block">{{ $message }}</span> @enderror
            </div>

            {{-- Nominal dengan Format Rupiah Otomatis --}}
            <div x-data="{ 
                    displayAmount: '{{ $amount }}' ? new Intl.NumberFormat('id-ID').format('{{ $amount }}') : '', 
                    
                    formatAmount() {
                        let numericValue = String(this.displayAmount).replace(/\D/g, '');
                        if (numericValue === '') {
                            this.displayAmount = '';
                            $wire.amount = ''; 
                            return;
                        }
                        this.displayAmount = new Intl.NumberFormat('id-ID').format(numericValue);
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
                    <input type="text" x-model="displayAmount" @input="formatAmount()" class="block w-full ps-10 pe-3 py-2.5 bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand shadow-xs" placeholder="0">
                </div>
                @error('amount') <span class="text-sm text-red-600 mt-1 block">{{ $message }}</span> @enderror
            </div>

            {{-- Action Buttons --}}
            <div class="flex items-center gap-3 pt-4 border-t border-gray-100">
                <button type="submit" class="px-5 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 shadow-xs flex items-center justify-center min-w-[160px]">
                    <span wire:loading.remove wire:target="update">Simpan Perubahan</span>
                    <span wire:loading wire:target="update">Menyimpan...</span>
                </button>
                
                {{-- Tombol Batal --}}
                <a href={{ route('admin.pengeluaran.index') }} wire:navigate class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-default-medium rounded-md hover:bg-gray-50 focus:ring-4 focus:ring-gray-100 shadow-xs">
                    Batal
                </a>
            </div>
        </form>
    </div>
</div>