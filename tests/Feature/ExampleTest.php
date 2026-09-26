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

    public function test_login_shows_join_now_registration_link(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Join Now')
            ->assertSee(route('member.register'), false)
            ->assertDontSee('Belum punya akun?');
    }

    public function test_home_is_accessible_after_login(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'member']))
            ->get('/')
            ->assertRedirect(route('member.dashboard'));
    }

    public function test_home_redirects_authenticated_roles_to_their_dashboards(): void
    {
        foreach (['member', 'pt', 'admin', 'kasir_gym', 'kasir_minum'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->get(route('home'))
                ->assertRedirect(route($user->dashboardRoute()));
        }

        $headCoach = User::factory()->headCoach()->create();

        $this->actingAs($headCoach)
            ->get(route('home'))
            ->assertRedirect(route('admin.cicilan.index'));
    }

    public function test_home_redirects_instead_of_rendering_the_trainer_list(): void
    {
        User::factory()->create(['name' => 'Wira', 'role' => 'pt', 'is_active' => true, ...$this->trainerProfile()]);
        User::factory()->create(['name' => 'Melvin', 'role' => 'pt', 'is_active' => true, ...$this->trainerProfile()]);
        User::factory()->create(['name' => 'Andi', 'role' => 'pt', 'is_active' => true, ...$this->trainerProfile()]);

        $this->actingAs(User::factory()->create(['role' => 'member']))
            ->get(route('home'))
            ->assertRedirect(route('member.dashboard'))
            ->assertDontSee('Wira')
            ->assertDontSee('Melvin')
            ->assertDontSee('Andi');
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
