<?php

namespace App\Livewire\Concerns;

trait ReportsPackageValidation
{
    use ReportsMembershipValidation;

    protected function validationAttributes(): array
    {
        return [
            'name' => 'Nama Paket', 'type' => 'Tipe Layanan',
            'category' => 'Kategori / Kapasitas Orang', 'max_members' => 'Maksimal Member',
            'pt_sessions' => 'Jumlah Sesi', 'price' => 'Harga Dipakai',
            'normal_price' => 'Harga Normal', 'net_price' => 'Harga Net',
            'unrecommended_price' => 'Harga Tidak Disarankan', 'discount' => 'Diskon Paket',
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'numeric' => ':attribute harus berupa angka.',
            'integer' => ':attribute harus berupa bilangan bulat.',
            'min' => ':attribute minimal :min.',
            'max' => ':attribute maksimal :max.',
            'in' => 'Pilih :attribute yang tersedia.',
            'price.required' => 'Isi salah satu harga, lalu pilih Harga Dipakai.',
        ];
    }
}
