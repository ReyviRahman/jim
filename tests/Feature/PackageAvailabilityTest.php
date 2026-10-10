<?php

namespace Tests\Feature;

use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PackageAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public static function times(): array
    {
        return [
            ['08:59:59', false, false],
            ['09:00:00', true, false],
            ['16:59:59', true, false],
            ['17:00:00', false, false],
            ['22:00:00', false, true],
            ['23:59:59', false, true],
            ['00:00:00', false, true],
            ['01:59:59', false, true],
            ['02:00:00', false, false],
        ];
    }

    #[DataProvider('times')]
    public function test_daily_availability_and_boundaries(string $time, bool $dayAvailable, bool $nightAvailable): void
    {
        $this->travelTo(Carbon::parse('2026-10-10 '.$time, 'Asia/Jakarta'));
        $unrestricted = $this->package();
        $day = $this->package(['available_from' => '09:00', 'available_until' => '17:00']);
        $night = $this->package(['available_from' => '22:00', 'available_until' => '02:00']);
        $ids = GymPackage::availableNow()->pluck('id');
        $this->assertTrue($ids->contains($unrestricted->id));
        $this->assertSame($dayAvailable, $ids->contains($day->id));
        $this->assertSame($nightAvailable, $ids->contains($night->id));
    }

    public function test_master_creates_edits_and_clears_optional_times(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Livewire::test('pages::dashboard.admin.package.create')
            ->set('name', 'Paket Pagi')->set('price', 100000)->set('duration_days', 1)
            ->set('available_from', '09:00')->set('available_until', '17:00')
            ->call('save')->assertHasNoErrors();
        $package = GymPackage::sole();
        $this->assertSame('09:00:00', $package->available_from);
        Livewire::test('pages::dashboard.admin.package.index')->assertSee('09:00 - 17:00 WIB');
        Livewire::test('pages::dashboard.admin.package.edit', ['package' => $package])
            ->assertSet('available_from', '09:00')->assertSet('available_until', '17:00')
            ->set('available_from', '22:00')->set('available_until', '02:00')
            ->call('update')->assertHasNoErrors();
        $this->assertSame('22:00:00', $package->refresh()->available_from);
        Livewire::test('pages::dashboard.admin.package.edit', ['package' => $package])
            ->set('available_from', '')->set('available_until', '')
            ->call('update')->assertHasNoErrors();
        $this->assertNull($package->refresh()->available_from);
        $this->assertNull($package->available_until);
    }

    public static function invalidTimes(): array
    {
        return [
            ['09:00', '', 'available_until'],
            ['', '17:00', 'available_from'],
            ['25:00', '17:00', 'available_from'],
            ['09:00', 'invalid', 'available_until'],
            ['09:00', '09:00', 'available_until'],
        ];
    }

    #[DataProvider('invalidTimes')]
    public function test_master_rejects_incomplete_or_invalid_times(string $from, string $until, string $field): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Livewire::test('pages::dashboard.admin.package.create')
            ->set('name', 'Paket Pagi')->set('price', 100000)->set('duration_days', 1)
            ->set('available_from', $from)->set('available_until', $until)
            ->call('save')->assertHasErrors($field);
        $this->assertDatabaseCount('gym_packages', 0);
        $package = $this->package();
        Livewire::test('pages::dashboard.admin.package.edit', ['package' => $package])
            ->set('available_from', $from)->set('available_until', $until)
            ->call('update')->assertHasErrors($field);
        $this->assertNull($package->refresh()->available_from);
    }

    public static function forms(): array
    {
        return [[false, 'gym'], [true, 'gym'], [false, 'pt'], [true, 'pt'], [false, 'visit'], [true, 'visit']];
    }

    #[DataProvider('forms')]
    public function test_membership_forms_filter_packages_and_recheck_on_save(bool $renew, string $type): void
    {
        $this->travelTo(Carbon::parse('2026-10-10 10:00:00', 'Asia/Jakarta'));
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $member = User::factory()->create(['role' => 'member']);
        $open = $this->package(['type' => $type, 'available_from' => '09:00', 'available_until' => '11:00']);
        $closed = $this->package(['type' => $type, 'available_from' => '12:00', 'available_until' => '13:00']);
        $always = $this->package(['type' => $type]);
        $registration = $type === 'gym' ? 'membership' : $type;
        $field = $type === 'pt' ? 'pt_package_id' : 'gym_package_id';
        $list = $type === 'pt' ? 'ptPackages' : 'gymPackages';
        if ($renew) {
            $old = Membership::create([
                'user_id' => $member->id, 'type' => $registration, $field => $closed->id,
                'base_price' => 100000, 'price_paid' => 100000, 'total_paid' => 100000,
                'payment_status' => 'paid', 'status' => 'active', 'is_active' => false,
                'start_date' => '2026-09-01', 'membership_end_date' => '2026-09-30',
            ]);
            $form = Livewire::test('pages::dashboard.admin.renew.create', ['id' => $old->id]);
            $form->assertSet($field, '');
        } else {
            $form = Livewire::withQueryParams(['users' => [$member->id]])
                ->test('pages::dashboard.admin.membership.paket')
                ->set('registration_type', $registration);
        }
        $ids = $form->get($list)->modelKeys();
        $this->assertContains($open->id, $ids);
        $this->assertContains($always->id, $ids);
        $this->assertNotContains($closed->id, $ids);
        $form->set($field, $open->id);
        $this->travelTo(Carbon::parse('2026-10-10 11:00:00', 'Asia/Jakarta'));
        $form->call('save')->assertHasErrors($field)
            ->assertSee('Paket tidak tersedia pada jam ini.');
        $this->assertDatabaseCount('memberships', $renew ? 1 : 0);
    }

    /** @param array<string, mixed> $attributes */
    private function package(array $attributes = []): GymPackage
    {
        return GymPackage::create(array_replace([
            'name' => 'Paket Uji', 'type' => 'gym', 'category' => 'single', 'max_members' => 1,
            'price' => 100000, 'discount' => 0, 'is_active' => true, 'duration_days' => 1, 'pt_sessions' => 10,
        ], $attributes));
    }
}
