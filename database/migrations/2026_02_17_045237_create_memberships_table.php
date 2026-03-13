<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); 
            $table->enum('type', ['membership', 'pt', 'bundle_pt_membership', 'visit'])->index()->default('membership'); 
            $table->foreignId('gym_package_id')->nullable()->constrained('gym_packages'); 
            $table->foreignId('pt_package_id')->nullable()->constrained('gym_packages'); 
            $table->foreignId('pt_id')->nullable()->constrained('users'); 
            $table->foreignId('admin_id')->nullable()->constrained('users');
            $table->foreignId('follow_up_id')->nullable()->constrained('users');
            $table->foreignId('follow_up_id_two')->nullable()->constrained('users');
            $table->decimal('base_price', 12, 0); 
            $table->decimal('discount_applied', 12, 0)->default(0); 
            $table->decimal('net_price', 12, 0)->nullable(); 
            $table->decimal('unrecommended_price', 12, 0)->nullable();
            $table->decimal('price_paid', 12, 0); 
            $table->decimal('total_paid', 12, 0); 
            $table->enum('payment_status', ['unpaid', 'partial', 'paid'])->default('unpaid');
            $table->integer('total_sessions')->nullable(); 
            $table->integer('remaining_sessions')->nullable(); 
            $table->date('pt_end_date')->nullable(); 
            $table->date('membership_end_date')->nullable(); 
            $table->string('member_goal')->nullable();
            $table->date('start_date'); 
            $table->enum('status', ['pending', 'active', 'rejected', 'completed'])->index()->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};