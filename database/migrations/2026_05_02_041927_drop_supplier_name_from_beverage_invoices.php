<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beverage_invoices', function (Blueprint $table) {
            $table->dropColumn('supplier_name');
        });
    }

    public function down(): void
    {
        Schema::table('beverage_invoices', function (Blueprint $table) {
            $table->string('supplier_name')->nullable();
        });
    }
};
