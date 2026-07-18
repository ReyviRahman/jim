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
            $table->foreignId('device_event_id')
                ->nullable()
                ->unique()
                ->after('id')
                ->constrained()
                ->nullOnDelete();
            $table->enum('type', ['gym', 'pt', 'visit', 'coach_attendance'])
                ->nullable()
                ->default(null)
                ->change();
            $table->enum('attendance_status', ['checkIn', 'checkOut'])
                ->nullable()
                ->after('type')
                ->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('attendances')->whereNull('type')->update(['type' => 'gym']);

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex(['attendance_status']);
            $table->dropColumn('attendance_status');
            $table->dropConstrainedForeignId('device_event_id');
            $table->enum('type', ['gym', 'pt', 'visit', 'coach_attendance'])
                ->nullable(false)
                ->default('gym')
                ->change();
        });
    }
};
