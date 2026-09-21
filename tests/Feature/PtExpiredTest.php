<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class PtExpiredTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-21 00:30:00', 'Asia/Jakarta'));
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_date_bounds_type_and_status_are_applied_to_counts_and_packages(): void
    {
        $coach = User::factory()->create(['role' => 'pt']);
        $oldest = $this->membership($coach, ['pt_end_date' => '2026-08-22']);
        $latest = $this->membership($coach, ['pt_end_date' => '2026-09-20', 'is_active' => false, 'status' => 'completed', 'remaining_sessions' => 0]);
        foreach (['2026-08-21', '2026-09-21', '2026-09-22', null] as $date) {
            $this->membership($coach, ['pt_end_date' => $date]);
        }
        foreach (['membership', 'bundle_pt_membership', 'visit'] as $type) {
            $this->membership($coach, ['type' => $type]);
        }
        $this->assertEqualsCanonicalizing([$oldest->id, $latest->id], Membership::recentlyExpiredPt()->pluck('id')->all());
        $page = Livewire::test('pages::dashboard.admin.pt-berjalan', ['expired' => true]);
        $this->assertSame(2, $page->get('coaches')->first()->active_packages_count);
        $details = Livewire::test('pages::dashboard.admin.pt-berjalan.coach', ['coach' => $coach->id, 'expired' => true])
            ->assertSee('2 paket expired')->assertSee($oldest->user->name)->assertSee($latest->user->name);
        $this->assertSame([$latest->id, $oldest->id], $details->get('memberships')->pluck('id')->all());
        $details->call('openDetailModal', $oldest->id)->assertSee('Detail PT Expired')->assertSee('22 Aug 2026');
        $this->travelTo(Carbon::parse('2026-09-22 00:00:00', 'Asia/Jakarta'));
        $this->assertFalse(Membership::recentlyExpiredPt()->whereKey($oldest)->exists());
    }

    public function test_routes_sidebar_and_livewire_access_follow_existing_roles(): void
    {
        $coach = User::factory()->create(['role' => 'pt']);
        $this->membership($coach);
        $this->membership(null);
        $urls = [route('admin.pt-expired.index'), route('admin.pt-expired.coach', $coach), route('admin.pt-expired.unassigned')];
        foreach ([User::factory()->create(['role' => 'admin']), User::factory()->create(['role' => 'kasir_gym']), User::factory()->headCoach()->create()] as $user) {
            $this->actingAs($user);
            foreach ($urls as $url) {
                $response = $this->get($url);
                $this->assertSame(200, $response->status(), $url);
                $response->assertSee('PT Expired')->assertSee('paket expired')
                    ->assertSee(route('admin.pt-expired.index'), false);
            }
            $this->get($urls[0])->assertSeeInOrder(['PT Berjalan', 'PT Expired', 'Data PT Expired']);
        }
        foreach (['member', 'pt', 'kasir_minum', 'head_coach'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach ($urls as $url) {
                $this->get($url)->assertRedirect(route('home'));
            }
        }
        auth()->logout();
        foreach ($urls as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_unauthorized_roles_cannot_access_livewire_components(): void
    {
        foreach (['member', 'pt', 'kasir_minum', 'head_coach'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            Livewire::test('pages::dashboard.admin.pt-berjalan', ['expired' => true])->assertForbidden();
            Livewire::test('pages::dashboard.admin.pt-berjalan.coach', ['expired' => true])->assertForbidden();
        }
    }

    public function test_coach_groups_search_pagination_and_group_packages(): void
    {
        $active = User::factory()->create(['role' => 'pt', 'name' => 'Coach Active']);
        $inactive = User::factory()->create(['role' => 'pt', 'is_active' => false, 'name' => 'Coach Inactive']);
        $hidden = User::factory()->create(['role' => 'pt', 'is_active' => false]);
        $package = $this->membership($inactive);
        $groupMember = User::factory()->create(['role' => 'member', 'name' => 'Group Member']);
        $package->members()->attach($groupMember);
        $unassigned = $this->membership(null);
        $page = Livewire::test('pages::dashboard.admin.pt-berjalan', ['expired' => true])
            ->assertSee('Belum ada coach')->assertSee('Nonaktif')->assertDontSee($hidden->name)
            ->assertSeeHtml(route('admin.pt-expired.coach', $inactive))
            ->assertSeeHtml(route('admin.pt-expired.unassigned'));
        $counts = $page->get('coaches')->getCollection()->keyBy('id');
        $this->assertSame(0, $counts[$active->id]->active_packages_count);
        $this->assertSame(1, $counts[$inactive->id]->active_packages_count);
        $page->assertSet('unassignedCount', 1)->set('search', 'Inactive')->assertDontSee($active->name);
        Livewire::test('pages::dashboard.admin.pt-berjalan.coach', ['expired' => true])->assertSee($unassigned->user->name);
        $details = Livewire::test('pages::dashboard.admin.pt-berjalan.coach', ['coach' => $inactive->id, 'expired' => true])
            ->assertSee('1 paket expired')->set('search', 'Group Member')->assertSee($groupMember->name)
            ->call('openDetailModal', $package->id)->assertSee($package->user->name);
        for ($i = 0; $i < 12; $i++) {
            $this->membership($inactive);
            User::factory()->create(['role' => 'pt']);
        }
        $details->set('search', '')->call('gotoPage', 2);
        $this->assertCount(1, $details->get('memberships')->items());
        $details->set('search', 'Group Member')->assertSet('paginators.page', 1);
        $page->set('search', '')->call('gotoPage', 2);
        $this->assertCount(2, $page->get('coaches')->items());
        $page->set('search', 'Inactive')->assertSet('paginators.page', 1);
        $this->get(route('admin.pt-expired.coach', $hidden))->assertNotFound();
        $this->get(route('admin.pt-expired.coach', auth()->id()))->assertNotFound();
        $this->get(route('admin.pt-expired.coach', 999999))->assertNotFound();
    }

    public function test_assignment_without_package_relation_and_admin_only_deletion(): void
    {
        $coach = User::factory()->create(['role' => 'pt']);
        $inactive = User::factory()->create(['role' => 'pt', 'is_active' => false]);
        $package = $this->membership(null);
        Livewire::actingAs(User::factory()->headCoach()->create())
            ->test('pages::dashboard.admin.pt-berjalan.coach', ['expired' => true])
            ->call('openDetailModal', $package->id)->assertDontSee('Pilih Coach')->assertDontSee('Hapus')
            ->call('openCoachModal', $package->id)->assertForbidden();
        Livewire::test('pages::dashboard.admin.pt-berjalan.coach', ['expired' => true])->call('saveCoach')->assertForbidden();
        Livewire::test('pages::dashboard.admin.pt-berjalan.coach', ['expired' => true])->call('delete', $package->id)->assertForbidden();
        $page = Livewire::actingAs(User::factory()->create(['role' => 'kasir_gym']))
            ->test('pages::dashboard.admin.pt-berjalan.coach', ['expired' => true])
            ->call('openDetailModal', $package->id)->call('openCoachModalFromDetail', $package->id)->assertSee('Paket Terhapus');
        foreach ([$inactive->id, auth()->id(), 999999] as $invalid) {
            $page->set('selectedCoachId', $invalid)->call('saveCoach')->assertHasErrors('selectedCoachId');
        }
        $page->set('selectedCoachId', $coach->id)->call('saveCoach')->assertHasNoErrors()->assertSee('0 paket expired');
        $this->assertSame($coach->id, $package->fresh()->pt_id);
        Livewire::test('pages::dashboard.admin.pt-berjalan.coach', ['coach' => $coach->id, 'expired' => true])
            ->call('delete', $package->id)->assertForbidden();
        Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test('pages::dashboard.admin.pt-berjalan.coach', ['coach' => $coach->id, 'expired' => true])
            ->call('delete', $package->id)->assertSee('0 paket expired');
        $this->assertModelMissing($package);
    }

    public function test_actions_cannot_target_other_groups_or_out_of_range_packages(): void
    {
        $other = $this->membership(User::factory()->create(['role' => 'pt']));
        $future = $this->membership(null, ['pt_end_date' => '2026-09-21']);
        foreach ([$other, $future] as $package) {
            foreach (['openDetailModal', 'openCoachModal', 'delete'] as $action) {
                Livewire::test('pages::dashboard.admin.pt-berjalan.coach', ['expired' => true])
                    ->call($action, $package->id)->assertNotFound();
                $this->assertModelExists($package);
            }
        }
        $coach = User::factory()->create(['role' => 'pt']);
        foreach ([['pt_end_date' => '2026-09-22'], ['pt_id' => $coach->id]] as $change) {
            $package = $this->membership(null);
            $page = Livewire::test('pages::dashboard.admin.pt-berjalan.coach', ['expired' => true])->call('openCoachModal', $package->id);
            $package->update($change);
            $page->set('selectedCoachId', $coach->id)->call('saveCoach')->assertNotFound();
        }
    }

    public function test_index_mode_cannot_be_changed_by_client(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::test('pages::dashboard.admin.pt-berjalan', ['expired' => true])->set('expired', false);
    }

    public function test_detail_mode_cannot_be_changed_by_client(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::test('pages::dashboard.admin.pt-berjalan.coach', ['expired' => true])->set('expired', false);
    }

    /** @param array<string, mixed> $attributes */
    private function membership(?User $coach, array $attributes = []): Membership
    {
        return Membership::create([
            'user_id' => User::factory()->create(['role' => 'member'])->id,
            'pt_id' => $coach?->id, 'type' => 'pt', 'pt_package_id' => null,
            'base_price' => 300000, 'price_paid' => 300000, 'total_paid' => 300000,
            'payment_status' => 'paid', 'status' => 'active', 'is_active' => true,
            'total_sessions' => 10, 'remaining_sessions' => 5,
            'start_date' => '2026-08-01', 'pt_end_date' => '2026-09-20',
            ...$attributes,
        ]);
    }
}
