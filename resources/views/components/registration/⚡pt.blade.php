<?php

use Livewire\Component;

new class extends Component
{
    public $joined_at;

    public function mount()
    {
        $this->joined_at = date('Y-m-d');
    }
};
?>

<div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="mb-4">
            <label for="name" class="block mb-2.5 text-sm font-medium text-heading">Nama</label>
            <input type="text" id="name"
                class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:accent focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body"
                placeholder="Masukkan Nama" required />
        </div>
        <div class="mb-4">
            <label for="gender" class="block mb-2.5 text-sm font-medium text-heading">Jenis
                Kelamin</label>
            <select id="gender" name="gender"
                class="block w-full px-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs placeholder:text-body">
                <option value="Laki-laki">Laki-laki</option>
                <option value="Perempuan">Perempuan</option>
            </select>
        </div>
        <div class="mb-4">
            <label for="age" class="block mb-2.5 text-sm font-medium text-heading">Usia</label>
            <input type="number" id="age"
                class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:accent focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body"
                placeholder="Masukkan Usia" required />
        </div>
        <div class="mb-4">
            <label for="age" class="block mb-2.5 text-sm font-medium text-heading">No HP /
                WhatsApp</label>
            <input type="number" id="age"
                class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:accent focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body"
                placeholder="Masukkan No HP" required />
        </div>
        <div class="mb-4">
            <label for="joined_at" class="block mb-2.5 text-sm font-medium text-heading">Tanggal Join Frans Gym</label>
            <input type="date" 
                id="joined_at"
                wire:model="joined_at" 
                class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:accent focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body"
                required />
        </div>
        
        <div class="mb-4">
            <label for="alamat" class="block mb-2.5 text-sm font-medium text-heading">Alamat</label>
            <input type="text" id="alamat"
                class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:accent focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body"
                placeholder="Masukkan Alamat" required />
        </div>
<div class="mb-4">
            <label for="email" class="block mb-2.5 text-sm font-medium text-heading">Email</label>
            <input type="email" id="email"
                class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:accent focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body"
                placeholder="Masukkan Email" required />
        </div>
        <div>
            <label for="password" class="block mb-2.5 text-sm font-medium text-heading">Password</label>
            <input type="password" id="password"
                class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:accent focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body"
                placeholder="•••••••••" required />
        </div>
    </div>
    <button type="submit"
        class="mt-4 text-heading cursor-pointer bg-brand box-border border border-transparent hover:bg-heading hover:text-white focus:ring-4 focus:accent-medium shadow-xs font-medium leading-5 rounded-base text-sm px-4 py-2.5 focus:outline-none w-full mb-3">Daftarkan
        Akun</button>
</div>