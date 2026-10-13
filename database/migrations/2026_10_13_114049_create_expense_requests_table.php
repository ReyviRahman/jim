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
        Schema::create('expense_requests', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->foreignId('requested_by')->constrained('users');
            $table->string('requested_by_name');
            $table->string('shift')->nullable();
            $table->string('description');
            $table->decimal('amount', 12, 0);
            $table->date('expense_date');
            $table->string('status')->default('pending');
            $table->timestamp('requested_at');
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->timestamp('decided_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('expense_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('beverage_sale_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->index(['source', 'status']);
            $table->index(['requested_by', 'status']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_requests');
    }
};
