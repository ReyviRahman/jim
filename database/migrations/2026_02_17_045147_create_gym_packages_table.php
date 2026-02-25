<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gym_packages', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['gym', 'pt', 'visit'])->default('gym'); 
            $table->string('name'); 
            $table->enum('category', ['single', 'couple', 'group'])->default('single'); 
            $table->integer('max_members')->default(1); 
            $table->integer('pt_sessions')->nullable();
            $table->decimal('price', 12, 0);
            $table->decimal('discount', 12, 0)->default(0);
            $table->text('description')->nullable(); 
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gym_packages');
    }
};