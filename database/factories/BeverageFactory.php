<?php

namespace Database\Factories;

use App\Models\Beverage;
use Illuminate\Database\Eloquent\Factories\Factory;

class BeverageFactory extends Factory
{
    protected $model = Beverage::class;

    private static array $beverages = [
        ['nama_produk' => 'Aqua 600ml', 'harga_modal' => 3500, 'harga_jual' => 5000],
        ['nama_produk' => 'Aqua 1500ml', 'harga_modal' => 6000, 'harga_jual' => 8000],
        ['nama_produk' => 'Le Minerale 600ml', 'harga_modal' => 3000, 'harga_jual' => 4500],
        ['nama_produk' => 'Le Minerale 1500ml', 'harga_modal' => 5500, 'harga_jual' => 7500],
        ['nama_produk' => 'Cleo 600ml', 'harga_modal' => 3200, 'harga_jual' => 5000],
        ['nama_produk' => 'Cleo 1500ml', 'harga_modal' => 5800, 'harga_jual' => 8000],
        ['nama_produk' => 'Club Milk 250ml', 'harga_modal' => 4000, 'harga_jual' => 6000],
        ['nama_produk' => 'Ultra Milk 250ml', 'harga_modal' => 4500, 'harga_jual' => 6500],
        ['nama_produk' => 'Frisian Flag 250ml', 'harga_modal' => 4200, 'harga_jual' => 6000],
        ['nama_produk' => 'Indomie Goreng', 'harga_modal' => 3500, 'harga_jual' => 5000],
        ['nama_produk' => 'Indomie Kuah', 'harga_modal' => 3500, 'harga_jual' => 5000],
        ['nama_produk' => 'Mie Sedaap Goreng', 'harga_modal' => 3000, 'harga_jual' => 4500],
        ['nama_produk' => 'Mie Sedaap Kuah', 'harga_modal' => 3000, 'harga_jual' => 4500],
        ['nama_produk' => 'Kopi Sachet', 'harga_modal' => 2000, 'harga_jual' => 3500],
        ['nama_produk' => 'Kopi Latte', 'harga_modal' => 5000, 'harga_jual' => 8000],
        ['nama_produk' => 'Teh Gula', 'harga_modal' => 2000, 'harga_jual' => 3000],
        ['nama_produk' => 'Teh Botol 500ml', 'harga_modal' => 4500, 'harga_jual' => 6000],
        ['nama_produk' => 'Fruitee 500ml', 'harga_modal' => 4000, 'harga_jual' => 5500],
        ['nama_produk' => 'Pop Ice 450ml', 'harga_modal' => 3500, 'harga_jual' => 5000],
        ['nama_produk' => 'Yoghurt 250ml', 'harga_modal' => 5000, 'harga_jual' => 7500],
        ['nama_produk' => 'Coca Cola 600ml', 'harga_modal' => 5000, 'harga_jual' => 7000],
        ['nama_produk' => 'Pepsi 600ml', 'harga_modal' => 5000, 'harga_jual' => 7000],
        ['nama_produk' => 'Sprite 600ml', 'harga_modal' => 5000, 'harga_jual' => 7000],
        ['nama_produk' => 'Fanta 600ml', 'harga_modal' => 5000, 'harga_jual' => 7000],
        ['nama_produk' => 'Ades 600ml', 'harga_modal' => 3500, 'harga_jual' => 5000],
        ['nama_produk' => 'Pocari Sweat 600ml', 'harga_modal' => 6000, 'harga_jual' => 8500],
        ['nama_produk' => 'Mizone 500ml', 'harga_modal' => 5000, 'harga_jual' => 7000],
        ['nama_produk' => 'Hydro Coco 300ml', 'harga_modal' => 6000, 'harga_jual' => 9000],
        ['nama_produk' => 'Good Time 300ml', 'harga_modal' => 7000, 'harga_jual' => 10000],
        ['nama_produk' => 'Energen 40g', 'harga_modal' => 4000, 'harga_jual' => 6000],
        ['nama_produk' => 'Beras Kencur 40g', 'harga_modal' => 3500, 'harga_jual' => 5500],
        ['nama_produk' => 'Kuku Bumbu 30g', 'harga_modal' => 2000, 'harga_jual' => 3500],
        ['nama_produk' => 'Mie Lidi 30g', 'harga_modal' => 2000, 'harga_jual' => 3500],
        ['nama_produk' => 'Qtela 60g', 'harga_modal' => 8000, 'harga_jual' => 12000],
        ['nama_produk' => 'Pilus 60g', 'harga_modal' => 7000, 'harga_jual' => 10000],
        ['nama_produk' => 'Taro 50g', 'harga_modal' => 6000, 'harga_jual' => 9000],
        ['nama_produk' => 'Chitato 50g', 'harga_modal' => 7000, 'harga_jual' => 10000],
        ['nama_produk' => 'Lays 50g', 'harga_modal' => 8000, 'harga_jual' => 12000],
        ['nama_produk' => 'Doritos 50g', 'harga_modal' => 9000, 'harga_jual' => 13000],
        ['nama_produk' => 'Oreo 40g', 'harga_modal' => 5000, 'harga_jual' => 7500],
        ['nama_produk' => 'Bengbeng 30g', 'harga_modal' => 3000, 'harga_jual' => 5000],
        ['nama_produk' => 'Silverqueen 30g', 'harga_modal' => 8000, 'harga_jual' => 12000],
        ['nama_produk' => 'Cadbury 30g', 'harga_modal' => 9000, 'harga_jual' => 13000],
        ['nama_produk' => 'Kiss 25g', 'harga_modal' => 5000, 'harga_jual' => 8000],
        ['nama_produk' => 'Mounds 30g', 'harga_modal' => 7000, 'harga_jual' => 10000],
        ['nama_produk' => 'Ari Bits 25g', 'harga_modal' => 4000, 'harga_jual' => 6000],
        ['nama_produk' => 'Starbite 25g', 'harga_modal' => 3500, 'harga_jual' => 5500],
        ['nama_produk' => 'Wafello 25g', 'harga_modal' => 5000, 'harga_jual' => 7500],
        ['nama_produk' => 'Biskuit Coklat 40g', 'harga_modal' => 4000, 'harga_jual' => 6000],
    ];

    private static int $index = 0;

    public function definition(): array
    {
        $beverage = self::$beverages[self::$index % count(self::$beverages)];
        self::$index++;

        return [
            'nama_produk' => $beverage['nama_produk'],
            'harga_modal' => $beverage['harga_modal'],
            'harga_jual' => $beverage['harga_jual'],
            'stok_sekarang' => $this->faker->numberBetween(10, 100),
        ];
    }
}