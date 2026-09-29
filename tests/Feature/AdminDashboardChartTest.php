<?php

namespace Tests\Feature;

use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\MembershipAddon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminDashboardChartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_unique_contracts_follow_installment_onboarding_active_priority(): void
    {
        $gym = $this->membership();
        $pt = $this->membership(['type' => 'pt']);
        $bundle = $this->membership(['type' => 'bundle_pt_membership']);
        $this->membership(['is_active' => false]);
        $this->membership(['type' => 'pt', 'pt_id' => null]);
        $this->membership(['type' => 'pt', 'pt_end_date' => null]);
        $this->membership(['payment_status' => 'partial']);
        $this->membership(['type' => 'pt', 'payment_status' => 'partial', 'pt_id' => null]);
        $this->membership(['type' => 'pt', 'status' => 'pending', 'is_active' => false, 'payment_status' => 'unpaid']);
        $this->membership(['type' => 'visit', 'payment_status' => 'unpaid']);
        $this->membership(['status' => 'completed']);
        $this->membership(['status' => 'pending', 'is_active' => false]);
        $this->membership(['type' => 'pt', 'status' => 'pending', 'is_active' => false, 'payment_status' => 'partial', 'pt_installment_expired_at' => now()]);
        $gym->members()->attach(User::factory()->count(2)->create());
        MembershipAddon::factory()->create(['membership_id' => $pt->id, 'approval_status' => 'approved', 'status' => 'active']);

        $page = Livewire::test('pages::dashboard.admin.index');
        $this->assertSame([3, 3, 4], $page->get('chartData')['data']);
        $this->assertSame(10, $page->get('chartData')['total']);
        $this->assertEquals([30.0, 30.0, 40.0], $page->get('chartData')['percentages']);
        $this->assertTrue(Membership::activeGym()->whereKey($bundle)->exists());
        $this->assertTrue(Membership::runningPt()->whereKey($bundle)->exists());
        $this->assertDatabaseCount('membership_transactions', 0);
        $page->assertSee('Status Paket Saat Ini')->assertSee('Setiap paket dihitung sekali.')
            ->assertSee('Membership Dobel')->assertDontSee('Pilih Rentang Tanggal');
    }

    public function test_empty_dashboard_has_zero_percentages_and_no_chart(): void
    {
        $page = Livewire::test('pages::dashboard.admin.index')->assertSee('Belum ada data paket');
        $this->assertSame([0, 0, 0], $page->get('chartData')['data']);
        $this->assertEquals([0.0, 0.0, 0.0], $page->get('chartData')['percentages']);
        $page->assertDontSee('<canvas', false);
    }

    public function test_percentages_include_old_contracts_and_round_to_one_decimal(): void
    {
        $this->membership(['created_at' => now()->subYears(3)]);
        $this->membership(['is_active' => false]);
        $this->membership(['payment_status' => 'partial']);
        $page = Livewire::test('pages::dashboard.admin.index');
        $this->assertEquals([33.3, 33.3, 33.3], $page->get('chartData')['percentages']);
        $page->assertSee('33,3%');
    }

    public function test_shared_filters_match_the_six_reference_lists(): void
    {
        $gym = $this->membership();
        $pt = $this->membership(['type' => 'pt']);
        $awaitingGym = $this->membership(['is_active' => false]);
        $awaitingPt = $this->membership(['type' => 'pt', 'pt_id' => null, 'is_active' => false]);
        $gymInstallment = $this->membership(['payment_status' => 'partial', 'status' => 'pending', 'is_active' => false]);
        $ptInstallment = $this->membership(['type' => 'pt', 'payment_status' => 'unpaid', 'status' => 'pending', 'is_active' => false]);
        $this->membership(['type' => 'pt', 'payment_status' => 'partial', 'status' => 'pending', 'is_active' => false, 'pt_installment_expired_at' => now()]);

        foreach ([
            ['pages::dashboard.admin.membership.index', [], $gym->id],
            ['pages::dashboard.admin.membership.gabung', [], $awaitingGym->id],
            ['pages::dashboard.admin.pt-booking.index', [], $awaitingPt->id],
            ['pages::dashboard.admin.cicilan.index', ['ptOnly' => false], $gymInstallment->id],
            ['pages::dashboard.admin.cicilan.index', ['ptOnly' => true], $ptInstallment->id],
        ] as [$component, $parameters, $expected]) {
            $page = Livewire::test($component, $parameters);
            $this->assertSame([$expected], $page->get('memberships')->pluck('id')->all());
        }
        $running = Livewire::test('pages::dashboard.admin.pt-berjalan');
        $this->assertSame(1, $running->get('coaches')->sum('active_packages_count'));
        $this->assertSame([$pt->id], Membership::runningPt()->pluck('id')->all());
        $this->assertSame([2, 2, 2], Livewire::test('pages::dashboard.admin.index')->get('chartData')['data']);
    }

    private function membership(array $overrides = []): Membership
    {
        $type = $overrides['type'] ?? 'membership';
        $pt = in_array($type, ['pt', 'bundle_pt_membership'], true);
        $package = $pt ? GymPackage::create(['name' => 'PT chart test', 'type' => 'pt', 'category' => 'single', 'price' => 300000, 'pt_sessions' => 10, 'is_active' => true]) : null;

        return Membership::create([
            'user_id' => User::factory()->create(['role' => 'member'])->id,
            'type' => $type, 'pt_package_id' => $package?->id,
            'pt_id' => $pt ? User::factory()->create(['role' => 'pt', 'is_active' => true])->id : null,
            'base_price' => 300000, 'price_paid' => 300000, 'total_paid' => 300000,
            'payment_status' => 'paid', 'status' => 'active', 'is_active' => true,
            'start_date' => today(), 'pt_end_date' => $pt ? today()->addMonth() : null,
            'membership_end_date' => today()->addMonth(), 'total_sessions' => $pt ? 10 : null,
            'remaining_sessions' => $pt ? 10 : null,
            ...$overrides,
        ]);
    }
}
