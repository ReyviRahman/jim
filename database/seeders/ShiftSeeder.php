<?php

namespace Database\Seeders;

use App\Models\Shift;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShiftSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (['admin' => 'ADM', 'kasir_gym' => 'KSG', 'kasir_minum' => 'KSM'] as $role => $prefix) {
                foreach (['Pagi' => ['07:00:00', '15:00:00'], 'Siang' => ['14:00:00', '22:00:00']] as $name => $hours) {
                    $code = $name === 'Pagi' ? 'P' : 'S';
                    $matches = Shift::query()->where('role', $role)
                        ->whereIn('code', [$code, $prefix.'-'.strtoupper($name)])->get();

                    if ($matches->count() > 1) {
                        throw new \RuntimeException('Master shift ambigu untuk role '.$role.' dan kode '.$code.'.');
                    }

                    if ($shift = $matches->first()) {
                        if ($shift->name !== $name) {
                            throw new \RuntimeException('Nama master shift berbeda untuk role '.$role.' dan kode '.$code.'.');
                        }
                        $shift->update(['code' => $code]);
                    } else {
                        Shift::query()->create([
                            'code' => $code, 'role' => $role, 'name' => $name,
                            'start_time' => $hours[0], 'end_time' => $hours[1],
                        ]);
                    }
                }
            }
        });
    }
}
