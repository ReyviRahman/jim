<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_redirects_guests_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_home_is_accessible_after_login(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'member']))
            ->get('/')
            ->assertOk();
    }

    public function test_home_page_hides_wira_and_melvin_from_the_trainer_list(): void
    {
        User::factory()->create(['name' => 'Wira', 'role' => 'pt', 'is_active' => true, ...$this->trainerProfile()]);
        User::factory()->create(['name' => 'Melvin', 'role' => 'pt', 'is_active' => true, ...$this->trainerProfile()]);
        User::factory()->create(['name' => 'Andi', 'role' => 'pt', 'is_active' => true, ...$this->trainerProfile()]);

        $this->actingAs(User::factory()->create(['role' => 'member']))->get('/')
            ->assertOk()
            ->assertDontSee('Wira')
            ->assertDontSee('Melvin')
            ->assertSee('Andi');
    }

    /**
     * @return array{age: int, gender: string, phone: string}
     */
    private function trainerProfile(): array
    {
        return [
            'age' => 30,
            'gender' => 'Laki-laki',
            'phone' => fake()->unique()->numerify('08##########'),
        ];
    }
}
