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

    public function test_membership_package_column_appears_immediately_after_member_name(): void
    {
        $admin = $this->createUser('admin');
        $staffUser = $this->createUser('sales');

        $this->actingAs($admin);

        Livewire::test('pages::dashboard.admin.rekap-bonus.detail', ['user' => $staffUser])
            ->assertSeeInOrder([
                'No',
                'Nama Member',
                'Paket Membership',
                'Nominal',
                'Nominal Akhir',
                'Follow Up 1',
                'Follow Up 2',
                'Tgl Mulai',
            ])
            ->assertSeeHtml('<table class="block w-full table-fixed')
            ->assertSeeHtml('<col class="w-[3%]">')
            ->assertSeeHtml('<col class="w-[12%]">')
            ->assertSeeHtml('<col class="w-[6%]">');
    }

    public function test_mobile_bonus_cards_display_labels_for_every_table_value(): void
    {
        $admin = $this->createUser('admin');
        $staffUser = $this->createUser('sales');
        $member = $this->createUser('member');
        $membership = $this->createPaidMembership($member, $admin, $staffUser);

        $this->createTransaction($membership, $member, $admin, $staffUser, '2026-07-17');
        $this->actingAs($admin);

        Livewire::test('pages::dashboard.admin.rekap-bonus.detail', ['user' => $staffUser])
            ->call('setDateRange', '2026-07-01 to 2026-07-31')
            ->assertSeeHtml([
                '<span class="font-medium text-gray-500 xl:hidden">No</span>',
                '<span class="font-medium text-gray-500 xl:hidden">Nama Member</span>',
                '<span class="font-medium text-gray-500 xl:hidden">Paket Membership</span>',
                '<span class="font-medium text-gray-500 xl:hidden">Nominal</span>',
                '<span class="font-medium text-gray-500 xl:hidden">Nominal Akhir</span>',
                '<span class="font-medium text-gray-500 xl:hidden">Follow Up 1</span>',
                '<span class="font-medium text-gray-500 xl:hidden">Follow Up 2</span>',
                '<span class="font-medium text-gray-500 xl:hidden">Tgl Mulai</span>',
                '<span class="font-medium text-gray-500 xl:hidden">Tgl Selesai</span>',
                '<span class="font-medium text-gray-500 xl:hidden">Tgl Bayar</span>',
                '<span class="font-medium text-gray-500 xl:hidden">Aksi</span>',
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

    public function test_same_gym_cashier_receives_full_nominal_for_unrecommended_price(): void
    {
        $gymCashier = $this->createUser('kasir_gym');
        $membership = $this->makeMembership($gymCashier, $gymCashier->id, [
            'price_paid' => 1_100_000,
            'total_paid' => 1_100_000,
        ]);

        $this->assertSame(1_100_000.0, $membership->calculateNominalAkhir());
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

    public function test_detail_page_displays_full_nominal_and_total_for_same_gym_cashier(): void
    {
        $admin = $this->createUser('admin');
        $gymCashier = $this->createUser('kasir_gym');
        $member = $this->createUser('member');

        $membership = Membership::create([
            'user_id' => $member->id,
            'type' => 'membership',
            'admin_id' => $admin->id,
            'follow_up_id' => $gymCashier->id,
            'follow_up_id_two' => $gymCashier->id,
            'base_price' => 1_600_000,
            'discount_applied' => 500_000,
            'normal_price' => 1_600_000,
            'net_price' => 1_400_000,
            'unrecommended_price' => 1_100_000,
            'price_paid' => 1_100_000,
            'total_paid' => 1_100_000,
            'payment_status' => 'paid',
            'start_date' => '2026-08-22',
            'status' => 'active',
            'transaction_type' => 'MEMBERSHIP',
            'package_name' => 'Test Gym Cashier',
        ]);

        $membership->transactions()->create([
            'invoice_number' => 'INV-REKAP-BONUS-KASIR-TEST',
            'user_id' => $member->id,
            'admin_id' => $admin->id,
            'follow_up_id' => $gymCashier->id,
            'follow_up_id_two' => $gymCashier->id,
            'transaction_type' => 'MEMBERSHIP',
            'package_name' => 'Test Gym Cashier',
            'amount' => 1_100_000,
            'payment_method' => 'cash',
            'payment_date' => '2026-08-22',
        ]);

        $this->actingAs($admin);

        Livewire::test('pages::dashboard.admin.rekap-bonus.detail', ['user' => $gymCashier])
            ->call('setDateRange', '2026-08-01 to 2026-08-31')
            ->assertSeeInOrder([
                'Nominal Akhir',
                'Rp 1.100.000',
                'Total Keseluruhan:',
                'Rp 1.100.000',
            ])
            ->assertDontSee('Rp 550.000');
    }

    public function test_detail_page_only_displays_payment_dates_inside_selected_target_period(): void
    {
        $admin = $this->createUser('admin');
        $staffUser = $this->createUser('sales');
        $julyMember = $this->createUser('member');
        $augustMember = $this->createUser('member');
        $mixedPeriodMember = $this->createUser('member');

        $julyMembership = $this->createPaidMembership($julyMember, $admin, $staffUser);
        $augustMembership = $this->createPaidMembership($augustMember, $admin, $staffUser);
        $mixedPeriodMembership = $this->createPaidMembership($mixedPeriodMember, $admin, $staffUser);

        $this->createTransaction($julyMembership, $julyMember, $admin, $staffUser, '2026-07-17');
        $this->createTransaction($augustMembership, $augustMember, $admin, $staffUser, '2026-08-17');
        $this->createTransaction($mixedPeriodMembership, $mixedPeriodMember, $admin, $staffUser, '2026-07-17');
        $this->createTransaction($mixedPeriodMembership, $mixedPeriodMember, $admin, $staffUser, '2026-08-17');

        $this->actingAs($admin);

        Livewire::test('pages::dashboard.admin.rekap-bonus.detail', ['user' => $staffUser])
            ->call('setDateRange', '2026-07-01 to 2026-07-31')
            ->assertSee($julyMember->name)
            ->assertDontSee($augustMember->name)
            ->assertDontSee($mixedPeriodMember->name)
            ->assertSee('17 July 2026')
            ->assertDontSee('17 August 2026');
    }

    public function test_pt_bonus_detail_only_displays_pt_membership_type(): void
    {
        $admin = $this->createUser('admin');
        $coach = $this->createUser('pt');
        $ptMember = $this->createUser('member');
        $gymMember = $this->createUser('member');

        $ptMembership = $this->createPaidMembership($ptMember, $admin, $coach, 'pt');
        $gymMembership = $this->createPaidMembership($gymMember, $admin, $coach);

        $this->createTransaction($ptMembership, $ptMember, $admin, $coach, '2026-07-17');
        $this->createTransaction($gymMembership, $gymMember, $admin, $coach, '2026-07-17');

        $this->actingAs($admin);

        Livewire::test('pages::dashboard.admin.rekap-bonus.detail', ['user' => $coach])
            ->call('setDateRange', '2026-07-01 to 2026-07-31')
            ->assertSee($ptMember->name)
            ->assertDontSee($gymMember->name);
    }

    private function createPaidMembership(
        User $member,
        User $admin,
        User $staffUser,
        string $type = 'membership'
    ): Membership {
        return Membership::create([
            'user_id' => $member->id,
            'type' => $type,
            'admin_id' => $admin->id,
            'follow_up_id' => $staffUser->id,
            'base_price' => 500_000,
            'price_paid' => 500_000,
            'total_paid' => 500_000,
            'payment_status' => 'paid',
            'start_date' => '2026-07-17',
            'status' => 'active',
            'transaction_type' => 'MEMBERSHIP',
            'package_name' => 'Test Target Period',
        ]);
    }

    private function createTransaction(
        Membership $membership,
        User $member,
        User $admin,
        User $staffUser,
        string $paymentDate
    ): void {
        $membership->transactions()->create([
            'invoice_number' => 'INV-'.$membership->id.'-'.$paymentDate,
            'user_id' => $member->id,
            'admin_id' => $admin->id,
            'follow_up_id' => $staffUser->id,
            'transaction_type' => 'MEMBERSHIP',
            'package_name' => 'Test Target Period',
            'amount' => 500_000,
            'payment_method' => 'cash',
            'payment_date' => $paymentDate,
        ]);
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
