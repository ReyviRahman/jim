<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendance_employee', function (Blueprint $table) {
            $table->enum('status', ['hadir', 'izin', 'off'])->default('hadir');
            $table->text('notes')->nullable();
            $table->timestamp('check_in_time')->nullable()->change();
            foreach (['shift_code', 'shift_name', 'shift_role'] as $column) {
                $table->string($column)->nullable()->change();
            }
            foreach (['shift_start_time', 'shift_end_time'] as $column) {
                $table->time($column)->nullable()->change();
            }
            foreach (['scheduled_start_at', 'scheduled_end_at', 'checkout_deadline_at'] as $column) {
                $table->timestamp($column)->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $incompatible = DB::table('attendance_employee')->where('status', '!=', 'hadir')
            ->orWhereNotNull('notes');
        foreach (['check_in_time', 'shift_code', 'shift_name', 'shift_role', 'shift_start_time', 'shift_end_time', 'scheduled_start_at', 'scheduled_end_at', 'checkout_deadline_at'] as $column) {
            $incompatible->orWhereNull($column);
        }
        if ($incompatible->exists()) {
            throw new RuntimeException('Rollback dihentikan: data Izin/Off, catatan, atau kolom kosong belum kompatibel dengan struktur absensi lama.');
        }

        Schema::table('attendance_employee', function (Blueprint $table) {
            $table->timestamp('check_in_time')->nullable(false)->change();
            foreach (['shift_code', 'shift_name', 'shift_role'] as $column) {
                $table->string($column)->nullable(false)->change();
            }
            foreach (['shift_start_time', 'shift_end_time'] as $column) {
                $table->time($column)->nullable(false)->change();
            }
            foreach (['scheduled_start_at', 'scheduled_end_at', 'checkout_deadline_at'] as $column) {
                $table->timestamp($column)->nullable(false)->change();
            }
            $table->dropColumn(['status', 'notes']);
        });
    }
};
