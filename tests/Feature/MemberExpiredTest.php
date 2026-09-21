<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class MemberExpiredTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-21 00:30:00', 'Asia/Jakarta'));
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_date_window_includes_all_types_statuses_and_renewed_memberships(): void
    {
        $oldest = $this->membership(['membership_end_date' => '2026-08-22']);
        $expected = [$oldest->id];
        foreach (['membership', 'pt', 'bundle_pt_membership', 'visit'] as $type) {
            foreach (['active', 'pending', 'rejected', 'completed'] as $status) {
                $expected[] = $this->membership(['type' => $type, 'status' => $status, 'is_active' => false])->id;
            }
        }
        foreach (['2026-08-21', '2026-09-21', '2026-09-22', null] as $date) {
            $this->membership(['membership_end_date' => $date]);
        }
        $this->membership(['user_id' => $oldest->user_id, 'membership_end_date' => '2026-10-21']);

        $page = Livewire::test('pages::dashboard.admin.membership.non-member')
            ->assertSee('Member Expired')->assertSee('22 Aug 2026')->assertSee('20 Sep 2026');
        $this->assertSame(array_reverse($expected), $page->get('memberships')->pluck('id')->all());
        $this->travelTo(Carbon::parse('2026-09-22 00:00:00', 'Asia/Jakarta'));
        $this->assertNotContains($oldest->id, Livewire::test('pages::dashboard.admin.membership.non-member')->get('memberships')->pluck('id')->all());
    }

    public function test_search_matches_owner_and_group_contacts_without_escaping_date_window(): void
    {
        $owner = User::factory()->create(['name' => 'Pemilik Khusus', 'email' => 'pemilik@example.test', 'phone' => '081234567890']);
        $member = User::factory()->create(['name' => 'Anggota Khusus', 'email' => 'anggota@example.test', 'phone' => '089876543210']);
        $other = User::factory()->create(['name' => 'Anggota Kedua']);
        $package = $this->membership(['user_id' => $owner->id, 'package_name' => 'Paket Couple']);
        $package->members()->attach([$member->id, $other->id]);
        $outside = $this->membership(['user_id' => $owner->id, 'membership_end_date' => '2026-08-21']);
        $outside->members()->attach($member);
        $this->membership();

        $page = Livewire::test('pages::dashboard.admin.membership.non-member')
            ->assertSee('Paket Couple')->assertSee('Anggota Kedua');
        foreach ([$owner->name, $owner->email, $owner->phone, $member->name, $member->email, $member->phone] as $search) {
            $page->set('search', $search);
            $this->assertSame([$package->id], $page->get('memberships')->pluck('id')->all());
        }
        $row = $page->get('memberships')->first();
        foreach (['user', 'members', 'gymPackage', 'ptPackage'] as $relation) {
            $this->assertTrue($row->relationLoaded($relation));
        }
        $page->set('search', 'tidak-ditemukan')->assertSee('Tidak ada membership expired');
    }

    public function test_pagination_resets_when_search_changes(): void
    {
        $first = $this->membership();
        for ($i = 0; $i < 20; $i++) {
            $this->membership();
        }
        $page = Livewire::test('pages::dashboard.admin.membership.non-member');
        $this->assertCount(20, $page->get('memberships')->items());
        $page->call('gotoPage', 2);
        $this->assertSame([$first->id], $page->get('memberships')->pluck('id')->all());
        $page->set('search', $first->user->email)->assertSet('paginators.page', 1);
        $this->assertSame([$first->id], $page->get('memberships')->pluck('id')->all());
    }

    public function test_existing_route_access_and_sidebar_label_are_preserved(): void
    {
        $url = route('admin.membership.non-member');
        foreach ([User::factory()->create(['role' => 'admin']), User::factory()->create(['role' => 'kasir_gym']), User::factory()->headCoach()->create()] as $user) {
            $this->actingAs($user)->get($url)->assertOk()
                ->assertSee('Member Expired')->assertDontSee('Member Tidak Aktif')->assertSee($url, false);
        }
        foreach (['member', 'pt', 'kasir_minum', 'head_coach'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get($url)->assertRedirect(route('home'));
        }
        auth()->logout();
        $this->get($url)->assertRedirect(route('login'));
    }

    public function test_card_opens_details_and_modal_can_be_closed(): void
    {
        $membership = $this->membership(['package_name' => 'Paket Detail']);

        Livewire::test('pages::dashboard.admin.membership.non-member')
            ->assertSeeHtml('wire:click="openDetailModal('.$membership->id.')"')
            ->assertDontSee('Detail Member Expired')
            ->call('openDetailModal', $membership->id)
            ->assertSet('selectedMembershipId', $membership->id)
            ->assertSee('Detail Member Expired')
            ->assertSee($membership->user->email)
            ->assertSee('Paket Detail')
            ->call('closeDetailModal')
            ->assertSet('selectedMembershipId', null)
            ->assertDontSee('Detail Member Expired');
    }

    public function test_modal_rejects_memberships_outside_expired_window(): void
    {
        $membership = $this->membership(['membership_end_date' => '2026-09-22']);

        Livewire::test('pages::dashboard.admin.membership.non-member')
            ->call('openDetailModal', $membership->id)
            ->assertNotFound();
    }

    /** @param array<string, mixed> $attributes */
    private function membership(array $attributes = []): Membership
    {
        return Membership::create([
            'user_id' => $attributes['user_id'] ?? User::factory()->create(['role' => 'member'])->id,
            'type' => 'membership', 'base_price' => 300000, 'price_paid' => 300000,
            'total_paid' => 300000, 'payment_status' => 'paid', 'status' => 'active',
            'is_active' => true, 'start_date' => '2026-08-01', 'membership_end_date' => '2026-09-20',
            ...$attributes,
        ]);
    }
}
