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
        Schema::create('membership_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('membership_id')->constrained('memberships');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('admin_id')->constrained('users');
            $table->foreignId('follow_up_id')->nullable()->constrained('users');
            $table->string('transaction_type'); // Contoh isian: 'New Member', 'Renew Member', 'Cicilan 1', 'Pelunasan', 'New PT 20 Sesi'
            $table->string('package_name');
            $table->decimal('amount', 12, 0);
            $table->enum('payment_method', ['cash', 'transfer', 'qris', 'debit'])->default('cash');
            $table->date('payment_date');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable(); // Catatan tambahan (misal: "Transfer ke BCA", "Sisa kurang 50rb")
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('membership_transactions');
    }
};
