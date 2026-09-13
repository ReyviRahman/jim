<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_waivers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('member_name');
            $table->boolean('accepted')->default(false);
            $table->string('signature_path')->nullable();
            $table->timestamp('recorded_at');
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('terms_snapshot');
            $table->string('consent_label');
            $table->timestamps();
            $table->unique(['membership_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_waivers');
    }
};
