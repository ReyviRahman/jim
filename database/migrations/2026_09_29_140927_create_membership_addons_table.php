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
        Schema::create('membership_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('duration_months')->default(0);
            $table->unsignedInteger('duration_weeks')->default(0);
            $table->unsignedInteger('duration_days')->default(0);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status')->default('pending');
            $table->string('approval_status')->default('pending')->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->index(['status', 'end_date']);
        });
        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('membership_addon_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('membership_addon_id');
        });
        Schema::dropIfExists('membership_addons');
    }
};
