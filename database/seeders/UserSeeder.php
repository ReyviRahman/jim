<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('id_ID'); // Pakai data orang Indonesia
        $password = Hash::make('12345678'); // Default password semua akun: 'password'

        // ==========================================
        // 1. BUAT 1 ADMIN
        // ==========================================
        User::create([
            'name' => 'Super Admin',
            'email' => 'admin@gmail.com', // Email untuk login admin
            'password' => $password,
            'role' => 'admin',
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

        // ==========================================
        // 2. BUAT 5 PERSONAL TRAINER (PT)
        // ==========================================
        $ptSpecialties = [
            'Strength Coach', 
            'Yoga Instructor', 
            'Cardio Specialist', 
            'Bodybuilding Coach', 
            'Crossfit Trainer'
        ];

        foreach ($ptSpecialties as $index => $spec) {
            $gender = $faker->randomElement(['Laki-laki', 'Perempuan']);
            
            User::create([
                'name' => 'Coach ' . $faker->firstName($gender == 'Laki-laki' ? 'male' : 'female'),
                'email' => 'pt' . ($index + 1) . '@gmail.com', // pt1@gmail.com, pt2@gmail.com, dst
                'password' => $password,
                'role' => 'pt',
                'occupation' => $spec, // Pekerjaan sesuai spesialisasi
                'age' => $faker->numberBetween(23, 40),
                'gender' => $gender,
                'phone' => $faker->phoneNumber,
                'medical_history' => null, // PT biasanya sehat
                'address' => $faker->address,
                'joined_at' => Carbon::now()->subMonths($faker->numberBetween(5, 24)),
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
        }

        // ==========================================
        // 3. BUAT 5 MEMBER
        // ==========================================
        for ($i = 1; $i <= 5; $i++) {
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
