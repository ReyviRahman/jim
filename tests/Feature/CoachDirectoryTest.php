<?php

namespace Tests\Feature;

use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\PtBooking;
use App\Models\User;
use Carbon\Carbon;
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

    public function test_default_period_uses_previous_month_sixteenth_through_current_month_fifteenth(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        foreach (['2026-09-01', '2026-09-15', '2026-09-25', '2026-09-30'] as $today) {
            $this->travelTo(Carbon::parse($today));
            Livewire::test('pages::dashboard.admin.sesi-pt.index')
                ->assertSet('periodStart', '2026-08-16')->assertSet('periodEnd', '2026-09-15');
        }

        $this->travelTo(Carbon::parse('2027-01-31'));
        Livewire::test('pages::dashboard.admin.sesi-pt.index')
            ->assertSet('periodStart', '2026-12-16')->assertSet('periodEnd', '2027-01-15');
        $this->travelBack();
    }

    public function test_period_counts_unique_members_from_overlapping_packages_and_only_attended_bookings(): void
    {
        $this->travelTo(Carbon::parse('2026-09-25'));
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $coach = User::factory()->create(['role' => 'pt', 'is_active' => true]);
        $members = User::factory()->count(2)->create(['role' => 'member']);
        $package = GymPackage::create(['name' => 'PT Group', 'type' => 'pt', 'category' => 'group', 'price' => 300000, 'pt_sessions' => 10, 'is_active' => true]);

        $create = function (array $attributes = []) use ($coach, $members, $package): Membership {
            $membership = Membership::create([
                'user_id' => $members[0]->id, 'pt_id' => $coach->id, 'pt_package_id' => $package->id,
                'type' => 'pt', 'base_price' => 300000, 'price_paid' => 300000,
                'total_paid' => 300000, 'payment_status' => 'paid', 'status' => 'active', 'is_active' => true,
                'total_sessions' => 10, 'sesi_ditambahkan' => 2, 'remaining_sessions' => 5,
                'start_date' => '2026-08-01', 'pt_end_date' => '2026-10-01', ...$attributes,
            ]);
            $membership->members()->attach($members->modelKeys());

            return $membership;
        };

        $membership = $create();
        $create(['pt_end_date' => '2026-08-16', 'status' => 'completed', 'is_active' => false]);
        $create(['start_date' => '2026-09-15']);
        $create(['start_date' => '2026-08-20', 'pt_end_date' => '2026-09-01']);
        $create(['pt_end_date' => '2026-08-15']);
        $create(['start_date' => '2026-09-16']);
        $create(['status' => 'pending']);
        $create(['status' => 'rejected']);
        $create(['pt_package_id' => null]);
        $create(['pt_end_date' => null]);
        $create(['start_date' => null]);

        $otherCoach = User::factory()->create(['role' => 'pt', 'is_active' => true]);
        $booking = function (array $attributes = []) use ($membership, $coach, $members): void {
            PtBooking::create([
                'membership_id' => $membership->id, 'member_id' => $members[0]->id, 'pt_id' => $coach->id,
                'booking_date' => '2026-09-01', 'booking_time' => '08:00', 'status' => 'approved',
                'attendance' => 'attended', 'is_free' => false, ...$attributes,
            ]);
        };
        $booking(['booking_date' => '2026-08-16']);
        $booking(['booking_date' => '2026-09-15']);
        $booking(['is_free' => true]);
        $booking();
        $booking(['booking_date' => '2026-08-15']);
        $booking(['booking_date' => '2026-09-16']);
        $booking(['attendance' => 'noshow']);
        $booking(['attendance' => 'not_yet']);
        $booking(['status' => 'cancelled']);
        $booking(['status' => 'pending']);
        $booking(['status' => 'rejected']);
        $booking(['pt_id' => $otherCoach->id]);

        $page = Livewire::test('pages::dashboard.admin.sesi-pt.index');
        $stats = $page->get('ptUsers')->firstWhere('id', $coach->id);
        $this->assertCount(4, $stats->ptMemberships);
        $this->assertSame(2, $stats->ptMemberships->flatMap->members->unique('id')->count());
        $this->assertSame(3, (int) $stats->total_sessions);
        $this->assertSame(1, (int) $page->get('ptUsers')->firstWhere('id', $otherCoach->id)->total_sessions);

        $page->set('dateStart', '2027-02-01')->set('dateEnd', '2027-02-28')->call('applyPeriod')->assertHasNoErrors();
        $stats = $page->get('ptUsers')->firstWhere('id', $coach->id);
        $this->assertCount(0, $stats->ptMemberships);
        $this->assertSame(0, (int) $stats->total_sessions);
        $page->set('search', $coach->name)->assertSee($coach->name)->call('resetPeriod')
            ->assertSet('periodStart', '2026-08-16')->assertSet('periodEnd', '2026-09-15');
        $this->assertCount(4, $page->get('ptUsers')->first()->ptMemberships);
        $this->travelBack();
    }

    public function test_invalid_period_keeps_the_last_applied_range_and_same_day_is_allowed(): void
    {
        $this->travelTo(Carbon::parse('2026-09-25'));
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $page = Livewire::test('pages::dashboard.admin.sesi-pt.index');
        $page->set('dateStart', '2026-09-20')->set('dateEnd', '2026-09-01')->call('applyPeriod')
            ->assertHasErrors('dateEnd')->assertSet('periodStart', '2026-08-16')->assertSet('periodEnd', '2026-09-15');
        $page->set('dateStart', '')->call('applyPeriod')->assertHasErrors('dateStart');
        $page->set('dateStart', '2026-02-30')->call('applyPeriod')->assertHasErrors('dateStart');
        $page->set('dateStart', '2026-09-01')->set('dateEnd', '2026-09-01')->call('applyPeriod')
            ->assertHasNoErrors()->assertSet('periodStart', '2026-09-01')->assertSet('periodEnd', '2026-09-01');
        $this->travelBack();
    }
}
