<?php

namespace Tests\Feature;

use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class AdminMembershipAdminFeeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_fee_defaults_to_zero_when_a_membership_is_created(): void
    {
        $user = $this->createUser();
        $package = $this->createGymPackage();

        $membership = Membership::create([
            'user_id' => $user->id,
            'type' => 'membership',
            'gym_package_id' => $package->id,
            'base_price' => 300000,
            'price_paid' => 300000,
            'total_paid' => 300000,
            'payment_status' => 'paid',
            'start_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        $this->assertSame('0', $membership->fresh()->admin_fee);
    }

    public function test_package_form_adds_admin_fee_to_the_total_bill(): void
    {
        $user = $this->createUser();
        $package = $this->createGymPackage();
        Livewire::withQueryParams(['users' => [$user->id]])
            ->test('pages::dashboard.admin.membership.paket')
            ->set('registration_type', 'membership')
            ->set('gym_package_id', $package->id)
            ->set('admin_fee', 20000)
            ->assertSet('price_paid', 270000);
    }

    public function test_package_form_rejects_a_negative_admin_fee(): void
    {
        $user = $this->createUser();
        $package = $this->createGymPackage();

        $this->packageForm($user, $package)
            ->set('admin_fee', -1)
            ->call('save')
            ->assertHasErrors(['admin_fee' => ['min']]);
    }

    public function test_package_form_rejects_a_non_numeric_admin_fee(): void
    {
        $user = $this->createUser();
        $package = $this->createGymPackage();

        $this->packageForm($user, $package)
            ->set('admin_fee', 'biaya')
            ->call('save')
            ->assertHasErrors(['admin_fee' => ['numeric']]);
    }

    public function test_edit_form_recalculates_total_with_persisted_admin_fee(): void
    {
        $membership = $this->createMembership();

        Livewire::test('pages::dashboard.admin.membership.edit', ['id' => $membership->id])
            ->set('admin_fee', 20000)
            ->assertSet('price_paid', 270000);
    }

    public function test_edit_form_converts_an_empty_admin_fee_to_zero(): void
    {
        $membership = $this->createMembership();

        Livewire::test('pages::dashboard.admin.membership.edit', ['id' => $membership->id])
            ->set('admin_fee', '')
            ->assertSet('admin_fee', 0)
            ->assertSet('price_paid', 250000);
    }

    public function test_renewal_form_adds_admin_fee_to_the_total_bill(): void
    {
        $membership = $this->createMembership();

        Livewire::test('pages::dashboard.admin.renew.create', ['id' => $membership->id])
            ->set('admin_fee', 20000)
            ->assertSet('price_paid', 270000);
    }

    private function createMembership(): Membership
    {
        $user = $this->createUser();
        $package = $this->createGymPackage();

        $membership = Membership::create([
            'user_id' => $user->id,
            'type' => 'membership',
            'gym_package_id' => $package->id,
            'base_price' => 300000,
            'discount_applied' => 50000,
            'price_paid' => 250000,
            'total_paid' => 250000,
            'payment_status' => 'paid',
            'start_date' => now()->toDateString(),
            'membership_end_date' => now()->addMonth()->toDateString(),
            'status' => 'active',
        ]);

        $membership->members()->attach($user);

        return $membership;
    }

    private function createGymPackage(): GymPackage
    {
        return GymPackage::create([
            'type' => 'gym',
            'name' => 'Paket Gym',
            'category' => 'single',
            'max_members' => 1,
            'price' => 300000,
            'discount' => 50000,
            'is_active' => true,
        ]);
    }

    private function createUser(): User
    {
        return User::factory()->create([
            'age' => 30,
            'gender' => 'Laki-laki',
            'phone' => fake()->unique()->numerify('08##########'),
        ]);
    }

    private function packageForm(User $user, GymPackage $package): Testable
    {
        return Livewire::withQueryParams(['users' => [$user->id]])
            ->test('pages::dashboard.admin.membership.paket')
            ->set('registration_type', 'membership')
            ->set('gym_package_id', $package->id)
            ->set('admin_id', $user->id)
            ->set('follow_up_id', $user->id)
            ->set('follow_up_id_two', $user->id)
            ->set('transaction_type', 'MEMBERSHIP BARU')
            ->set('package_name', 'PAKET GYM')
            ->set('notes', 'Catatan');
    }
}
