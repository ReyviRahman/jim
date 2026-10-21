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
use Illuminate\Validation\ValidationException;
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
    public function test_renewal_preserves_previous_package_and_sets_new_end_date(string $type, string $field): void
    {
        [$form, $members] = $this->form($type);
        $old = $this->previous($members);
        $before = $old->refresh()->getAttributes();
        $form->set($field, '2026-11-05')->call('save')->assertHasNoErrors()->assertRedirect();
        $renewal = Membership::latest('id')->firstOrFail();
        $before['renewal_from'] = $renewal->id;
        $this->assertSame($before, $old->refresh()->getAttributes());
        $this->assertNotSame($old->id, $renewal->id);
        $this->assertSame('2026-11-05', $renewal->$field->toDateString());
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
    public function test_latest_package_is_linked_regardless_of_type_or_state(array $attributes): void
    {
        [$form, $members] = $this->form();
        $old = $this->previous($members, $attributes);
        $before = $old->pt_end_date?->toDateString();
        $form->call('save')->assertHasNoErrors()->assertRedirect();
        $this->assertSame($before, $old->refresh()->pt_end_date?->toDateString());
        $this->assertSame(Membership::latest('id')->firstOrFail()->id, $old->renewal_from);
    }

    public function test_package_expiring_today_is_unchanged(): void
    {
        [$form, $members] = $this->form();
        $old = $this->previous($members, ['pt_end_date' => today()]);
        $form->call('save')->assertHasNoErrors()->assertRedirect();
        $this->assertSame('2026-10-18', $old->refresh()->pt_end_date->toDateString());
    }

    public function test_nonrenewal_does_not_change_previous_package(): void
    {
        [$form, $members] = $this->form();
        $old = $this->previous($members);
        $form->set('is_renewal', '0')->call('save')->assertHasNoErrors()->assertRedirect();
        $this->assertSame('2026-12-01', $old->refresh()->pt_end_date->toDateString());
        $this->assertNull($old->renewal_from);
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
        $this->assertNull(Membership::sole()->renewal_from);
    }

    public function test_newest_package_blocks_an_older_eligible_pt_package(): void
    {
        [$form, $members] = $this->form();
        $old = $this->previous($members);
        $latest = $this->previous($members, ['type' => 'membership']);
        $form->call('save')->assertHasNoErrors()->assertRedirect();
        $this->assertSame('2026-12-01', $old->refresh()->pt_end_date->toDateString());
        $this->assertNull($old->renewal_from);
        $this->assertSame(Membership::latest('id')->firstOrFail()->id, $latest->refresh()->renewal_from);
    }

    public function test_group_preserves_shared_and_separate_previous_packages(): void
    {
        [$form, $members] = $this->form('membership', 3);
        $shared = $this->previous([$members[0], $members[1]]);
        $separate = $this->previous([$members[2]]);
        $updates = [];
        Membership::updated(function (Membership $membership) use (&$updates): void {
            $updates[] = $membership->id;
        });
        $form->call('save')->assertSet('showRenewalSelection', true)->assertNoRedirect();
        $form->set('selectedRenewalIds', [$shared->id, $separate->id])->call('confirmRenewalSelection')
            ->call('save')->assertHasNoErrors()->assertRedirect();
        $this->assertSame('2026-12-01', $shared->refresh()->pt_end_date->toDateString());
        $this->assertSame('2026-12-01', $separate->refresh()->pt_end_date->toDateString());
        $this->assertEqualsCanonicalizing([$shared->id, $separate->id], $updates);
        $renewal = Membership::latest('id')->firstOrFail();
        $this->assertSame($renewal->id, $shared->renewal_from);
        $this->assertSame($renewal->id, $separate->renewal_from);
    }

    public function test_couple_preserves_both_members_previous_packages(): void
    {
        [$form, $members] = $this->form('membership', 2);
        $first = $this->previous([$members[0]]);
        $second = $this->previous([$members[1]], ['remaining_sessions' => 0]);
        $form->call('openRenewalSelection')->set('selectedRenewalIds', [$first->id, $second->id])->call('confirmRenewalSelection');
        $form->call('save')->assertHasNoErrors()->assertRedirect();
        $this->assertSame('2026-12-01', $first->refresh()->pt_end_date->toDateString());
        $this->assertSame('2026-12-01', $second->refresh()->pt_end_date->toDateString());
        $renewal = Membership::latest('id')->firstOrFail();
        $this->assertSame($renewal->id, $first->renewal_from);
        $this->assertSame($renewal->id, $second->renewal_from);
    }

    public function test_failed_renewal_preserves_previous_package_and_rolls_back(): void
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
        $this->assertNull($old->renewal_from);
    }

    public function test_operational_renewal_preserves_previous_package_after_approval(): void
    {
        [$form, $members] = $this->form();
        $old = $this->previous($members);
        $form->set('payment_method', 'operasional')->set('operational_reason', 'Program renewal member')
            ->call('save')->assertHasNoErrors()->assertRedirect();
        $request = MembershipOperationalRequest::sole();
        $this->assertSame('pending', $request->status);
        $this->assertSame('2026-12-01', $old->refresh()->pt_end_date->toDateString());
        $this->assertNull($old->renewal_from);
        $admin = User::factory()->create(['role' => 'admin']);
        $action = app(MembershipOperationalApproval::class);
        $action->approve($admin, $request->id);
        $this->assertSame('2026-12-01', $old->refresh()->pt_end_date->toDateString());
        $renewalId = Membership::latest('id')->firstOrFail()->id;
        $this->assertSame($renewalId, $old->renewal_from);
        $old->update(['pt_end_date' => '2026-12-10']);
        $action->approve($admin, $request->id);
        $this->assertSame('2026-12-10', $old->refresh()->pt_end_date->toDateString());
        $this->assertDatabaseCount('memberships', 2);
        $this->assertSame($renewalId, $old->renewal_from);
    }

    public function test_renewal_finds_owner_without_pivot_and_overwrites_existing_shared_link(): void
    {
        [$form, $members] = $this->form('membership', 2);
        $shared = $this->previous($members);
        $shared->members()->detach($members[0]->id);
        $firstRenewal = $this->previous([$members[1]], ['is_renewal' => true]);
        $shared->update(['renewal_from' => $firstRenewal->id]);

        $form->call('openRenewalSelection')->set('selectedRenewalIds', [$shared->id, $firstRenewal->id])->call('confirmRenewalSelection');

        $form->call('save')->assertHasNoErrors()->assertRedirect();

        $newId = Membership::latest('id')->firstOrFail()->id;
        $this->assertSame($newId, $shared->refresh()->renewal_from);
        $this->assertSame($newId, $firstRenewal->refresh()->renewal_from);
    }

    public static function renewalForms(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('renewalForms')]
    public function test_only_selected_previous_package_is_linked(bool $renew): void
    {
        [$form, $first, $second] = $this->selectionForm($renew);
        $before = $second->refresh()->getAttributes();
        $form->call('save')->assertSet('showRenewalSelection', true)->assertNoRedirect();
        $this->assertDatabaseCount('memberships', 3);
        $form->set('selectedRenewalIds', [(string) $first->id])->call('confirmRenewalSelection')
            ->assertSet('showRenewalSelection', false)->call('save')->assertHasNoErrors()->assertRedirect();
        $this->assertSame(Membership::latest('id')->first()->id, $first->refresh()->renewal_from);
        $this->assertSame($before, $second->refresh()->getAttributes());
    }

    #[DataProvider('renewalForms')]
    public function test_empty_foreign_and_stale_renewal_selections_are_rejected(bool $renew): void
    {
        [$form, $first] = $this->selectionForm($renew);
        $foreign = $this->previous([User::factory()->create(['role' => 'member'])]);
        $form->call('openRenewalSelection')->call('confirmRenewalSelection')->assertHasErrors('selectedRenewalIds')
            ->set('selectedRenewalIds', [$foreign->id])->call('confirmRenewalSelection')->assertHasErrors('selectedRenewalIds.0');
        $form->set('selectedRenewalIds', [$first->id])->call('confirmRenewalSelection');
        $this->previous([$first->user]);
        $form->call('save')->assertNoRedirect()->assertSet('showRenewalSelection', true)->assertSet('confirmedRenewalIds', null);
        $this->assertNull($first->refresh()->renewal_from);
    }

    public function test_operational_approval_honors_selected_package(): void
    {
        [$form, $first, $second] = $this->selectionForm(false);
        $form->call('openRenewalSelection')->set('selectedRenewalIds', [$first->id])->call('confirmRenewalSelection')
            ->set('payment_method', 'operasional')->set('operational_reason', 'Renewal pilihan')
            ->call('save')->assertHasNoErrors()->assertRedirect();
        $request = MembershipOperationalRequest::sole();
        $this->assertSame([$first->id], $request->snapshot['renewal_selected_ids']);
        $this->assertNull($first->refresh()->renewal_from);
        $admin = User::factory()->create(['role' => 'admin']);
        app(MembershipOperationalApproval::class)->approve($admin, $request->id);
        app(MembershipOperationalApproval::class)->approve($admin, $request->id);
        $this->assertSame(Membership::latest('id')->first()->id, $first->refresh()->renewal_from);
        $this->assertNull($second->refresh()->renewal_from);
    }

    public function test_shared_package_needs_no_selection_and_nonrenewal_ignores_multiple_candidates(): void
    {
        [$form, $members] = $this->form('membership', 2);
        $shared = $this->previous($members);
        $form->call('save')->assertHasNoErrors()->assertRedirect();
        $this->assertNotNull($shared->refresh()->renewal_from);

        [$form, $first, $second] = $this->selectionForm(false);
        $form->set('is_renewal', '0')->call('save')->assertHasNoErrors()->assertRedirect();
        $this->assertNull($first->refresh()->renewal_from);
        $this->assertNull($second->refresh()->renewal_from);
    }

    public function test_operational_approval_rolls_back_when_selected_candidates_change(): void
    {
        [$form, $first] = $this->selectionForm(false);
        $form->call('openRenewalSelection')->set('selectedRenewalIds', [$first->id])->call('confirmRenewalSelection')
            ->set('payment_method', 'operasional')->set('operational_reason', 'Renewal pilihan')
            ->call('save')->assertHasNoErrors();
        $request = MembershipOperationalRequest::sole();
        $this->previous([$first->user]);
        $count = Membership::count();
        try {
            app(MembershipOperationalApproval::class)->approve(User::factory()->create(['role' => 'admin']), $request->id);
            $this->fail('Approval must reject stale candidates.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('selectedRenewalIds', $exception->errors());
        }
        $this->assertDatabaseCount('memberships', $count);
        $this->assertSame('pending', $request->refresh()->status);
        $this->assertNull($first->refresh()->renewal_from);
    }

    private function selectionForm(bool $renew): array
    {
        [$form, $members] = $this->form('membership', 2);
        $source = $this->previous($members, ['type' => 'membership', 'gym_package_id' => $form->get('gym_package_id')]);
        $first = $this->previous([$members[0]]);
        $second = $this->previous([$members[1]]);
        if ($renew) {
            $values = [];
            foreach (['admin_id', 'follow_up_id', 'follow_up_id_two', 'transaction_type', 'package_name', 'notes', 'pt_trial_interest', 'has_addon', 'waivers'] as $key) {
                $values[$key] = $form->get($key);
            }
            $form = Livewire::test('pages::dashboard.admin.renew.create', ['id' => $source->id])->set($values);
        }

        return [$form, $first, $second];
    }

    /** @param array<int, User> $members
     * @param  array<string, mixed>  $attributes
     */
    private function previous(array $members, array $attributes = []): Membership
    {
        $membership = Membership::create(array_replace([
            'user_id' => $members[0]->id, 'type' => 'pt', 'status' => 'active', 'is_active' => true,
            'start_date' => '2026-10-01', 'pt_end_date' => '2026-12-01', 'membership_end_date' => '2026-11-20',
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
