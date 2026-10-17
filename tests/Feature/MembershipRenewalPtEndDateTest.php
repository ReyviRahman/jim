<?php

namespace Tests\Feature;

use App\Actions\MembershipOperationalApproval;
use App\Actions\StoreMembershipWaivers;
use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\MembershipOperationalRequest;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MembershipRenewalPtEndDateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 18)->startOfDay());
        Storage::fake('local');
        Storage::fake('public');
    }

    public static function packageTypes(): array
    {
        return [['membership', 'membership_end_date'], ['pt', 'pt_end_date'], ['visit', 'membership_end_date'], ['bundle_pt_membership', 'pt_end_date']];
    }

    #[DataProvider('packageTypes')]
    public function test_renewal_copies_the_correct_end_date(string $type, string $field): void
    {
        [$form, $members] = $this->form($type);
        $old = $this->previous($members);
        $before = $old->refresh()->getAttributes();
        $form->set($field, '2026-11-05')->call('save')->assertHasNoErrors()->assertRedirect();
        $this->assertSame('2026-11-05', $old->refresh()->pt_end_date->toDateString());
        foreach (['remaining_sessions', 'status', 'is_active', 'start_date', 'total_sessions'] as $attribute) {
            $this->assertEquals($before[$attribute], $old->getRawOriginal($attribute));
        }
        $this->assertSame(0, $old->transactions()->count());
    }

    public static function ineligiblePackages(): array
    {
        return [
            [['type' => 'membership']], [['type' => 'bundle_pt_membership']],
            [['status' => 'pending']], [['status' => 'completed']], [['is_active' => false]],
            [['remaining_sessions' => 0]], [['pt_end_date' => '2026-10-17']], [['pt_end_date' => null]],
        ];
    }

    #[DataProvider('ineligiblePackages')]
    public function test_ineligible_latest_package_is_unchanged(array $attributes): void
    {
        [$form, $members] = $this->form();
        $old = $this->previous($members, $attributes);
        $before = $old->pt_end_date?->toDateString();
        $form->call('save')->assertHasNoErrors()->assertRedirect();
        $this->assertSame($before, $old->refresh()->pt_end_date?->toDateString());
    }

    public function test_package_expiring_today_is_eligible(): void
    {
        [$form, $members] = $this->form();
        $old = $this->previous($members, ['pt_end_date' => today()]);
        $form->call('save')->assertHasNoErrors()->assertRedirect();
        $this->assertSame('2026-11-16', $old->refresh()->pt_end_date->toDateString());
    }

    public function test_nonrenewal_does_not_change_previous_package(): void
    {
        [$form, $members] = $this->form();
        $old = $this->previous($members);
        $form->set('is_renewal', '0')->call('save')->assertHasNoErrors()->assertRedirect();
        $this->assertSame('2026-12-01', $old->refresh()->pt_end_date->toDateString());
    }

    public function test_pending_package_without_dates_does_not_change_previous_package(): void
    {
        [$form, $members] = $this->form();
        $old = $this->previous($members);
        $form->set('payment_type', 'partial')->set('amount_paid', 100000)->call('save')->assertHasNoErrors()->assertRedirect();
        $this->assertSame('2026-12-01', $old->refresh()->pt_end_date->toDateString());
    }

    public function test_renewal_without_a_previous_package_can_be_saved(): void
    {
        [$form] = $this->form();
        $form->call('save')->assertHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('memberships', 1);
    }

    public function test_newest_package_blocks_an_older_eligible_pt_package(): void
    {
        [$form, $members] = $this->form();
        $old = $this->previous($members);
        $this->previous($members, ['type' => 'membership']);
        $form->call('save')->assertHasNoErrors()->assertRedirect();
        $this->assertSame('2026-12-01', $old->refresh()->pt_end_date->toDateString());
    }

    public function test_group_checks_each_member_and_updates_shared_package_once(): void
    {
        [$form, $members] = $this->form('membership', 3);
        $shared = $this->previous([$members[0], $members[1]]);
        $separate = $this->previous([$members[2]]);
        $updates = [];
        Membership::updated(function (Membership $membership) use (&$updates): void {
            $updates[] = $membership->id;
        });
        $form->call('save')->assertHasNoErrors()->assertRedirect();
        $this->assertSame('2026-11-16', $shared->refresh()->pt_end_date->toDateString());
        $this->assertSame('2026-11-16', $separate->refresh()->pt_end_date->toDateString());
        $this->assertSame(1, count(array_keys($updates, $shared->id, true)));
    }

    public function test_couple_checks_the_other_members_latest_package_independently(): void
    {
        [$form, $members] = $this->form('membership', 2);
        $first = $this->previous([$members[0]]);
        $second = $this->previous([$members[1]], ['remaining_sessions' => 0]);
        $form->call('save')->assertHasNoErrors()->assertRedirect();
        $this->assertSame('2026-11-16', $first->refresh()->pt_end_date->toDateString());
        $this->assertSame('2026-12-01', $second->refresh()->pt_end_date->toDateString());
    }

    public function test_failure_after_updating_previous_package_rolls_back_everything(): void
    {
        [$form, $members] = $this->form();
        $old = $this->previous($members);
        $this->partialMock(StoreMembershipWaivers::class, function ($mock): void {
            $mock->shouldReceive('execute')->once()->andThrow(new \RuntimeException('Failed waiver storage'));
        });
        $form->call('save')->assertNoRedirect();
        $this->assertSame('2026-12-01', $old->refresh()->pt_end_date->toDateString());
        $this->assertDatabaseCount('memberships', 1);
        $this->assertDatabaseCount('membership_transactions', 0);
    }

    public function test_operational_renewal_updates_only_on_approval_and_only_once(): void
    {
        [$form, $members] = $this->form();
        $old = $this->previous($members);
        $form->set('payment_method', 'operasional')->set('operational_reason', 'Program renewal member')
            ->call('save')->assertHasNoErrors()->assertRedirect();
        $request = MembershipOperationalRequest::sole();
        $this->assertSame('pending', $request->status);
        $this->assertSame('2026-12-01', $old->refresh()->pt_end_date->toDateString());
        $admin = User::factory()->create(['role' => 'admin']);
        $action = app(MembershipOperationalApproval::class);
        $action->approve($admin, $request->id);
        $this->assertSame('2026-11-16', $old->refresh()->pt_end_date->toDateString());
        $old->update(['pt_end_date' => '2026-12-10']);
        $action->approve($admin, $request->id);
        $this->assertSame('2026-12-10', $old->refresh()->pt_end_date->toDateString());
        $this->assertDatabaseCount('memberships', 2);
    }

    /** @param array<int, User> $members
     * @param  array<string, mixed>  $attributes
     */
    private function previous(array $members, array $attributes = []): Membership
    {
        $membership = Membership::create(array_replace([
            'user_id' => $members[0]->id, 'type' => 'pt', 'status' => 'active', 'is_active' => true,
            'start_date' => '2026-10-01', 'pt_end_date' => '2026-12-01',
            'remaining_sessions' => 5, 'total_sessions' => 10, 'payment_status' => 'paid',
            'base_price' => 300000, 'price_paid' => 300000, 'total_paid' => 300000,
        ], $attributes));
        $membership->members()->attach(array_map(fn (User $user): int => $user->id, $members));

        return $membership;
    }

    /** @return array{Testable, array<int, User>} */
    private function form(string $type = 'membership', int $count = 1): array
    {
        $actor = User::factory()->create(['role' => 'kasir_gym', 'shift' => Shift::factory()->create(['role' => 'kasir_gym'])->id]);
        $this->actingAs($actor);
        $members = User::factory()->count($count)->create(['role' => 'member', 'photo' => 'existing.webp'])->all();
        $attributes = [
            'name' => 'Paket renewal', 'category' => $count === 1 ? 'single' : ($count === 2 ? 'couple' : 'group'),
            'max_members' => $count, 'price' => 300000, 'discount' => 0, 'is_active' => true, 'duration_months' => 1,
        ];
        $form = Livewire::withQueryParams(['users' => array_map(fn (User $user): int => $user->id, $members)])
            ->test('pages::dashboard.admin.membership.paket')->set('is_renewal', '1')->set('registration_type', $type);
        if ($type !== 'pt') {
            $gym = GymPackage::create($attributes + ['type' => $type === 'visit' ? 'visit' : 'gym']);
            $form->set('gym_package_id', $gym->id);
        }
        if (in_array($type, ['pt', 'bundle_pt_membership'], true)) {
            $pt = GymPackage::create($attributes + ['type' => 'pt', 'pt_sessions' => 10]);
            $form->set('pt_package_id', $pt->id);
        }
        $form->set('admin_id', $actor->id)->set('follow_up_id', $actor->id)->set('follow_up_id_two', $actor->id)
            ->set('transaction_type', 'MEMBERSHIP')->set('package_name', 'Renewal')->set('notes', 'Renewal PT')
            ->set('pt_trial_interest', 'no')->set('has_addon', 'no');
        $image = imagecreatetruecolor(400, 160);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imageline($image, 25, 100, 360, 50, imagecolorallocate($image, 0, 0, 0));
        ob_start();
        imagepng($image);
        $signature = 'data:image/png;base64,'.base64_encode(ob_get_clean());
        imagedestroy($image);
        foreach ($members as $member) {
            $form->set('waivers.'.$member->id.'.accepted', true)->set('waivers.'.$member->id.'.signature', $signature);
        }

        return [$form, $members];
    }
}
