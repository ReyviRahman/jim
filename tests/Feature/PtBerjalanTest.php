<?php

namespace Tests\Feature;

use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PtBerjalanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_coach_list_counts_packages_and_includes_active_empty_and_inactive_assigned_coaches(): void
    {
        $coach = $this->coach('Coach A');
        $empty = $this->coach('Coach B');
        $inactive = $this->coach('Coach C', false);
        $hidden = $this->coach('Coach D', false);
        $first = $this->membership($coach);
        $this->membership($coach, ['user_id' => $first->user_id, 'remaining_sessions' => 0, 'pt_end_date' => today()->subMonth()]);
        $this->membership($coach, ['status' => 'pending']);
        $this->membership($coach, ['is_active' => false]);
        $this->membership($coach, ['pt_package_id' => null]);
        $this->membership($inactive);
        $unassigned = $this->membership(null);
        $page = Livewire::test('pages::dashboard.admin.pt-berjalan')
            ->assertSee('Belum ada coach')->assertSee('Nonaktif')->assertDontSee($hidden->name);
        $coaches = $page->get('coaches')->getCollection()->keyBy('id');
        $this->assertSame(2, $coaches[$coach->id]->active_packages_count);
        $this->assertSame(0, $coaches[$empty->id]->active_packages_count);
        $this->assertSame(1, $coaches[$inactive->id]->active_packages_count);
        $this->assertSame(1, $page->get('unassignedCount'));
        $page->assertSeeHtml(route('admin.pt-berjalan.coach', $coach));
        Livewire::test('pages::dashboard.admin.pt-berjalan.coach', ['coach' => $empty->id])
            ->assertSee('0 paket aktif')->assertSee('Belum ada paket PT berjalan');
        Livewire::test('pages::dashboard.admin.pt-berjalan.coach')
            ->assertSee('Belum ada coach')->assertSee($unassigned->user->name);
    }

    public function test_details_keep_package_cards_and_count_group_as_one_package(): void
    {
        $coach = $this->coach('Coach Group');
        $membership = $this->membership($coach);
        $members = User::factory()->count(2)->create(['role' => 'member']);
        $membership->members()->attach($members->modelKeys());
        $other = $this->membership($this->coach('Coach Other'));
        $page = Livewire::test('pages::dashboard.admin.pt-berjalan.coach', ['coach' => $coach->id])
            ->assertSee('1 paket aktif')->assertSee($members[0]->name)->assertSee($members[1]->name)
            ->assertDontSee($other->user->name)->assertSee('Sisa 5 sesi')
            ->call('openDetailModal', $membership->id)->assertSee('Detail PT Berjalan')
            ->assertSeeHtml(route('admin.membership.renew', ['id' => $membership->id]));
        $page->set('search', $members[1]->name);
        $this->assertSame([$membership->id], $page->get('memberships')->getCollection()->modelKeys());
        $page->set('search', 'No matching member')->assertSee('Tidak ada member yang cocok');
        $this->assertSame(1, $page->get('packageCount'));
    }

    public function test_search_pagination_and_session_order(): void
    {
        for ($index = 0; $index < 13; $index++) {
            $this->coach(sprintf('Coach %02d', $index));
        }
        $page = Livewire::test('pages::dashboard.admin.pt-berjalan');
        $this->assertCount(12, $page->get('coaches'));
        $page->call('setPage', 2)->assertSee('Coach 12')->set('search', 'Coach 00')->assertSee('Coach 00');
        $this->assertSame(1, $page->get('coaches')->currentPage());
        $coach = User::where('name', 'Coach 00')->firstOrFail();
        for ($index = 13; $index > 0; $index--) {
            $this->membership($coach, ['remaining_sessions' => $index]);
        }
        $details = Livewire::test('pages::dashboard.admin.pt-berjalan.coach', ['coach' => $coach->id]);
        $this->assertSame(range(1, 12), $details->get('memberships')->pluck('remaining_sessions')->all());
        $details->call('setPage', 2);
        $this->assertSame([13], $details->get('memberships')->pluck('remaining_sessions')->all());
        $details->set('search', 'none');
        $this->assertSame(1, $details->get('memberships')->currentPage());
    }

    public function test_assigning_unassigned_package_validates_coach_and_moves_it_to_new_group(): void
    {
        $membership = $this->membership(null);
        $coach = $this->coach('Coach Active');
        $inactive = $this->coach('Coach Inactive', false);
        $member = User::factory()->create(['role' => 'member']);
        $page = Livewire::test('pages::dashboard.admin.pt-berjalan.coach')
            ->call('openDetailModal', $membership->id)
            ->call('openCoachModalFromDetail', $membership->id);
        foreach ([$inactive->id, $member->id, 999999] as $id) {
            $page->set('selectedCoachId', $id)->call('saveCoach')->assertHasErrors('selectedCoachId');
            $this->assertNull($membership->fresh()->pt_id);
        }
        $page->set('selectedCoachId', $coach->id)->call('saveCoach')->assertHasNoErrors()
            ->assertSet('showCoachModal', false)->assertSet('showDetailModal', false)->assertSee('0 paket aktif');
        $this->assertSame($coach->id, $membership->fresh()->pt_id);
        Livewire::test('pages::dashboard.admin.pt-berjalan.coach', ['coach' => $coach->id])
            ->assertSee('1 paket aktif')->assertSee($membership->user->name);
    }

    public function test_detail_and_mutations_cannot_target_a_different_coach_or_inactive_package(): void
    {
        $coach = $this->coach('Coach A');
        $other = $this->membership($this->coach('Coach B'));
        $inactive = $this->membership($coach, ['is_active' => false]);
        foreach ([$other, $inactive] as $membership) {
            foreach (['openDetailModal', 'openCoachModal', 'delete'] as $action) {
                Livewire::test('pages::dashboard.admin.pt-berjalan.coach', ['coach' => $coach->id])
                    ->call($action, $membership->id)->assertNotFound();
                $this->assertModelExists($membership);
            }
        }
    }

    public function test_assignment_is_rechecked_when_membership_moves_while_modal_is_open(): void
    {
        $membership = $this->membership(null);
        $coach = $this->coach('Coach A');
        $other = $this->coach('Coach B');
        $page = Livewire::test('pages::dashboard.admin.pt-berjalan.coach')->call('openCoachModal', $membership->id);
        $membership->update(['pt_id' => $other->id]);
        $page->set('selectedCoachId', $coach->id)->call('saveCoach')->assertNotFound();
        $this->assertSame($other->id, $membership->fresh()->pt_id);
    }

    public function test_access_and_admin_only_deletion(): void
    {
        $coach = $this->coach('Coach A');
        $membership = $this->membership($coach);
        $this->get(route('admin.pt-berjalan.coach', $coach))->assertOk();
        $this->get(route('admin.pt-berjalan.unassigned'))->assertOk();
        $this->get(route('admin.pt-berjalan.coach', 999999))->assertNotFound();
        $this->get(route('admin.pt-berjalan.coach', auth()->id()))->assertNotFound();
        Livewire::actingAs(User::factory()->create(['role' => 'kasir_gym']))
            ->test('pages::dashboard.admin.pt-berjalan.coach', ['coach' => $coach->id])
            ->call('delete', $membership->id)->assertForbidden();
        $unassigned = $this->membership(null);
        Livewire::actingAs(User::factory()->headCoach()->create())
            ->test('pages::dashboard.admin.pt-berjalan.coach')->call('openCoachModal', $unassigned->id)->assertForbidden();
        Livewire::actingAs(User::factory()->create(['role' => 'member']))
            ->test('pages::dashboard.admin.pt-berjalan')->assertForbidden();
        Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test('pages::dashboard.admin.pt-berjalan.coach', ['coach' => $coach->id])
            ->call('openDetailModal', $membership->id)->call('delete', $membership->id)
            ->assertSet('showDetailModal', false)->assertSee('0 paket aktif');
        $this->assertModelMissing($membership);
    }

    public function test_start_date_uses_earliest_approved_booking_for_the_selected_membership(): void
    {
        $coach = $this->coach('Coach Booking');
        foreach ([$coach, null] as $assignedCoach) {
            $membership = $this->membership($assignedCoach, ['start_date' => '2026-07-14']);
            foreach ([['2026-07-06', 'approved'], ['2026-07-05', 'approved'], ['2026-07-01', 'pending'], ['2026-07-02', 'cancelled'], ['2026-07-03', 'rejected']] as [$date, $status]) {
                $membership->ptBookings()->create([
                    'member_id' => $membership->user_id, 'pt_id' => $coach->id,
                    'booking_date' => $date, 'booking_time' => '07:00:00',
                    'status' => $status, 'attendance' => 'not_yet',
                ]);
            }
            $other = $this->membership($assignedCoach);
            $other->ptBookings()->create([
                'member_id' => $other->user_id, 'pt_id' => $coach->id,
                'booking_date' => '2026-06-01', 'booking_time' => '07:00:00', 'status' => 'approved',
            ]);

            Livewire::test('pages::dashboard.admin.pt-berjalan.coach', ['coach' => $assignedCoach?->id])
                ->call('openDetailModal', $membership->id)
                ->assertSee('05 Jul 2026')->assertSee('14 Jul 2026')->assertDontSee('01 Jun 2026');
            $this->assertSame('2026-07-14', $membership->fresh()->start_date->toDateString());
        }
    }

    public function test_start_date_displays_empty_message_without_approved_bookings(): void
    {
        $coach = $this->coach('Coach Empty Booking');
        $membership = $this->membership($coach, ['start_date' => '2026-07-14']);
        $page = Livewire::test('pages::dashboard.admin.pt-berjalan.coach', ['coach' => $coach->id])
            ->call('openDetailModal', $membership->id)
            ->assertSee('Tanggal Mulai')->assertSee('Belum ada booking')->assertSee('14 Jul 2026');
        $membership->ptBookings()->create([
            'member_id' => $membership->user_id, 'pt_id' => $coach->id,
            'booking_date' => '2026-07-05', 'booking_time' => '07:00:00', 'status' => 'pending',
        ]);
        $page->call('openDetailModal', $membership->id)->assertSee('Belum ada booking');
    }

    public function test_package_dates_are_visible_on_cards_and_in_details(): void
    {
        $coach = $this->coach('Coach Dates');
        $membership = $this->membership($coach, ['start_date' => '2026-07-14', 'pt_end_date' => '2026-10-14']);
        $page = Livewire::test('pages::dashboard.admin.pt-berjalan.coach', ['coach' => $coach->id])
            ->assertSee('Tanggal Mulai Paket')->assertSee('14 Jul 2026')
            ->assertSee('Tanggal Berakhir PT')->assertSee('14 Oct 2026');
        $page->call('openDetailModal', $membership->id);
        $this->assertSame(2, substr_count($page->html(), '14 Jul 2026'));
        $this->assertSame(2, substr_count($page->html(), '14 Oct 2026'));
        $membership->update(['start_date' => null, 'pt_end_date' => null]);
        $page->call('openDetailModal', $membership->id)->assertSee('—')
            ->assertDontSee('14 Jul 2026')->assertDontSee('14 Oct 2026');
    }

    private function coach(string $name, bool $active = true): User
    {
        return User::factory()->create(['name' => $name, 'role' => 'pt', 'is_active' => $active]);
    }

    /** @param array<string, mixed> $attributes */
    private function membership(?User $coach, array $attributes = []): Membership
    {
        $package = GymPackage::create(['name' => 'Paket PT', 'type' => 'pt', 'category' => 'single', 'price' => 300000, 'pt_sessions' => 10, 'is_active' => true]);

        return Membership::create([
            'user_id' => User::factory()->create(['role' => 'member'])->id, 'pt_id' => $coach?->id,
            'pt_package_id' => $package->id, 'type' => 'pt', 'base_price' => 300000, 'price_paid' => 300000,
            'total_paid' => 300000, 'payment_status' => 'paid', 'status' => 'active', 'is_active' => true,
            'total_sessions' => 10, 'remaining_sessions' => 5, 'start_date' => today(), 'pt_end_date' => today()->addMonth(),
            ...$attributes,
        ]);
    }
}
