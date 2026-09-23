<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beverage_invoices', function (Blueprint $table): void {
            $table->string('image_path')->nullable();
            $table->timestamp('stock_posted_at')->nullable();
        });
        Schema::table('beverage_invoice_items', function (Blueprint $table): void {
            $table->foreignId('beverage_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('total_pcs')->nullable();
        });
        Schema::table('beverage_restocks', function (Blueprint $table): void {
            $table->foreignId('beverage_invoice_item_id')->nullable()->unique()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('beverage_restocks', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('beverage_invoice_item_id');
        });
        Schema::table('beverage_invoice_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('beverage_id');
            $table->dropColumn('total_pcs');
        });
        Schema::table('beverage_invoices', function (Blueprint $table): void {
            $table->dropColumn(['image_path', 'stock_posted_at']);
        });
    }
};
