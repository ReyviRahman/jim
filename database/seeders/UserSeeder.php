<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('id_ID'); // Pakai data orang Indonesia
        $password = Hash::make('12345678'); // Default password semua akun: 'password'

        $packages = [
            ['id' => 1, 'type' => 'visit', 'name' => 'Daily Pass', 'category' => 'single', 'max_members' => 1, 'pt_sessions' => null, 'price' => 65000, 'discount' => 0, 'description' => null, 'is_active' => 1, 'created_at' => '2026-02-26 09:17:59', 'updated_at' => '2026-02-26 09:17:59'],
            ['id' => 2, 'type' => 'gym', 'name' => 'Membership Weekly Pass', 'category' => 'single', 'max_members' => 1, 'pt_sessions' => null, 'price' => 129000, 'discount' => 0, 'description' => null, 'is_active' => 1, 'created_at' => '2026-02-26 09:20:51', 'updated_at' => '2026-02-26 09:20:51'],
            ['id' => 3, 'type' => 'gym', 'name' => 'Membership 1 Monthly Pass', 'category' => 'single', 'max_members' => 1, 'pt_sessions' => null, 'price' => 300000, 'discount' => 0, 'description' => null, 'is_active' => 1, 'created_at' => '2026-02-26 09:25:22', 'updated_at' => '2026-02-26 09:25:44'],
            ['id' => 4, 'type' => 'gym', 'name' => 'Membership 2 Monthly Pass', 'category' => 'single', 'max_members' => 1, 'pt_sessions' => null, 'price' => 500000, 'discount' => 0, 'description' => null, 'is_active' => 1, 'created_at' => '2026-02-26 09:26:00', 'updated_at' => '2026-02-26 09:26:00'],
            ['id' => 5, 'type' => 'gym', 'name' => 'Membership 3 Monthly Pass', 'category' => 'single', 'max_members' => 1, 'pt_sessions' => null, 'price' => 750000, 'discount' => 0, 'description' => null, 'is_active' => 1, 'created_at' => '2026-02-26 09:26:23', 'updated_at' => '2026-02-26 09:26:23'],
            ['id' => 6, 'type' => 'gym', 'name' => 'Membership 6 Monthly Pass', 'category' => 'single', 'max_members' => 1, 'pt_sessions' => null, 'price' => 1500000, 'discount' => 0, 'description' => null, 'is_active' => 1, 'created_at' => '2026-02-26 09:26:48', 'updated_at' => '2026-02-26 09:26:48'],
            ['id' => 7, 'type' => 'gym', 'name' => 'Membership Yearly Pass', 'category' => 'single', 'max_members' => 1, 'pt_sessions' => null, 'price' => 2700000, 'discount' => 0, 'description' => null, 'is_active' => 1, 'created_at' => '2026-02-26 09:27:07', 'updated_at' => '2026-02-26 09:27:07'],
            ['id' => 8, 'type' => 'pt', 'name' => 'Personal Trainer Single 1 Session', 'category' => 'single', 'max_members' => 1, 'pt_sessions' => 1, 'price' => 200000, 'discount' => 0, 'description' => null, 'is_active' => 1, 'created_at' => '2026-02-26 09:27:32', 'updated_at' => '2026-02-26 09:27:32'],
            ['id' => 9, 'type' => 'pt', 'name' => 'Personal Trainer Single 5 Sessions', 'category' => 'single', 'max_members' => 1, 'pt_sessions' => 5, 'price' => 900000, 'discount' => 0, 'description' => null, 'is_active' => 1, 'created_at' => '2026-02-26 09:27:58', 'updated_at' => '2026-02-26 09:29:13'],
            ['id' => 10, 'type' => 'pt', 'name' => 'Personal Trainer Single 10 Sessions', 'category' => 'single', 'max_members' => 1, 'pt_sessions' => 10, 'price' => 1600000, 'discount' => 0, 'description' => null, 'is_active' => 1, 'created_at' => '2026-02-26 09:29:00', 'updated_at' => '2026-02-26 09:29:00'],
            ['id' => 11, 'type' => 'pt', 'name' => 'Personal Trainer Single 20 Sessions', 'category' => 'single', 'max_members' => 1, 'pt_sessions' => 20, 'price' => 2800000, 'discount' => 0, 'description' => null, 'is_active' => 1, 'created_at' => '2026-02-26 09:29:40', 'updated_at' => '2026-02-26 09:29:40'],
            ['id' => 12, 'type' => 'pt', 'name' => 'Personal Trainer Couple 1 Session', 'category' => 'couple', 'max_members' => 2, 'pt_sessions' => 1, 'price' => 250000, 'discount' => 0, 'description' => null, 'is_active' => 1, 'created_at' => '2026-02-26 09:30:12', 'updated_at' => '2026-02-26 09:32:08'],
            ['id' => 13, 'type' => 'pt', 'name' => 'Personal Trainer Couple 5 Sessions', 'category' => 'couple', 'max_members' => 2, 'pt_sessions' => 5, 'price' => 1000000, 'discount' => 0, 'description' => null, 'is_active' => 1, 'created_at' => '2026-02-26 09:30:33', 'updated_at' => '2026-02-26 09:32:16'],
            ['id' => 14, 'type' => 'pt', 'name' => 'Personal Trainer Couple 10 Sessions', 'category' => 'couple', 'max_members' => 2, 'pt_sessions' => 10, 'price' => 2100000, 'discount' => 0, 'description' => null, 'is_active' => 1, 'created_at' => '2026-02-26 09:30:59', 'updated_at' => '2026-02-26 09:32:23'],
            ['id' => 15, 'type' => 'pt', 'name' => 'Personal Trainer Couple 20 Sessions', 'category' => 'couple', 'max_members' => 2, 'pt_sessions' => 20, 'price' => 3800000, 'discount' => 0, 'description' => null, 'is_active' => 1, 'created_at' => '2026-02-26 09:31:24', 'updated_at' => '2026-02-26 09:32:28'],
        ];

        DB::table('gym_packages')->insert($packages);

        // ==========================================
        // 1. BUAT 1 ADMIN
        // ==========================================
        User::create([
            'name' => 'Super Admin',
            'email' => 'admin@gmail.com', // Email untuk login admin
            'password' => $password,
            'role' => 'admin',
            'shift' => 'Siang',
            'occupation' => 'Gym Manager',
            'age' => 30,
            'gender' => 'Laki-laki',
            'phone' => '081200001111',
            'medical_history' => null,
            'address' => 'Jl. Admin Pusat No. 1, Jakarta',
            'joined_at' => Carbon::now()->subYears(2),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        
        User::create([
            'name' => 'Ratna',
            'email' => 'ratna@gmail.com', // Email untuk login admin
            'password' => $password,
            'role' => 'kasir_gym',
            'shift' => 'Pagi',
            'occupation' => 'Kasir GYM',
            'age' => 30,
            'gender' => 'Perempuan',
            'phone' => '081234567890',
            'medical_history' => null,
            'address' => 'Jl. Admin Pusat No. 1, Jakarta',
            'joined_at' => Carbon::now()->subYears(2),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        User::create([
            'name' => 'C.Wira',
            'email' => 'wira@gmail.com', // Email untuk login admin
            'password' => $password,
            'role' => 'kasir_gym',
            'shift' => 'Siang',
            'occupation' => 'Kasir GYM',
            'age' => 30,
            'gender' => 'Laki-laki',
            'phone' => '081234567891',
            'medical_history' => null,
            'address' => 'Jl. Admin Pusat No. 1, Jakarta',
            'joined_at' => Carbon::now()->subYears(2),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        User::create([
            'name' => 'Coach Frans',
            'email' => 'frans@gmail.com', 
            'password' => $password,
            'role' => 'pt',
            'occupation' => 'Personal Trainer',
            'age' => 30,
            'gender' => 'Laki-laki',
            'phone' => '081372645212',
            'medical_history' => null,
            'address' => 'Jambi',
            'joined_at' => Carbon::now()->subYears(2),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        User::create([
            'name' => 'Coach Efdi',
            'email' => 'efdi@gmail.com', 
            'password' => $password,
            'role' => 'pt',
            'occupation' => 'Personal Trainer',
            'age' => 30,
            'gender' => 'Laki-laki',
            'phone' => '081371115212',
            'medical_history' => null,
            'address' => 'Jambi',
            'joined_at' => Carbon::now()->subYears(2),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        User::create([
            'name' => 'Coach Yoyok',
            'email' => 'yoyok@gmail.com', 
            'password' => $password,
            'role' => 'pt',
            'occupation' => 'Personal Trainer',
            'age' => 30,
            'gender' => 'Laki-laki',
            'phone' => '081372225212',
            'medical_history' => null,
            'address' => 'Jambi',
            'joined_at' => Carbon::now()->subYears(2),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // ==========================================
        // 3. BUAT 5 MEMBER
        // ==========================================
        for ($i = 1; $i <= 20; $i++) {
            $gender = $faker->randomElement(['Laki-laki', 'Perempuan']);
            
            User::create([
                'name' => $faker->name($gender == 'Laki-laki' ? 'male' : 'female'),
                'email' => 'member' . $i . '@gmail.com', // member1@gmail.com, dst
                'password' => $password,
                'role' => 'member',
                'occupation' => $faker->jobTitle, // Pekerjaan acak
                'age' => $faker->numberBetween(18, 55),
                'gender' => $gender,
                'phone' => $faker->phoneNumber,
                'medical_history' => $faker->optional(0.3)->sentence, // 30% kemungkinan punya riwayat sakit
                'address' => $faker->address,
                'joined_at' => Carbon::now()->subDays($faker->numberBetween(1, 100)),
                'is_active' => true, // Aktifkan supaya bisa langsung login
                'email_verified_at' => now(),
            ]);
        }
    }
}
