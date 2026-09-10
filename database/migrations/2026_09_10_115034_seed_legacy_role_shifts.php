<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $prefixes = ['admin' => 'ADM', 'kasir_gym' => 'KSG', 'kasir_minum' => 'KSM'];
            $invalid = DB::table('users')->whereNotNull('shift')
                ->where(function ($query) use ($prefixes) {
                    $query->whereNotIn('role', array_keys($prefixes))->orWhereNotIn('shift', ['Pagi', 'Siang']);
                })->exists();

            if ($invalid) {
                throw new RuntimeException('Mapping shift user belum lengkap. Migrasi dihentikan tanpa mengubah assignment.');
            }

            foreach ($prefixes as $role => $prefix) {
                foreach (['Pagi' => ['07:00:00', '15:00:00'], 'Siang' => ['14:00:00', '22:00:00']] as $name => $hours) {
                    $query = DB::table('shifts')->where('code', $prefix.'-'.strtoupper($name))->where('role', $role);
                    if ($query->count() > 1 || ($query->exists() && $query->value('name') !== $name)) {
                        throw new RuntimeException('Master shift bawaan ambigu atau memiliki nama berbeda.');
                    }

                    if (! $query->exists()) {
                        DB::table('shifts')->insert([
                            'code' => $prefix.'-'.strtoupper($name), 'role' => $role, 'name' => $name,
                            'start_time' => $hours[0], 'end_time' => $hours[1],
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                }
            }
        });
    }

    public function down(): void
    {
        // Master data is retained because it may already be used or edited.
    }
};
