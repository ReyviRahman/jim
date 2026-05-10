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
        Schema::table('pt_schedules', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->after('type');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null')->after('status');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null')->after('created_by');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pt_schedules', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['status', 'created_by', 'approved_by', 'approved_at']);
        });
    }
};
