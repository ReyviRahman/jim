<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pt_sessions', function (Blueprint $table) {
            $table->id();

            // Relasi
            // Menyambungkan ke tabel periode
            $table->foreignId('period_id')->constrained('periods')->restrictOnDelete();

            // Menyambungkan data ini ke transaksi membership utamanya
            $table->foreignId('membership_id')->constrained('memberships')->cascadeOnDelete();
            // Menyambungkan langsung ke user untuk mempermudah query "nama member"
            $table->foreignId('member_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('pt_id')->constrained('users')->cascadeOnDelete();

            // Harga
            $table->decimal('price', 12, 0);

            // Kategori
            $table->string('category');
            $table->string('sale_category');

            // Tracking Sesi
            $table->integer('initial_sessions')->default(0);
            $table->integer('added_sessions')->default(0);
            $table->integer('total_sessions')->default(0);
            $table->integer('used_sessions')->default(0);
            $table->integer('expired_sessions')->default(0);
            $table->integer('remaining_sessions')->default(0);

            // Nominal
            $table->decimal('nominal_per_session', 12, 0)->nullable();
            $table->decimal('total_nominal', 12, 0)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pt_sessions');
    }
};
