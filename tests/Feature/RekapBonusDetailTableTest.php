<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RekapBonusDetailTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_key_bonus_columns_appear_immediately_after_number_column(): void
    {
        $admin = $this->createUser('admin');
        $staffUser = $this->createUser('sales');

        $this->actingAs($admin);

        Livewire::test('pages::dashboard.admin.rekap-bonus.detail', ['user' => $staffUser])
            ->assertSeeInOrder([
                'No',
                'Nama Member',
                'Nominal',
                'Nominal Akhir',
                'Follow Up 1',
                'Follow Up 2',
                'Tgl Mulai',
            ]);
    }

    private function createUser(string $role): User
    {
        return User::factory()->create([
            'age' => 30,
            'gender' => 'Laki-laki',
            'phone' => fake()->unique()->numerify('08##########'),
            'role' => $role,
        ]);
    }
}
