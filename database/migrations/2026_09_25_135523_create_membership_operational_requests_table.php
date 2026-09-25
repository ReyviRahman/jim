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
        Schema::create('membership_operational_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('submission_token')->unique();
            $table->foreignId('requested_by')->constrained('users');
            $table->string('requested_by_name');
            $table->string('status')->default('pending')->index();
            $table->text('reason');
            $table->dateTime('requested_at');
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->dateTime('decided_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->json('snapshot');
            $table->json('documents');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('membership_operational_requests');
    }
};
