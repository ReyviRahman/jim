<?php

namespace Tests\Feature;

use App\Models\GymPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPackagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_groups_active_packages_without_displaying_prices(): void
    {
        $visit = $this->createPackage('visit', 'Daily Test Visit', 113701);
        $gym = $this->createPackage('gym', 'Monthly Test Gym', 229903);
        $pt = $this->createPackage('pt', 'Couple Test PT', 448807, ['category' => 'couple', 'pt_sessions' => 10]);
        $inactive = $this->createPackage('gym', 'Hidden Test Package', 999901, ['is_active' => false]);

        $this->actingAs(User::factory()->create(['role' => 'member']))
            ->get(route('home'))
            ->assertOk()
            ->assertSeeInOrder(['Gym Reguler', 'Private Gym 1-1', 'Kunjungan Harian', $visit->name, 'Membership Gym', $gym->name, 'Personal Trainer', $pt->name])
            ->assertSee('Berdua')
            ->assertSee('10 sesi')
            ->assertDontSee($inactive->name)
            ->assertDontSee('113701')
            ->assertDontSee('229903')
            ->assertDontSee('448807');
    }

    public function test_landing_page_shows_areas_and_empty_message_without_active_packages(): void
    {
        GymPackage::query()->update(['is_active' => false]);

        $this->actingAs(User::factory()->create(['role' => 'member']))
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Gym Reguler')
            ->assertSee('Private Gym 1-1')
            ->assertSee('Belum ada paket yang tersedia saat ini.');
    }

    /** @param array<string, mixed> $attributes */
    private function createPackage(string $type, string $name, int $price, array $attributes = []): GymPackage
    {
        return GymPackage::create([
            'type' => $type,
            'name' => $name,
            'category' => 'single',
            'max_members' => 1,
            'price' => $price,
            'is_active' => true,
            ...$attributes,
        ]);
    }
}
