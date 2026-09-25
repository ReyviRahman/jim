<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CoachDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_renders_coaches_and_filters_by_name(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $coach = User::factory()->create(['role' => 'pt', 'name' => 'Coach Aditya', 'is_active' => true, 'photo' => null]);
        User::factory()->create(['role' => 'pt', 'name' => 'Coach Bintang', 'is_active' => false]);
        User::factory()->create(['role' => 'member', 'name' => 'Member Rahasia']);

        $this->actingAs($admin)->get(route('admin.sesi-pt.index'))
            ->assertOk()->assertSee('coach-directory-card')->assertSee('Member Aktif');

        Livewire::test('pages::dashboard.admin.sesi-pt.index')
            ->assertSee('Coach Aditya')->assertDontSee('Coach Bintang')
            ->assertDontSee('Nonaktif')->assertDontSee('Member Rahasia')
            ->assertSee(route('admin.sesi-pt.detail', $coach), false)
            ->set('search', 'Aditya')->assertSee('Coach Aditya')->assertDontSee('Coach Bintang')
            ->set('search', 'TidakDitemukan')->assertSee('Tidak ada coach yang cocok dengan pencarian.')
            ->set('search', 'Bintang')->assertDontSee('Coach Bintang')->assertSee('Tidak ada coach yang cocok dengan pencarian.')
            ->set('search', '')->assertSee('Coach Aditya')->assertDontSee('Coach Bintang');
    }

    public function test_directory_has_an_empty_state_without_coaches(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test('pages::dashboard.admin.sesi-pt.index')
            ->assertSee('Belum ada data personal trainer.');
    }
}
