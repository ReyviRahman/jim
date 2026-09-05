<?php

namespace Tests\Feature;

use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MemberDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_the_member_dashboard(): void
    {
        $this->get(route('member.dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_non_member_is_redirected_home_from_the_member_dashboard(): void
    {
        $admin = $this->createUser(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('member.dashboard'))
            ->assertRedirect(route('home'));
    }

    public function test_member_can_open_the_member_dashboard(): void
    {
        $member = $this->createUser();

        $this->actingAs($member)
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard Membership')
            ->assertSee('Jam Operasional')
            ->assertSee('Senin - Jum’at')
            ->assertSee('07.00 - 22.00')
            ->assertSee('Sabtu')
            ->assertSee('07.00 - 20.00')
            ->assertSee('Minggu')
            ->assertSee('07.00 - 19.00')
            ->assertDontSeeText('Lihat masa aktif membership dan rekomendasi paket terbaik untuk Anda.');
    }

    public function test_membership_owner_and_shared_member_can_view_the_same_membership(): void
    {
        $owner = $this->createUser();
        $sharedMember = $this->createUser();
        $package = $this->createPackage('Paket Couple Bersama', [
            'category' => 'couple',
            'max_members' => 2,
        ]);
        $membership = $this->createMembership($owner, $package, members: [$sharedMember]);

        Livewire::actingAs($owner)
            ->test('pages::dashboard.member.home')
            ->assertSet('selectedMembershipId', $membership->id)
            ->assertSee('Paket Couple Bersama');

        Livewire::actingAs($sharedMember)
            ->test('pages::dashboard.member.home')
            ->assertSet('selectedMembershipId', $membership->id)
            ->assertSee('Paket Couple Bersama');
    }

    public function test_selector_defaults_to_the_furthest_end_date_then_latest_membership_id(): void
    {
        $member = $this->createUser();
        $payer = $this->createUser();
        $nearPackage = $this->createPackage('Paket Berakhir Lebih Dekat');
        $firstFurthestPackage = $this->createPackage('Paket Terjauh Pertama');
        $latestFurthestPackage = $this->createPackage('Paket Terjauh Terbaru');

        $nearMembership = $this->createMembership($member, $nearPackage, [
            'membership_end_date' => today()->addMonthsNoOverflow(2)->toDateString(),
        ]);
        $this->createMembership($payer, $firstFurthestPackage, [
            'membership_end_date' => today()->addMonthsNoOverflow(3)->toDateString(),
        ], [$member]);
        $latestFurthestMembership = $this->createMembership($payer, $latestFurthestPackage, [
            'membership_end_date' => today()->addMonthsNoOverflow(3)->toDateString(),
        ], [$member]);

        Livewire::actingAs($member)
            ->test('pages::dashboard.member.home')
            ->assertSet('selectedMembershipId', $latestFurthestMembership->id)
            ->assertSeeHtml('wire:model.live.number="selectedMembershipId"')
            ->set('selectedMembershipId', $nearMembership->id)
            ->assertSet('selectedMembershipId', $nearMembership->id)
            ->assertSee('Paket Berakhir Lebih Dekat')
            ->assertSee('Paket Terjauh Pertama')
            ->assertSee('Paket Terjauh Terbaru')
            ->assertCount('ownedPackages', 3);
    }

    public function test_selector_rejects_a_membership_the_member_cannot_access(): void
    {
        $member = $this->createUser();
        $otherMember = $this->createUser();
        $ownedMembership = $this->createMembership(
            $member,
            $this->createPackage('Paket Milik Sendiri'),
        );
        $unrelatedMembership = $this->createMembership(
            $otherMember,
            $this->createPackage('Paket Milik Orang Lain'),
        );

        Livewire::actingAs($member)
            ->test('pages::dashboard.member.home')
            ->assertSet('selectedMembershipId', $ownedMembership->id)
            ->set('selectedMembershipId', $unrelatedMembership->id)
            ->assertForbidden();
    }

    public function test_current_memberships_are_filtered_by_type_status_active_flag_and_dates(): void
    {
        $member = $this->createUser();
        $otherMember = $this->createUser();
        $currentPackage = $this->createPackage('Paket Valid Saat Ini');
        $currentMembership = $this->createMembership($member, $currentPackage);

        $this->createMembership($member, $this->createInactivePackage('Paket Pending'), [
            'status' => 'pending',
        ]);
        $this->createMembership($member, $this->createInactivePackage('Paket Selesai'), [
            'status' => 'completed',
        ]);
        $this->createMembership($member, $this->createInactivePackage('Paket Flag Tidak Aktif'), [
            'is_active' => false,
        ]);
        $this->createMembership($member, $this->createInactivePackage('Paket Belum Dimulai'), [
            'start_date' => today()->addDay()->toDateString(),
        ]);
        $this->createMembership($member, $this->createInactivePackage('Paket Kedaluwarsa'), [
            'membership_end_date' => today()->subDay()->toDateString(),
        ]);
        $this->createMembership($member, $this->createInactivePackage('Paket PT Bukan Gym'), [
            'type' => 'pt',
            'membership_end_date' => null,
            'pt_end_date' => today()->addMonth()->toDateString(),
            'remaining_sessions' => 0,
            'total_sessions' => 10,
        ]);
        $this->createMembership($member, $this->createInactivePackage('Paket Visit Bukan Membership'), [
            'type' => 'visit',
        ]);
        $this->createMembership($otherMember, $this->createInactivePackage('Paket Milik Member Lain'));

        Livewire::actingAs($member)
            ->test('pages::dashboard.member.home')
            ->assertSet('selectedMembershipId', $currentMembership->id)
            ->assertSee('Paket Valid Saat Ini')
            ->assertDontSee('Paket Pending')
            ->assertDontSee('Paket Selesai')
            ->assertDontSee('Paket Flag Tidak Aktif')
            ->assertDontSee('Paket Belum Dimulai')
            ->assertDontSee('Paket Kedaluwarsa')
            ->assertSee('Paket PT Bukan Gym')
            ->assertDontSee('Paket Visit Bukan Membership')
            ->assertDontSee('Paket Milik Member Lain');
    }

    public function test_owned_package_is_shown_instead_of_other_catalog_packages(): void
    {
        $member = $this->createUser();
        $currentPackage = $this->createPackage('Paket Couple Saat Ini', [
            'category' => 'couple',
            'max_members' => 2,
            'price' => 500000,
            'discount' => 100000,
        ]);
        $this->createMembership($member, $currentPackage);

        $this->createPackage('Paket Efektif Sama', [
            'category' => 'couple',
            'max_members' => 2,
            'price' => 450000,
            'discount' => 50000,
        ]);
        $firstTiedUpgrade = $this->createPackage('Upgrade Tie ID Terkecil', [
            'category' => 'couple',
            'max_members' => 2,
            'price' => 650000,
            'discount' => 100000,
        ]);
        $this->createPackage('Upgrade Tie ID Terbaru', [
            'category' => 'couple',
            'max_members' => 2,
            'price' => 600000,
            'discount' => 50000,
        ]);
        $this->createPackage('Upgrade Lebih Mahal', [
            'category' => 'couple',
            'max_members' => 2,
            'price' => 800000,
        ]);
        $this->createPackage('Paket Single Salah Kategori', [
            'price' => 450000,
        ]);
        $this->createPackage('Paket Couple Nonaktif', [
            'category' => 'couple',
            'max_members' => 2,
            'price' => 450000,
            'is_active' => false,
        ]);

        Livewire::actingAs($member)
            ->test('pages::dashboard.member.home')
            ->assertDontSeeText('Upgrade Membership')
            ->assertDontSee($firstTiedUpgrade->name)
            ->assertSee('Paket Couple Saat Ini')
            ->assertDontSee('Upgrade Tie ID Terbaru')
            ->assertDontSee('Upgrade Lebih Mahal')
            ->assertDontSee('Paket Single Salah Kategori')
            ->assertDontSee('Paket Couple Nonaktif');
    }

    #[DataProvider('packageDurationCases')]
    public function test_owned_package_starting_price_uses_purchase_price_and_duration(
        string $packageName,
        int $price,
        int $discount,
        string $expectedStartingPrice,
    ): void {
        $member = $this->createUser();
        $currentPackage = $this->createPackage('Paket Saat Ini', [
            'price' => 50000,
        ]);
        $ownedPackage = $this->createPackage($packageName, [
            'price' => $price,
            'discount' => $discount,
        ]);
        $this->createMembership($member, $ownedPackage, [
            'base_price' => $price,
            'discount_applied' => $discount,
            'price_paid' => $price - $discount,
            'total_paid' => $price - $discount,
        ]);

        Livewire::actingAs($member)
            ->test('pages::dashboard.member.home')
            ->assertSee($packageName)
            ->assertSee('Mulai '.$expectedStartingPrice)
            ->assertSee('Harga Paket')
            ->assertSee('Rp '.number_format($price, 0, ',', '.'))
            ->assertSee('Total Pembayaran')
            ->assertDontSeeText('Rekomendasi paket');
    }

    /** @return array<string, array{string, int, int, string}> */
    public static function packageDurationCases(): array
    {
        return [
            'one month weekly price' => ['Membership 1 Monthly Pass', 400000, 0, 'Rp 100.000 per minggu'],
            'one month Indonesian name' => ['Paket 1 bulan', 400000, 0, 'Rp 100.000 per minggu'],
            'one month discounted' => ['Membership 1 Monthly Pass', 500000, 100000, 'Rp 100.000 per minggu'],
            'monthly pass' => ['Membership 2 Monthly Pass', 500000, 0, 'Rp 250.000 per bulan'],
            'yearly pass' => ['Membership Yearly Pass', 2700000, 0, 'Rp 225.000 per bulan'],
            'free months promotion' => ['Promo 4 free 1 bulan', 500000, 0, 'Rp 100.000 per bulan'],
            'additional months promotion' => ['Promo 5 bulan plus 2 bulan', 750000, 0, 'Rp 107.143 per bulan'],
            'monthly promotion' => ['Promo 4 bulan', 500000, 0, 'Rp 125.000 per bulan'],
            'weekly pass' => ['Membership Weekly Pass', 130000, 0, 'Rp 130.000'],
            'unknown duration' => ['Paket Khusus', 400000, 0, 'Rp 400.000'],
            'invalid duration' => ['Membership 0 Monthly Pass', 400000, 0, 'Rp 400.000'],
        ];
    }

    public function test_catalog_price_changes_do_not_change_purchase_prices(): void
    {
        $member = $this->createUser();
        $currentPackage = $this->createPackage('Paket Saat Ini', [
            'price' => 50000,
        ]);
        $currentPackage->update([
            'price' => 500000,
            'discount' => 50000,
        ]);
        $this->createMembership($member, $currentPackage, [
            'base_price' => 500000,
            'discount_applied' => 50000,
            'admin_fee' => 25000,
            'price_paid' => 475000,
            'total_paid' => 200000,
            'payment_status' => 'partial',
        ]);
        $currentPackage->update(['price' => 999000, 'discount' => 0]);

        Livewire::actingAs($member)
            ->test('pages::dashboard.member.home')
            ->assertDontSee('Rp 999.000')
            ->assertDontSee('Biaya Admin')
            ->assertSee('Rp 475.000')
            ->assertDontSee('Sudah Dibayar')
            ->assertDontSee('Sisa Pembayaran')
            ->assertSee('Harga Paket')
            ->assertSee('Rp 500.000')
            ->assertSee('Total Pembayaran')
            ->assertSee('Total Harga');
    }

    public function test_highest_tier_membership_has_no_upgrade_recommendation(): void
    {
        $member = $this->createUser();
        $highestPackage = $this->createPackage('Paket Tier Tertinggi', [
            'price' => 900000,
            'discount' => 100000,
        ]);
        $this->createPackage('Paket Tier Lebih Rendah', [
            'price' => 700000,
        ]);
        $this->createPackage('Paket Tier Efektif Sama', [
            'price' => 850000,
            'discount' => 50000,
        ]);
        $this->createMembership($member, $highestPackage);

        Livewire::actingAs($member)
            ->test('pages::dashboard.member.home')
            ->assertSee('Paket Tier Tertinggi')
            ->assertDontSee('Tier tertinggi sudah aktif')
            ->assertSee('Membership Anda');
    }

    public function test_missing_current_package_uses_snapshot_name_and_disables_recommendation(): void
    {
        $member = $this->createUser();
        $membership = $this->createMembership($member, null, [
            'package_name' => 'Paket Snapshot Lama',
        ]);
        $this->createPackage('Paket Aktif Yang Tidak Bisa Dibandingkan');

        Livewire::actingAs($member)
            ->test('pages::dashboard.member.home')
            ->assertSet('selectedMembershipId', $membership->id)
            ->assertSee('Paket Snapshot Lama')
            ->assertDontSee('Rekomendasi paket belum tersedia.')
            ->assertSee('Membership Anda')
            ->assertDontSee('Paket Aktif Yang Tidak Bisa Dibandingkan');
    }

    public function test_missing_current_package_name_uses_generic_fallback(): void
    {
        $member = $this->createUser();
        $membership = $this->createMembership($member);

        Livewire::actingAs($member)
            ->test('pages::dashboard.member.home')
            ->assertSet('selectedMembershipId', $membership->id)
            ->assertSee('Paket Membership');
    }

    public function test_dashboard_handles_the_absence_of_active_gym_packages(): void
    {
        $member = $this->createUser();
        $this->createPackage('Paket Nonaktif', ['is_active' => false]);

        Livewire::actingAs($member)
            ->test('pages::dashboard.member.home')
            ->assertSet('selectedMembershipId', null)
            ->assertSee('Belum ada membership atau PT aktif')
            ->assertDontSee('Paket Nonaktif');
    }

    public function test_current_membership_uses_an_informative_fallback_when_no_gym_package_is_active(): void
    {
        $member = $this->createUser();
        $inactivePackage = $this->createPackage('Paket Lama Nonaktif', ['is_active' => false]);
        $this->createMembership($member, $inactivePackage);

        Livewire::actingAs($member)
            ->test('pages::dashboard.member.home')
            ->assertSee('Paket Lama Nonaktif')
            ->assertSee('Membership Anda')
            ->assertDontSee('Tier tertinggi sudah aktif');
    }

    public function test_dashboard_explains_when_active_catalog_has_no_single_membership_package(): void
    {
        $member = $this->createUser();
        $this->createPackage('Paket Couple Aktif', [
            'category' => 'couple',
            'max_members' => 2,
        ]);

        Livewire::actingAs($member)
            ->test('pages::dashboard.member.home')
            ->assertSet('selectedMembershipId', null)
            ->assertSee('Belum ada membership atau PT aktif')
            ->assertDontSee('Belum ada paket membership yang tersedia saat ini.');
    }

    public function test_member_without_owned_packages_is_not_offered_catalog_packages(): void
    {
        $member = $this->createUser();
        $this->createPackage('Paket Single Efektif Mahal', [
            'price' => 500000,
        ]);
        $cheapestPackage = $this->createPackage('Paket Single Efektif Termurah', [
            'price' => 700000,
            'discount' => 300000,
        ]);
        $this->createPackage('Paket Couple Lebih Murah', [
            'category' => 'couple',
            'max_members' => 2,
            'price' => 100000,
        ]);
        $this->createPackage('Paket Single Nonaktif', [
            'price' => 50000,
            'is_active' => false,
        ]);

        Livewire::actingAs($member)
            ->test('pages::dashboard.member.home')
            ->assertSet('selectedMembershipId', null)
            ->assertSee('Belum ada membership atau PT aktif')
            ->assertDontSee($cheapestPackage->name)
            ->assertDontSee('Paket Single Efektif Mahal')
            ->assertDontSee('Paket Couple Lebih Murah')
            ->assertDontSee('Paket Single Nonaktif');
    }

    public function test_pt_banner_sums_only_accessible_active_unexpired_pt_sessions(): void
    {
        $member = $this->createUser();
        $payer = $this->createUser();
        $bundlePackage = $this->createPackage('Paket Bundle Aktif');

        $this->createMembership($member, null, [
            'type' => 'pt',
            'membership_end_date' => null,
            'pt_end_date' => today()->addMonth()->toDateString(),
            'remaining_sessions' => 4,
            'total_sessions' => 10,
        ]);
        $this->createMembership($member, $bundlePackage, [
            'type' => 'bundle_pt_membership',
            'pt_end_date' => today()->addMonth()->toDateString(),
            'remaining_sessions' => 3,
            'total_sessions' => 10,
        ]);
        $this->createMembership($payer, null, [
            'type' => 'pt',
            'membership_end_date' => null,
            'pt_end_date' => today()->addMonth()->toDateString(),
            'remaining_sessions' => 2,
            'total_sessions' => 5,
        ], [$member]);
        $this->createMembership($member, null, [
            'type' => 'pt',
            'status' => 'completed',
            'membership_end_date' => null,
            'pt_end_date' => today()->addMonth()->toDateString(),
            'remaining_sessions' => 100,
            'total_sessions' => 100,
        ]);
        $this->createMembership($member, null, [
            'type' => 'pt',
            'is_active' => false,
            'membership_end_date' => null,
            'pt_end_date' => today()->addMonth()->toDateString(),
            'remaining_sessions' => 100,
            'total_sessions' => 100,
        ]);
        $this->createMembership($member, null, [
            'type' => 'pt',
            'membership_end_date' => null,
            'pt_end_date' => today()->subDay()->toDateString(),
            'remaining_sessions' => 100,
            'total_sessions' => 100,
        ]);
        $this->createMembership($member, null, [
            'type' => 'pt',
            'start_date' => today()->addDay()->toDateString(),
            'membership_end_date' => null,
            'pt_end_date' => today()->addMonth()->toDateString(),
            'remaining_sessions' => 100,
            'total_sessions' => 100,
        ]);

        Livewire::actingAs($member)
            ->test('pages::dashboard.member.home')
            ->assertSee('Sisa sesi PT aktif Anda: 9 sesi.');
    }

    public function test_dashboard_formats_calendar_duration_and_upgrade_currency_without_a_next_button(): void
    {
        $this->travelTo(Carbon::parse('2026-09-01 14:55:00', config('app.timezone')));

        $member = $this->createUser();
        $currentPackage = $this->createPackage('Paket Current Basic', [
            'price' => 1000000,
        ]);
        $upgradePackage = $this->createPackage('Paket Upgrade Ultra', [
            'price' => 2400000,
            'discount' => 114000,
        ]);
        $this->createMembership($member, $currentPackage, [
            'membership_end_date' => '2028-01-29',
            'base_price' => 2400000,
            'discount_applied' => 114000,
            'price_paid' => 2286000,
            'total_paid' => 2286000,
        ]);

        Livewire::actingAs($member)
            ->test('pages::dashboard.member.home')
            ->assertSee('Membership Saat Ini')
            ->assertSee('16 bulan | 28 hari tersisa')
            ->assertDontSeeText('Upgrade Membership')
            ->assertDontSee($upgradePackage->name)
            ->assertSee('Lihat Detail')
            ->assertSee('Rincian Harga')
            ->assertSee('Harga Paket')
            ->assertSee('Rp 2.400.000')
            ->assertSee('Diskon')
            ->assertSee('Rp 114.000')
            ->assertSee('Total Pembayaran')
            ->assertSee('Total Harga')
            ->assertSee('Rp 2.286.000')
            ->assertDontSee('Next');
    }

    public function test_membership_ending_today_is_still_current(): void
    {
        $member = $this->createUser();
        $currentPackage = $this->createPackage('Paket Berakhir Hari Ini');
        $membership = $this->createMembership($member, $currentPackage, [
            'membership_end_date' => today()->toDateString(),
        ]);

        Livewire::actingAs($member)
            ->test('pages::dashboard.member.home')
            ->assertSet('selectedMembershipId', $membership->id)
            ->assertSee('Paket Berakhir Hari Ini')
            ->assertSee('Berakhir hari ini');
    }

    public function test_pt_only_member_sees_zero_sessions_and_shared_purchase_without_other_users_packages(): void
    {
        $member = $this->createUser();
        $owner = $this->createUser();
        $pt = $this->createPackage('PT Privat Aktif', ['type' => 'pt']);
        $purchase = $this->createMembership($owner, null, [
            'type' => 'pt',
            'pt_package_id' => $pt->id,
            'membership_end_date' => null,
            'pt_end_date' => today()->toDateString(),
            'remaining_sessions' => 0,
        ], [$member]);
        $this->createMembership($owner, $this->createPackage('Paket Rahasia'));

        Livewire::actingAs($member)->test('pages::dashboard.member.home')
            ->assertSet('selectedMembershipId', null)
            ->assertCount('ownedPackages', 1)
            ->assertSeeHtml('data-testid="owned-package-'.$purchase->id.'"')
            ->assertSee('Paket PT Anda')
            ->assertSee('Mulai Rp 500.000')
            ->assertSee('PT Privat Aktif')
            ->assertDontSee('Sisa sesi PT: 0 sesi')
            ->assertSee('Rp 500.000')
            ->assertDontSee('Paket Rahasia');
    }

    #[DataProvider('bundleDates')]
    public function test_bundle_is_one_purchase_card_while_either_benefit_is_active(int $gymDays, int $ptDays, int $expectedCount): void
    {
        $member = $this->createUser();
        $gym = $this->createPackage('Gym Bundle');
        $pt = $this->createPackage('PT Bundle', ['type' => 'pt']);
        $purchase = $this->createMembership($member, $gym, [
            'type' => 'bundle_pt_membership',
            'pt_package_id' => $pt->id,
            'membership_end_date' => today()->addDays($gymDays),
            'pt_end_date' => today()->addDays($ptDays),
            'remaining_sessions' => 3,
        ]);

        $component = Livewire::actingAs($member)->test('pages::dashboard.member.home')
            ->assertCount('ownedPackages', $expectedCount);

        if ($expectedCount === 1) {
            $component->assertSee('Gym Bundle / PT Bundle')
                ->assertSee('Paket Bundle Anda')
                ->assertSee('Mulai Rp 500.000')
                ->assertSee('Total Harga');
            $this->assertSame(1, substr_count($component->html(), 'data-testid="owned-package-'.$purchase->id.'"'));
        } else {
            $component->assertSee('Belum ada membership atau PT aktif');
        }
    }

    /** @return array<string, array{int, int, int}> */
    public static function bundleDates(): array
    {
        return [
            'gym expired' => [-1, 10, 1],
            'pt expired' => [10, -1, 1],
            'both expired' => [-1, -1, 0],
        ];
    }

    /** @param array<string, mixed> $attributes */
    private function createUser(array $attributes = []): User
    {
        return User::factory()->create([
            'role' => 'member',
            ...$attributes,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function createPackage(string $name, array $attributes = []): GymPackage
    {
        return GymPackage::create([
            'type' => 'gym',
            'name' => $name,
            'category' => 'single',
            'max_members' => 1,
            'price' => 500000,
            'discount' => 0,
            'is_active' => true,
            ...$attributes,
        ]);
    }

    private function createInactivePackage(string $name): GymPackage
    {
        return $this->createPackage($name, ['is_active' => false]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, User>  $members
     */
    private function createMembership(
        User $owner,
        ?GymPackage $package = null,
        array $attributes = [],
        array $members = [],
    ): Membership {
        $membership = Membership::create([
            'user_id' => $owner->id,
            'type' => 'membership',
            'gym_package_id' => $package?->id,
            'base_price' => 500000,
            'discount_applied' => 0,
            'price_paid' => 500000,
            'total_paid' => 500000,
            'payment_status' => 'paid',
            'start_date' => today()->subDay()->toDateString(),
            'membership_end_date' => today()->addMonth()->toDateString(),
            'status' => 'active',
            'is_active' => true,
            'package_name' => $package?->name,
            ...$attributes,
        ]);

        if ($members !== []) {
            $membership->members()->attach(collect($members)->pluck('id')->unique()->all());
        }

        return $membership;
    }
}
