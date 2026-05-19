<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');

            Schema::rename('pt_bookings', 'pt_bookings_old');

            Schema::create('pt_bookings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('membership_id')->constrained('memberships')->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('pt_id')->constrained('users')->cascadeOnDelete();
                $table->date('booking_date');
                $table->time('booking_time');
                $table->enum('status', ['pending', 'approved', 'cancelled', 'rejected'])->default('pending');
                $table->enum('type', ['fleksibel', 'keep'])->default('fleksibel');
                $table->text('notes')->nullable();
                $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('cancelled_at')->nullable();
                $table->text('cancellation_reason')->nullable();
                $table->timestamps();
            });

            DB::statement('INSERT INTO pt_bookings SELECT * FROM pt_bookings_old');

            Schema::drop('pt_bookings_old');

            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            DB::statement("ALTER TABLE pt_bookings MODIFY COLUMN status ENUM('pending', 'approved', 'cancelled', 'rejected') NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');

            Schema::rename('pt_bookings', 'pt_bookings_old');

            Schema::create('pt_bookings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('membership_id')->constrained('memberships')->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('pt_id')->constrained('users')->cascadeOnDelete();
                $table->date('booking_date');
                $table->time('booking_time');
                $table->enum('status', ['pending', 'approved', 'cancelled'])->default('pending');
                $table->enum('type', ['fleksibel', 'keep'])->default('fleksibel');
                $table->text('notes')->nullable();
                $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('cancelled_at')->nullable();
                $table->text('cancellation_reason')->nullable();
                $table->timestamps();
            });

            DB::statement('INSERT INTO pt_bookings SELECT * FROM pt_bookings_old');

            Schema::drop('pt_bookings_old');

            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            DB::statement("ALTER TABLE pt_bookings MODIFY COLUMN status ENUM('pending', 'approved', 'cancelled') NOT NULL DEFAULT 'pending'");
        }
    }
};
