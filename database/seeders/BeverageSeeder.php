<?php

namespace Database\Seeders;

use App\Models\Beverage;
use Illuminate\Database\Seeder;

class BeverageSeeder extends Seeder
{
    public function run(): void
    {
        Beverage::factory()->count(50)->create();
    }
}