<?php

namespace Tests\Feature;

use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\PtBooking;
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
            ->assertSeeText('Paket PT Anda sudah habis. Hubungi admin untuk informasi pembelian paket selanjutnya.')
            ->assertSee('HUBUNGI ADMIN')
            ->assertSee('href="https://wa.me/6282373997318"', false)
            ->assertSee('href="https://wa.me/6282181378658"', false)
            ->assertSeeText('CS')
            ->assertSeeText('Sales')
            ->assertDontSee('Jam Operasional')
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
            ->assertCount('ownedPackages', 1)
            ->assertSee('Paket Couple Bersama')
            ->assertSee('member-membership-hero.webp')
            ->assertDontSee('member-package-hero.webp')
            ->assertDontSee('TRANSFORMATION');

        Livewire::actingAs($sharedMember)
            ->test('pages::dashboard.member.home')
            ->assertCount('ownedPackages', 1)
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
            ->assertDontSeeHtml('wire:model.live.number="selectedMembershipId"')
            ->assertSeeInOrder(['Paket Terjauh Terbaru', 'Paket Terjauh Pertama', 'Paket Berakhir Lebih Dekat'])
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
            ->assertCount('ownedPackages', 1)
            ->assertSee('Paket Milik Sendiri')
            ->assertDontSeeHtml('data-testid="owned-package-'.$unrelatedMembership->id.'"')
            ->assertSet('ownedPackages', fn ($packages): bool => $packages->modelKeys() === [$ownedMembership->id]);
    }

    public function test_current_memberships_are_filtered_by_type_status_and_active_flag_regardless_of_dates(): void
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
            ->assertCount('ownedPackages', 4)
            ->assertSee('Paket Valid Saat Ini')
            ->assertDontSee('Paket Pending')
            ->assertDontSee('Paket Selesai')
            ->assertDontSee('Paket Flag Tidak Aktif')
            ->assertSee('Paket Belum Dimulai')
            ->assertSee('Paket Kedaluwarsa')
            ->assertSee('Paket PT Bukan Gym')
            ->assertDontSee('Paket Visit Bukan Membership')
            ->assertDontSee('Paket Milik Member Lain');
    }

    public function test_owned_package_is_shown_without_catalog_packages(): void
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
    public function test_owned_package_shows_only_package_price(
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
            ->assertDontSee('Mulai '.$expectedStartingPrice)
            ->assertSee('Harga Paket')
            ->assertSee('Rp '.number_format($price - $discount, 0, ',', '.'))
            ->assertDontSee('Total Pembayaran')
            ->assertDontSee('Diskon')
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
            ->assertSet('ownedPackageSummaries', fn ($summaries): bool => $summaries->first()['price'] === 'Rp 475.000')
            ->assertDontSee('Rp 999.000')
            ->assertDontSee('Biaya Admin')
            ->assertDontSee('Rp 200.000')
            ->assertDontSee('Sudah Dibayar')
            ->assertDontSee('Sisa Pembayaran')
            ->assertSee('Harga Paket')
            ->assertSee('Rp 475.000')
            ->assertDontSee('Rp 500.000')
            ->assertDontSee('Total Pembayaran')
            ->assertDontSee('Diskon')
            ->assertDontSee('Total Harga');
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
            ->assertCount('ownedPackages', 1)
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
            ->assertCount('ownedPackages', 1)
            ->assertSee('Paket Membership');
    }

    public function test_dashboard_handles_the_absence_of_active_gym_packages(): void
    {
        $member = $this->createUser();
        $this->createPackage('Paket Nonaktif', ['is_active' => false]);

        Livewire::actingAs($member)
            ->test('pages::dashboard.member.home')
            ->assertCount('ownedPackages', 0)
            ->assertSeeText('Paket Personal Training Session Telah Habis')
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
            ->assertCount('ownedPackages', 0)
            ->assertSeeText('Paket Personal Training Session Telah Habis')
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
            ->assertCount('ownedPackages', 0)
            ->assertSeeText('Paket Personal Training Session Telah Habis')
            ->assertDontSee($cheapestPackage->name)
            ->assertDontSee('Paket Single Efektif Mahal')
            ->assertDontSee('Paket Couple Lebih Murah')
            ->assertDontSee('Paket Single Nonaktif');
    }

    public function test_pt_banner_sums_accessible_active_pt_sessions_regardless_of_dates(): void
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
            ->assertCount('ownedPackages', 5)
            ->assertSet('ownedPackageSummaries', fn ($summaries): bool => $summaries->sum('remaining_sessions') === 209)
            ->assertDontSee('Sisa sesi PT aktif Anda:');
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
            ->assertDontSee('Membership Saat Ini')
            ->assertSee('16 bulan | 28 hari tersisa')
            ->assertDontSeeText('Upgrade Membership')
            ->assertDontSee($upgradePackage->name)
            ->assertSee('Lihat Riwayat Absen')
            ->assertSee('Harga Paket')
            ->assertSee('Rp 2.286.000')
            ->assertDontSee('Diskon')
            ->assertDontSee('Rp 114.000')
            ->assertDontSee('Total Pembayaran')
            ->assertDontSee('Total Harga')
            ->assertDontSee('Rp 2.400.000')
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
            ->assertCount('ownedPackages', 1)
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
            ->assertCount('ownedPackages', 1)
            ->assertSeeHtml('data-testid="owned-package-'.$purchase->id.'"')
            ->assertSeeInOrder(['1-ON-1', 'TRANSFORMATION', 'PERSONALIZED PROGRAM.', 'REAL PROGRESS.', 'A STRONGER YOU.'])
            ->assertSeeText('Paket PT Anda')
            ->assertDontSee('Data Absen PT Client')
            ->assertDontSee('Detail paket dan kehadiran client')
            ->assertSeeText('PT Privat Aktif')
            ->assertSee('Jumlah Sesi')
            ->assertSee('Progress Kehadiran')
            ->assertDontSee('Mulai Rp 500.000')
            ->assertSee('PT Privat Aktif')
            ->assertDontSee('Sisa sesi PT: 0 sesi')
            ->assertSee('Rp 500.000')
            ->assertDontSee('Paket Rahasia');
    }

    #[DataProvider('bundleDates')]
    public function test_active_bundle_is_one_purchase_card_regardless_of_benefit_dates(int $gymDays, int $ptDays, int $expectedCount): void
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
            $component->assertSet('ownedPackageSummaries', function ($summaries) use ($gymDays, $ptDays): bool {
                $summary = $summaries->first();

                return $summary['has_gym'] === true
                    && $summary['has_pt'] === true
                    && $summary['gym_active'] === ($gymDays >= 0)
                    && $summary['pt_active'] === ($ptDays >= 0)
                    && $summary['gym_end'] === today()->addDays($gymDays)->locale('id')->translatedFormat('d M Y')
                    && $summary['pt_end'] === today()->addDays($ptDays)->locale('id')->translatedFormat('d M Y')
                    && $summary['gym_duration'] === match (true) {
                        $gymDays < 0 => 'Masa aktif berakhir',
                        $gymDays === 0 => 'Berakhir hari ini',
                        default => $gymDays.' hari tersisa',
                    };
            })
                ->assertSee('Gym Bundle / PT Bundle')
                ->assertSeeInOrder(['1-ON-1', 'TRANSFORMATION', 'PERSONALIZED PROGRAM.', 'REAL PROGRESS.', 'A STRONGER YOU.'])
                ->assertSeeText('Paket Bundle Anda')
                ->assertSeeText('Gym Bundle / PT Bundle')
                ->assertDontSee('Data Absen PT Client')
                ->assertDontSee('Detail paket dan kehadiran client')
                ->assertDontSee('Mulai Rp 500.000')
                ->assertDontSee('Total Harga');
            $this->assertSame(1, substr_count($component->html(), 'data-testid="owned-package-'.$purchase->id.'"'));
        } else {
            $component->assertSeeText('Paket Personal Training Session Telah Habis');
        }
    }

    public function test_pt_progress_counts_attended_bookings_for_the_shared_purchase_only(): void
    {
        $member = $this->createUser();
        $owner = $this->createUser();
        $trainer = $this->createUser(['role' => 'pt']);
        $purchase = $this->createMembership($owner, null, [
            'type' => 'pt',
            'membership_end_date' => null,
            'pt_end_date' => today()->addMonth(),
            'total_sessions' => 10,
            'remaining_sessions' => 6,
        ], [$member]);
        $otherPurchase = $this->createMembership($owner, null, [
            'type' => 'pt',
            'membership_end_date' => null,
            'pt_end_date' => today()->addMonth(),
        ]);

        foreach ([[$purchase, $owner, 'attended'], [$purchase, $member, 'attended'], [$purchase, $member, 'noshow'], [$purchase, $member, 'not_yet'], [$otherPurchase, $owner, 'attended']] as [$membership, $attendee, $attendance]) {
            PtBooking::create([
                'membership_id' => $membership->id,
                'member_id' => $attendee->id,
                'pt_id' => $trainer->id,
                'booking_date' => today(),
                'booking_time' => '09:00:00',
                'status' => 'approved',
                'attendance' => $attendance,
            ]);
        }

        Livewire::actingAs($member)->test('pages::dashboard.member.home')
            ->assertCount('ownedPackages', 1)
            ->assertSet('ownedPackageSummaries', function ($summaries) use ($purchase): bool {
                $summary = $summaries->first();

                return $summary['id'] === $purchase->id
                    && $summary['total_sessions'] === 10
                    && $summary['remaining_sessions'] === 6
                    && $summary['attended_sessions'] === 2
                    && (int) $summary['progress'] === 20;
            })
            ->assertSee('Progress Kehadiran')
            ->assertSee('20%')
            ->assertSee('Lihat Riwayat Absen');
    }

    public function test_pt_progress_handles_zero_total_sessions(): void
    {
        $member = $this->createUser();
        $this->createMembership($member, null, [
            'type' => 'pt',
            'membership_end_date' => null,
            'pt_end_date' => today()->addMonth(),
            'total_sessions' => 0,
            'remaining_sessions' => 0,
        ]);

        Livewire::actingAs($member)->test('pages::dashboard.member.home')
            ->assertSet('ownedPackageSummaries', fn ($summaries): bool => (int) $summaries->first()['progress'] === 0)
            ->assertSee('0%')
            ->assertSee('Sisa Sesi');
    }

    /** @return array<string, array{int, int, int}> */
    public static function bundleDates(): array
    {
        return [
            'gym expired' => [-1, 10, 1],
            'pt expired' => [10, -1, 1],
            'both expired' => [-1, -1, 1],
            'both end today' => [0, 0, 1],
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

    #[DataProvider('catalogTypes')]
    public function test_catalog_is_hidden_for_every_owned_package_type(string $type, bool $showsGym, bool $showsPt): void
    {
        $member = $this->createUser();
        $purchase = $this->createMembership($member, null, [
            'type' => $type,
            'pt_end_date' => today()->addMonth(),
        ]);
        $gym = $this->createPackage('Katalog Gym Aktif', ['price' => 345000]);
        $pt = $this->createPackage('Katalog PT Aktif', ['type' => 'pt', 'pt_sessions' => 8, 'price' => 765000]);
        $this->createPackage('Katalog Gym Nonaktif', ['is_active' => false]);
        $this->createPackage('Katalog PT Nonaktif', ['type' => 'pt', 'is_active' => false]);
        $this->createPackage('Katalog Visit', ['type' => 'visit']);

        $component = Livewire::actingAs($member)->test('pages::dashboard.member.home')
            ->assertDontSee('Katalog Gym Nonaktif')
            ->assertDontSee('Katalog PT Nonaktif')
            ->assertDontSee('Katalog Visit');

        $component->assertSeeHtml('data-testid="owned-package-'.$purchase->id.'"')
            ->assertDontSee($gym->name)->assertDontSee('Pilihan Paket Membership')
            ->assertDontSee($pt->name)->assertDontSee('Pilihan Paket PT')
            ->assertDontSee('Rp 345.000')->assertDontSee('Rp 765.000');
    }

    #[DataProvider('catalogPriceCases')]
    public function test_catalog_is_hidden_regardless_of_duration_or_savings(array $attributes, ?int $quantity, string $period, string $unitPrice, string $total, ?string $saving): void
    {
        $member = $this->createUser();
        $this->createMembership($member);
        $package = $this->createPackage('Paket Khusus', $attributes);

        Livewire::actingAs($member)->test('pages::dashboard.member.home')
            ->assertDontSee($package->name)
            ->assertDontSee('Pilihan Paket Membership')
            ->assertDontSee('Pilihan Paket PT');
    }

    /** @return array<string, array{array<string, mixed>, int|null, string, string, string, string|null}> */
    public static function catalogPriceCases(): array
    {
        return [
            'monthly discount' => [['name' => 'Membership 3 Monthly Pass', 'price' => 1500000, 'discount' => 300000], 3, 'per bulan', 'Rp 400.000', 'Rp 1.200.000', '20'],
            'bonus months' => [['name' => 'PROMO 6 BULAN PLUS 2 BULAN', 'price' => 620000], 8, 'per bulan', 'Rp 77.500', 'Rp 620.000', null],
            'yearly' => [['name' => 'Membership Yearly Pass', 'price' => 2400000], 12, 'per bulan', 'Rp 200.000', 'Rp 2.400.000', null],
            'weekly' => [['name' => 'Membership Weekly Pass', 'price' => 150000, 'normal_price' => 130000], 1, 'per minggu', 'Rp 150.000', 'Rp 150.000', null],
            'pt sessions' => [['type' => 'pt', 'pt_sessions' => 10, 'price' => 1600000, 'normal_price' => 2000000], 10, 'per sesi', 'Rp 160.000', 'Rp 1.600.000', '20'],
            'unknown duration' => [['price' => 500000], null, 'per paket', 'Rp 500.000', 'Rp 500.000', null],
            'zero sessions' => [['type' => 'pt', 'pt_sessions' => 0, 'price' => 0], null, 'per paket', 'Rp 0', 'Rp 0', null],
        ];
    }

    /** @return array<string, array{string, bool, bool}> */
    public static function catalogTypes(): array
    {
        return [
            'membership' => ['membership', true, false],
            'pt' => ['pt', false, true],
            'bundle' => ['bundle_pt_membership', true, true],
        ];
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
