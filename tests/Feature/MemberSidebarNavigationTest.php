<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberSidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_without_pt_membership_does_not_see_pt_schedule_navigation(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $gymMembership = $this->createMembership($member, 'membership');
        $gymMembership->members()->attach($member);

        $this->actingAs($member)
            ->get(route('member.membership.index'))
            ->assertOk()
            ->assertDontSee('href="'.route('member.jadwal-pt.index').'"', false);
    }

    public function test_membership_owner_with_pt_membership_sees_pt_schedule_navigation(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $this->createMembership($member, 'pt');

        $this->actingAs($member)
            ->get(route('member.membership.index'))
            ->assertOk()
            ->assertSee('href="'.route('member.jadwal-pt.index').'"', false)
            ->assertSee('Jadwal PT');
    }

    public function test_shared_member_with_pt_membership_sees_pt_schedule_navigation(): void
    {
        $owner = User::factory()->create(['role' => 'member']);
        $sharedMember = User::factory()->create(['role' => 'member']);
        $ptMembership = $this->createMembership($owner, 'pt');
        $ptMembership->members()->attach($sharedMember);

        $this->actingAs($sharedMember)
            ->get(route('member.membership.index'))
            ->assertOk()
            ->assertSee('href="'.route('member.jadwal-pt.index').'"', false)
            ->assertSee('Jadwal PT');
    }

    private function createMembership(User $owner, string $type): Membership
    {
        return Membership::create([
            'user_id' => $owner->id,
            'type' => $type,
            'base_price' => 300000,
            'price_paid' => 300000,
            'total_paid' => 300000,
            'payment_status' => 'paid',
            'start_date' => now()->toDateString(),
            'status' => 'active',
        ]);
    }
}
