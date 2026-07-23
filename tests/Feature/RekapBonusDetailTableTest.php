<?php

namespace Tests\Feature;

use App\Models\Membership;
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

    public function test_same_coach_receives_full_nominal_for_unrecommended_price(): void
    {
        $coach = $this->createUser('pt');
        $membership = $this->makeMembership($coach, $coach->id, [
            'price_paid' => 1_100_000,
            'total_paid' => 1_100_000,
        ]);

        $this->assertSame(1_100_000.0, $membership->calculateNominalAkhir());
    }

    public function test_same_coach_receives_full_nominal_for_recommended_price(): void
    {
        $coach = $this->createUser('pt');
        $membership = $this->makeMembership($coach, $coach->id, [
            'price_paid' => 1_400_000,
            'total_paid' => 1_400_000,
        ]);

        $this->assertSame(1_400_000.0, $membership->calculateNominalAkhir());
    }

    public function test_same_non_coach_still_receives_half_for_unrecommended_price(): void
    {
        $sales = $this->createUser('sales');
        $membership = $this->makeMembership($sales, $sales->id, [
            'price_paid' => 1_100_000,
            'total_paid' => 1_100_000,
        ]);

        $this->assertSame(550_000.0, $membership->calculateNominalAkhir());
    }

    public function test_different_follow_ups_still_split_recommended_nominal(): void
    {
        $firstCoach = $this->createUser('pt');
        $secondCoach = $this->createUser('pt');
        $membership = $this->makeMembership($firstCoach, $secondCoach->id, [
            'price_paid' => 1_400_000,
            'total_paid' => 1_400_000,
        ]);

        $this->assertSame(700_000.0, $membership->calculateNominalAkhir());
    }

    public function test_detail_page_displays_full_nominal_and_total_for_same_coach(): void
    {
        $admin = $this->createUser('admin');
        $coach = $this->createUser('pt');
        $member = $this->createUser('member');

        $membership = Membership::create([
            'user_id' => $member->id,
            'type' => 'pt',
            'pt_id' => $coach->id,
            'admin_id' => $admin->id,
            'follow_up_id' => $coach->id,
            'follow_up_id_two' => $coach->id,
            'base_price' => 1_600_000,
            'discount_applied' => 500_000,
            'normal_price' => 1_600_000,
            'net_price' => 1_400_000,
            'unrecommended_price' => 1_100_000,
            'price_paid' => 1_100_000,
            'total_paid' => 1_100_000,
            'payment_status' => 'paid',
            'start_date' => now()->toDateString(),
            'status' => 'active',
            'transaction_type' => 'PT',
            'package_name' => 'Test Coach',
        ]);

        $membership->transactions()->create([
            'invoice_number' => 'INV-REKAP-BONUS-TEST',
            'user_id' => $member->id,
            'admin_id' => $admin->id,
            'follow_up_id' => $coach->id,
            'follow_up_id_two' => $coach->id,
            'transaction_type' => 'PT',
            'package_name' => 'Test Coach',
            'amount' => 1_100_000,
            'payment_method' => 'cash',
            'payment_date' => now()->toDateString(),
        ]);

        $this->actingAs($admin);

        Livewire::test('pages::dashboard.admin.rekap-bonus.detail', ['user' => $coach])
            ->assertSee('Rp 1.100.000')
            ->assertDontSee('Rp 550.000');
    }

    /**
     * @param  array<string, int>  $overrides
     */
    private function makeMembership(User $followUp, int $followUpTwoId, array $overrides = []): Membership
    {
        $membership = new Membership([
            'follow_up_id' => $followUp->id,
            'follow_up_id_two' => $followUpTwoId,
            'base_price' => 1_600_000,
            'normal_price' => 1_600_000,
            'net_price' => 1_400_000,
            'unrecommended_price' => 1_100_000,
            'price_paid' => 1_100_000,
            'total_paid' => 1_100_000,
            ...$overrides,
        ]);
        $membership->setRelation('followUp', $followUp);

        return $membership;
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
