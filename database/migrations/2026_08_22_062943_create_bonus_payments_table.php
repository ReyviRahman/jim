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
        Schema::create('bonus_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_user_id')->constrained('users')->restrictOnDelete();
            $table->date('date_start');
            $table->date('date_end');
            $table->string('search_filter')->nullable();
            $table->decimal('total_nominal_akhir', 15, 2);
            $table->decimal('bonus_percentage', 5, 2);
            $table->string('range_start');
            $table->string('range_end');
            $table->decimal('bonus_amount', 15, 2);
            $table->decimal('potongan', 15, 2)->default(0);
            $table->text('keterangan_potongan')->nullable();
            $table->decimal('net_amount', 15, 2);
            $table->foreignId('paid_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('paid_at')->index();
            $table->timestamps();

            $table->index(['staff_user_id', 'date_start', 'date_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bonus_payments');
    }
};
