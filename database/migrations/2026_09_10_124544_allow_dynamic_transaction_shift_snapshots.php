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
        foreach (['membership_transactions', 'expenses'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->string('shift')->nullable()->default(null)->change();
            });
        }

        Schema::table('beverage_sales', function (Blueprint $table): void {
            $table->string('shift')->nullable(false)->default('pagi')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['membership_transactions', 'expenses', 'beverage_sales'] as $name) {
            if (DB::table($name)->whereNotNull('shift')->whereNotIn('shift', ['Pagi', 'Siang'])->exists()) {
                throw new RuntimeException('Rollback dibatalkan: '.$name.' memiliki snapshot selain Pagi/Siang.');
            }
        }

        foreach (['membership_transactions', 'expenses'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->enum('shift', ['Pagi', 'Siang'])->nullable()->default(null)->change();
            });
        }

        Schema::table('beverage_sales', function (Blueprint $table): void {
            $table->enum('shift', ['pagi', 'siang'])->nullable(false)->default('pagi')->change();
        });
    }
};
