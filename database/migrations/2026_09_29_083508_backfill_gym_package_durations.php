<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $packages = DB::table('gym_packages')->where('duration_months', 0)->where('duration_weeks', 0)->where('duration_days', 0);
        (clone $packages)->whereIn('type', ['gym', 'pt'])->update(['duration_months' => 1]);
        (clone $packages)->where('type', 'visit')->update(['duration_days' => 1]);
    }

    /**
     * Preserve configured durations; the schema rollback removes these columns.
     */
    public function down(): void
    {
        //
    }
};
