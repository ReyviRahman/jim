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
        Schema::table('attendances', function (Blueprint $table) {
            $table->date('attendance_date')->nullable()->after('user_id');
            $table->timestamp('check_in_time')->nullable()->default(null)->change();
            $table->timestamp('check_out_time')->nullable()->after('check_in_time');

            $table->index('attendance_date', 'attendances_attendance_date_index');
            $table->unique(
                ['user_id', 'attendance_date'],
                'attendances_user_attendance_date_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('attendances')
            ->whereNull('check_in_time')
            ->update([
                'check_in_time' => DB::raw('COALESCE(check_out_time, created_at, CURRENT_TIMESTAMP)'),
            ]);

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropUnique('attendances_user_attendance_date_unique');
            $table->dropIndex('attendances_attendance_date_index');
            $table->timestamp('check_in_time')->nullable(false)->useCurrent()->change();
            $table->dropColumn(['attendance_date', 'check_out_time']);
        });
    }
};
