<?php

namespace App;

final class MembershipFormValidation
{
    /** @return array<string, string> */
    public static function attributes(): array
    {
        return [
            'registration_type' => 'Jenis Pendaftaran',
            'is_renewal' => 'Status Renewal',
            'is_active' => 'Status Keanggotaan',
            'gym_package_id' => 'Paket Gym / Visit',
            'pt_package_id' => 'Paket Personal Trainer',
            'pt_id' => 'Personal Trainer',
            'start_date' => 'Tanggal Mulai',
            'membership_end_date' => 'Tanggal Berakhir Gym',
            'pt_end_date' => 'Tanggal Berakhir PT',
            'admin_id' => 'Admin / Kasir (Shift)',
            'follow_up_id' => 'Admin Follow Up',
            'follow_up_id_two' => 'Sales Follow Up',
            'payment_type' => 'Tipe Pembayaran',
            'payment_method' => 'Metode Pembayaran',
            'payment_date' => 'Tanggal Pembayaran',
            'amount_paid' => 'Uang Diterima / Nominal DP',
            'package_name' => 'Paket Member',
            'transaction_type' => 'Status Transaksi',
            'notes' => 'Catatan',
            'pt_trial_interest' => 'Minat Personal Trainer / Trial',
            'payment_proof' => 'Bukti Pembayaran',
            'split_payment_proofs.transfer' => 'Bukti Transfer',
            'split_payment_proofs.qris' => 'Bukti QRIS',
            'split_payment_proofs.debit' => 'Bukti Debit',
            'split_payment' => 'Split Payment',
            'reason' => 'Alasan Operasional',
            'manual_discount' => 'Diskon Manual',
            'admin_fee' => 'Biaya Admin',
            'shift' => 'Shift',
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'required_if' => ':attribute wajib diisi.',
            'after_or_equal' => ':attribute tidak boleh sebelum Tanggal Mulai.',
            'date' => ':attribute harus berupa tanggal yang valid.',
            'date_format' => ':attribute harus berupa tanggal yang valid.',
            'exists' => 'Pilihan :attribute tidak tersedia. Silakan pilih kembali.',
            'in' => 'Pilih salah satu opsi :attribute yang tersedia.',
            'boolean' => 'Pilih salah satu opsi :attribute.',
            'is_renewal.required' => 'Pilih Renewal atau Tidak Renewal pada Status Renewal.',
            'is_renewal.boolean' => 'Pilih Renewal atau Tidak Renewal pada Status Renewal.',
            'registration_type.required' => 'Pilih Jenis Pendaftaran terlebih dahulu.',
            'gym_package_id.required' => 'Pilih Paket Gym / Visit terlebih dahulu.',
            'pt_package_id.required' => 'Pilih Paket Personal Trainer terlebih dahulu.',
            'admin_id.required' => 'Pilih Admin / Kasir (Shift) yang mencatat transaksi.',
            'follow_up_id.required' => 'Pilih Admin Follow Up.',
            'follow_up_id_two.required' => 'Pilih Sales Follow Up.',
            'payment_method.required' => 'Pilih Metode Pembayaran.',
            'payment_type.required' => 'Pilih Tipe Pembayaran: Lunas atau Nyicil (DP).',
            'is_active.required' => 'Pilih Status Keanggotaan: Aktif Sekarang atau Tidak Aktif (Pending).',
            'membership_end_date.required' => 'Tanggal Berakhir Gym wajib diisi.',
            'pt_end_date.required' => 'Tanggal Berakhir PT wajib diisi.',
            'pt_trial_interest.required' => 'Pilih minat Personal Trainer / Trial: Iya, Tidak, atau Mungkin nanti.',
            'payment_proof.required' => 'Unggah Bukti Pembayaran untuk metode non-Cash.',
            'reason.required' => 'Isi Alasan Operasional sebelum mengajukan pembayaran.',
        ];
    }
}
