<?php

namespace Tests\Feature;

use App\Exports\RekapBonusExport;
use App\Models\CoachKonsultan;
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
            ->assertSeeHtml('<table data-responsive-table data-responsive-explicit-labels data-responsive-breakpoint="sm" class="block w-full table-fixed')
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

    public function test_different_follow_ups_split_every_role_and_price_category(): void
    {
        foreach (['pt', 'kasir_gym', 'sales'] as $role) {
            $firstFollowUp = $this->createUser($role);
            $secondFollowUp = $this->createUser('sales');

            foreach ([
                'recommended' => 1_400_000,
                'unrecommended' => 1_100_000,
            ] as $priceCategory => $amount) {
                $membership = $this->makeMembership($firstFollowUp, $secondFollowUp->id, [
                    'price_paid' => $amount,
                    'total_paid' => $amount,
                ]);

                $this->assertSame(
                    (float) ($amount / 2),
                    $membership->calculateNominalAkhir(),
                    "Role {$role} dengan harga {$priceCategory} harus dibagi dua ketika follow-up berbeda."
                );
            }
        }
    }

    public function test_single_empty_follow_up_keeps_full_recommended_nominal(): void
    {
        $sales = $this->createUser('sales');
        $membership = new Membership([
            'follow_up_id' => $sales->id,
            'follow_up_id_two' => null,
            'base_price' => 1_600_000,
            'normal_price' => 1_600_000,
            'net_price' => 1_400_000,
            'unrecommended_price' => 1_100_000,
            'price_paid' => 1_400_000,
            'total_paid' => 1_400_000,
        ]);
        $membership->setRelation('followUp', $sales);

        $this->assertSame(1_400_000.0, $membership->calculateNominalAkhir());
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

    public function test_pt_bonus_data_includes_pt_and_membership_types_with_both_follow_ups_everywhere(): void
    {
        $admin = $this->createUser('admin');
        $coach = $this->createUser('pt');
        $otherCoach = $this->createUser('pt');
        $eligibleMember = $this->createUser('member');
        $firstOnlyMember = $this->createUser('member');
        $secondOnlyMember = $this->createUser('member');
        $membershipMember = $this->createUser('member');
        $bundleMember = $this->createUser('member');

        $eligiblePtMembership = $this->createPaidMembership($eligibleMember, $admin, $coach, 'pt');
        $firstOnlyMembership = $this->createPaidMembership($firstOnlyMember, $admin, $coach, 'pt', [
            'follow_up_id_two' => $otherCoach->id,
        ]);
        $secondOnlyMembership = $this->createPaidMembership($secondOnlyMember, $admin, $coach, 'pt', [
            'follow_up_id' => $otherCoach->id,
            'follow_up_id_two' => $coach->id,
        ]);
        $eligibleGymMembership = $this->createPaidMembership($membershipMember, $admin, $coach);
        $bundleMembership = $this->createPaidMembership($bundleMember, $admin, $coach, 'bundle_pt_membership');

        $this->createTransaction($eligiblePtMembership, $eligibleMember, $admin, $coach, '2026-07-17');
        $this->createTransaction($firstOnlyMembership, $firstOnlyMember, $admin, $coach, '2026-07-17');
        $this->createTransaction($secondOnlyMembership, $secondOnlyMember, $admin, $coach, '2026-07-17');
        $this->createTransaction($eligibleGymMembership, $membershipMember, $admin, $coach, '2026-07-17');
        $this->createTransaction($bundleMembership, $bundleMember, $admin, $coach, '2026-07-17');
        CoachKonsultan::create([
            'rentang_satu' => '0',
            'rentang_dua' => '60000000',
            'persen' => 2,
        ]);

        $this->actingAs($admin);

        $component = Livewire::test('pages::dashboard.admin.rekap-bonus.detail', ['user' => $coach])
            ->call('setDateRange', '2026-07-01 to 2026-07-31')
            ->assertSee($eligibleMember->name)
            ->assertSee($membershipMember->name)
            ->assertDontSee($firstOnlyMember->name)
            ->assertDontSee($secondOnlyMember->name)
            ->assertDontSee($bundleMember->name)
            ->call('openBonusPaymentModal')
            ->assertSet('showBonusPaymentModal', true)
            ->assertSet('bonusPaymentTotalNominalAkhir', 1_000_000.0);

        $this->assertSame(1_000_000.0, (float) $component->instance()->totalNominalAkhir());

        $this->assertEqualsCanonicalizing(
            [$eligiblePtMembership->id, $eligibleGymMembership->id],
            collect($component->get('bonusPaymentRows'))->pluck('membership_id')->all()
        );

        $exportData = (new RekapBonusExport(
            $coach->id,
            '',
            '2026-07-01',
            '2026-07-31'
        ))->view()->getData();

        $this->assertEqualsCanonicalizing(
            [$eligiblePtMembership->id, $eligibleGymMembership->id],
            $exportData['memberships']->pluck('id')->all()
        );
        $this->assertSame(1_000_000.0, (float) $exportData['totalNominalAkhir']);
    }

    /** @param array<string, mixed> $overrides */
    private function createPaidMembership(
        User $member,
        User $admin,
        User $staffUser,
        string $type = 'membership',
        array $overrides = []
    ): Membership {
        return Membership::create([
            'user_id' => $member->id,
            'type' => $type,
            'admin_id' => $admin->id,
            'follow_up_id' => $staffUser->id,
            'follow_up_id_two' => $staffUser->id,
            'base_price' => 500_000,
            'price_paid' => 500_000,
            'total_paid' => 500_000,
            'payment_status' => 'paid',
            'start_date' => '2026-07-17',
            'status' => 'active',
            'transaction_type' => 'MEMBERSHIP',
            'package_name' => 'Test Target Period',
            ...$overrides,
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
            'follow_up_id' => $membership->follow_up_id,
            'follow_up_id_two' => $membership->follow_up_id_two,
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
