<?php

namespace Database\Seeders;

use App\Models\KasirKonsultan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class KasirKonsultanSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $data = [
            ['rentang_satu' => '20000000', 'rentang_dua' => '30000000', 'persen' => 3.00],
            ['rentang_satu' => '30000000', 'rentang_dua' => '40000000', 'persen' => 4.00],
            ['rentang_satu' => '40000000', 'rentang_dua' => '50000000', 'persen' => 6.00],
            ['rentang_satu' => '50000000', 'rentang_dua' => 'plus', 'persen' => 8.00],
        ];

        foreach ($data as $item) {
            KasirKonsultan::create($item);
        }
    }
}
