<?php

namespace App;

class MembershipWaiverTerms
{
    public const CONSENT_LABEL = 'Saya membaca & setuju Kebijakan Privasi & Waiver Frans Gym. (PDP)';

    /** @return array{heading: string, introduction: string, items: list<string>} */
    public static function snapshot(): array
    {
        return [
            'heading' => 'KETENTUAN PEMBAYARAN',
            'introduction' => 'Dengan melakukan pembayaran, member dianggap telah membaca dan menyetujui seluruh ketentuan FRANS GYM.',
            'items' => [
                'Seluruh pembayaran yang telah diterima oleh FRANS GYM bersifat final, tidak dapat dibatalkan, tidak dapat dikembalikan (non-refundable), dan tidak dapat dialihkan kepada pihak lain.',
                'Membership bersifat pribadi dan tidak dapat dipindahtangankan kepada orang lain.',
                'Masa aktif membership tetap berjalan sesuai tanggal yang tertera pada invoice.',
                'FRANS GYM tidak bertanggung jawab atas membership yang tidak digunakan oleh member selama masa aktif.',
                'Apabila terjadi penutupan operasional karena force majeure, kebijakan akan mengikuti ketentuan manajemen FRANS GYM.',
            ],
        ];
    }
}
