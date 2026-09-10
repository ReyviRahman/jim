<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('shift')->nullable()->default(null)->change();
            $table->foreign('shift')->references('id')->on('shifts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['shift']);
            $table->dropIndex('users_shift_foreign');
            $table->string('shift')->nullable()->default(null)->change();
        });
    }
};
