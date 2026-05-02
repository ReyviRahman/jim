<?php

namespace Database\Seeders;

use App\Models\User;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
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
            'name' => 'Manager Frans',
            'email' => 'Managerfrans@gmail.com', // Email untuk login admin
            'password' => Hash::make('24022024'),
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
            'name' => 'Dewi',
            'email' => 'dewi@gmail.com', // Email untuk login admin
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
            'name' => 'Laurent',
            'email' => 'laurent@gmail.com', // Email untuk login admin
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

        User::create([
            'name' => 'Coach Tiwi',
            'email' => 'tiwi@gmail.com',
            'password' => $password,
            'role' => 'pt',
            'occupation' => 'Personal Trainer',
            'age' => 30,
            'gender' => 'Perempuan',
            'phone' => '081372225122',
            'medical_history' => null,
            'address' => 'Jambi',
            'joined_at' => Carbon::now()->subYears(2),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // ==========================================
        // 3. BUAT 5 MEMBER
        // ==========================================
        // for ($i = 1; $i <= 20; $i++) {
        //     $gender = $faker->randomElement(['Laki-laki', 'Perempuan']);

        //     User::create([
        //         'name' => $faker->name($gender == 'Laki-laki' ? 'male' : 'female'),
        //         'email' => 'member' . $i . '@gmail.com', // member1@gmail.com, dst
        //         'password' => $password,
        //         'role' => 'member',
        //         'occupation' => $faker->jobTitle, // Pekerjaan acak
        //         'age' => $faker->numberBetween(18, 55),
        //         'gender' => $gender,
        //         'phone' => $faker->phoneNumber,
        //         'medical_history' => $faker->optional(0.3)->sentence, // 30% kemungkinan punya riwayat sakit
        //         'address' => $faker->address,
        //         'joined_at' => Carbon::now()->subDays($faker->numberBetween(1, 100)),
        //         'is_active' => true, // Aktifkan supaya bisa langsung login
        //         'email_verified_at' => now(),
        //     ]);
        // }
    }
}
