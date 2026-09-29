<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->softDeletes();
            $table->unsignedTinyInteger('active_unique_key')->nullable()
                ->virtualAs('CASE WHEN deleted_at IS NULL THEN 1 ELSE NULL END');
            foreach (['email', 'phone', 'hikvision_employee_no'] as $column) {
                $table->dropUnique([$column]);
                $table->unique([$column, 'active_unique_key'], 'users_'.$column.'_active_unique');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach (['email', 'phone', 'hikvision_employee_no'] as $column) {
                $table->unique($column);
                $table->dropUnique('users_'.$column.'_active_unique');
            }
            $table->dropColumn('active_unique_key');
            $table->dropSoftDeletes();
        });
    }
};
