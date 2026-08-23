<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\PtSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HeadCoachAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_head_coach_identity_is_based_on_email_case_insensitively(): void
    {
        $headCoach = User::factory()->headCoach()->make([
            'email' => 'HEADCOACHFG@GMAIL.COM',
        ]);
        $legacyRoleUser = User::factory()->make([
            'email' => 'legacy-head-coach@example.com',
            'role' => 'head_coach',
        ]);

        $this->assertTrue($headCoach->isHeadCoach());
        $this->assertFalse($legacyRoleUser->isHeadCoach());
    }

    public function test_designated_head_coach_can_access_pt_and_head_coach_routes(): void
    {
        $headCoach = User::factory()->headCoach()->create();

        $this->actingAs($headCoach)
            ->get(route('pt.absensi'))
            ->assertOk();

        $this->get(route('admin.cicilan.index'))
            ->assertOk();

        $this->get(route('admin.sesi-pt.index'))
            ->assertOk();
    }

    public function test_regular_pt_and_legacy_head_coach_role_cannot_access_head_coach_routes(): void
    {
        $regularPt = User::factory()->create(['role' => 'pt']);
        $legacyRoleUser = User::factory()->create(['role' => 'head_coach']);

        foreach ([$regularPt, $legacyRoleUser] as $user) {
            $this->actingAs($user)
                ->get(route('admin.cicilan.index'))
                ->assertRedirect(route('home'));

            $this->get(route('admin.sesi-pt.index'))
                ->assertRedirect(route('home'));
        }
    }

    public function test_head_coach_is_redirected_to_head_coach_dashboard_after_login(): void
    {
        User::factory()->headCoach()->create();

        Livewire::test('pages::login')
            ->set('email', User::HEAD_COACH_EMAIL)
            ->set('password', 'password')
            ->call('login')
            ->assertRedirect(route('admin.cicilan.index'));
    }

    public function test_head_coach_dashboard_links_prioritize_the_head_coach_dashboard(): void
    {
        $headCoach = User::factory()->headCoach()->create();

        $this->actingAs($headCoach)
            ->get(route('home'))
            ->assertOk()
            ->assertSee(route('admin.cicilan.index'), false);

        Livewire::actingAs($headCoach)
            ->test('navbar')
            ->assertSeeHtml(route('admin.cicilan.index'));
    }

    public function test_head_coach_remains_blocked_from_admin_only_settings(): void
    {
        $headCoach = User::factory()->headCoach()->create();

        $this->actingAs($headCoach)
            ->get(route('admin.whatsapp.settings'))
            ->assertRedirect(route('home'));
    }

    public function test_pt_schedule_is_auto_approved_only_for_head_coach_email(): void
    {
        $headCoach = User::factory()->headCoach()->create();
        $headCoachMembership = $this->createPtMembership();

        Livewire::actingAs($headCoach)
            ->test('pages::dashboard.admin.jadwal-pt.index')
            ->set('scheduleMembershipId', $headCoachMembership->id)
            ->set('scheduleType', 'fleksibel')
            ->call('saveSchedule');

        $headCoachSchedule = PtSchedule::where('membership_id', $headCoachMembership->id)->firstOrFail();
        $this->assertSame('approved', $headCoachSchedule->status);
        $this->assertSame($headCoach->id, $headCoachSchedule->approved_by);

        $legacyRoleUser = User::factory()->create(['role' => 'head_coach']);
        $legacyMembership = $this->createPtMembership();

        $legacyComponent = Livewire::actingAs($legacyRoleUser)
            ->test('pages::dashboard.admin.jadwal-pt.index')
            ->set('scheduleMembershipId', $legacyMembership->id)
            ->set('scheduleType', 'fleksibel')
            ->call('saveSchedule');

        $legacySchedule = PtSchedule::where('membership_id', $legacyMembership->id)->firstOrFail();
        $this->assertSame('pending', $legacySchedule->status);
        $this->assertNull($legacySchedule->approved_by);

        $legacyComponent->call('approveSchedule', $legacyMembership->id);

        $this->assertSame('pending', $legacySchedule->fresh()->status);
    }

    private function createPtMembership(): Membership
    {
        $member = User::factory()->create(['role' => 'member']);
        $personalTrainer = User::factory()->create(['role' => 'pt']);

        return Membership::create([
            'user_id' => $member->id,
            'pt_id' => $personalTrainer->id,
            'type' => 'pt',
            'base_price' => 100000,
            'price_paid' => 100000,
            'total_paid' => 100000,
            'payment_status' => 'paid',
            'start_date' => today(),
            'pt_end_date' => today()->addMonth(),
            'total_sessions' => 10,
            'remaining_sessions' => 10,
            'status' => 'active',
        ]);
    }
}
