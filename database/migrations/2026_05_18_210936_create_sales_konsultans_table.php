<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_konsultans', function (Blueprint $table) {
            $table->id();
            $table->string('rentang_satu');
            $table->string('rentang_dua');
            $table->decimal('persen', 5, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_konsultans');
    }
};
