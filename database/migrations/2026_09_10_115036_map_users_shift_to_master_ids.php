<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $prefixes = ['admin' => 'ADM', 'kasir_gym' => 'KSG', 'kasir_minum' => 'KSM'];
            $assignments = [];
            foreach (DB::table('users')->whereNotNull('shift')->get(['id', 'role', 'shift']) as $user) {
                $prefix = $prefixes[$user->role] ?? null;
                $matches = $prefix === null ? collect() : DB::table('shifts')
                    ->where('role', $user->role)->where('name', $user->shift)
                    ->where('code', $prefix.'-'.strtoupper($user->shift))->get(['id']);

                if ($matches->count() !== 1) {
                    throw new RuntimeException('Mapping shift tidak ditemukan atau ambigu untuk user '.$user->id);
                }
                $assignments[$user->id] = $matches->first()->id;
            }

            foreach ($assignments as $userId => $shiftId) {
                DB::table('users')->where('id', $userId)->update(['shift' => (string) $shiftId]);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $assignments = [];
            foreach (DB::table('users')->whereNotNull('shift')->get(['id', 'shift']) as $user) {
                $name = DB::table('shifts')->where('id', $user->shift)->value('name');
                if (! in_array($name, ['Pagi', 'Siang'], true)) {
                    throw new RuntimeException('Rollback tidak dapat mengubah shift user '.$user->id.' menjadi enum lama.');
                }
                $assignments[$user->id] = $name;
            }

            foreach ($assignments as $userId => $name) {
                DB::table('users')->where('id', $userId)->update(['shift' => $name]);
            }
        });
    }
};
