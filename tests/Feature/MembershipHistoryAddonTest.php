<?php

namespace Tests\Feature;

use App\Actions\MembershipAddonApproval;
use App\Models\Membership;
use App\Models\MembershipAddon;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MembershipHistoryAddonTest extends TestCase
{
    use RefreshDatabase;

    private function membership(array $attributes = []): Membership
    {
        $addon = MembershipAddon::factory()->create();
        $membership = $addon->membership;
        $addon->delete();
        $membership->update($attributes);

        return $membership;
    }

    public static function states(): array
    {
        return [['pending', false, null, 'pending'], ['active', true, 0, 'active'], ['completed', false, 5, 'active'], ['completed', false, 40, 'completed']];
    }

    #[DataProvider('states')]
    public function test_history_submission_requires_separate_approval(string $status, bool $active, ?int $daysAgo, string $expected): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $membership = $this->membership(['status' => $status, 'is_active' => $active, 'start_date' => $daysAgo === null ? null : today()->subDays($daysAgo)]);
        $form = Livewire::actingAs($admin)->test('pages::dashboard.admin.riwayat.detail', ['user' => $membership->user])
            ->call('openAddon', $membership->id)->assertSee('Membership 1 Monthly Pass')
            ->set('addon_name', 'Bonus dari riwayat')->set('addon_duration_months', 1);
        if ($daysAgo === 40) {
            $form->assertSee('status langsung completed');
        }
        $form->call('submitAddon')->assertHasNoErrors()->assertSet('addonMembershipId', null)->assertDispatched('addon-approvals-updated')
            ->assertSee('Add-on menunggu persetujuan Manager')->assertSee('Bonus dari riwayat');
        $addon = MembershipAddon::sole();
        $this->assertSame('pending', $addon->approval_status);
        $this->assertSame('pending', $addon->status);
        $this->assertSame($admin->id, $addon->requested_by);
        $this->assertFalse(MembershipAddon::usable()->exists());
        $this->assertDatabaseCount('membership_transactions', 0);
        Livewire::test('dashboard.membership-addon-approvals')->assertSee('Bonus dari riwayat');
        app(MembershipAddonApproval::class)->approve($admin, $addon->id);
        $this->assertSame($expected, $addon->refresh()->status);
        if ($daysAgo !== null) {
            $this->assertSame($membership->start_date->toDateString(), $addon->start_date->toDateString());
            $this->assertSame($membership->start_date->copy()->addDays(29)->toDateString(), $addon->end_date->toDateString());
        }
    }

    public function test_shared_member_and_extended_pt_end_do_not_change_addon_start(): void
    {
        $membership = $this->membership(['start_date' => today()->subDays(10), 'pt_end_date' => today()->addMonths(4)]);
        $shared = User::factory()->create(['role' => 'member']);
        $membership->members()->attach($shared);
        Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test('pages::dashboard.admin.riwayat.detail', ['user' => $shared])
            ->call('openAddon', $membership->id)->set('addon_name', 'Shared bonus')
            ->set('addon_duration_months', 1)->set('addon_duration_weeks', 2)->set('addon_duration_days', 3)
            ->call('submitAddon')->assertHasNoErrors();
        $addon = MembershipAddon::sole();
        $this->assertSame(47, $addon->durationInDays());
        $this->assertSame($membership->start_date->copy()->addDays(46)->toDateString(), $addon->end_date->toDateString());
    }

    public static function invalidInputs(): array
    {
        return [['addon_name', ' ', 'addon_name'], ['addon_name', str_repeat('a', 256), 'addon_name'],
            ['addon_duration_months', '', 'addon_duration_months'], ['addon_duration_days', -1, 'addon_duration_days'],
            ['addon_duration_days', 1.5, 'addon_duration_days'], ['addon_duration_days', 36501, 'addon_duration_days'],
            ['addon_duration_months', 1217, 'addon_duration_months']];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_input_does_not_create_addon(string $field, mixed $value, string $error): void
    {
        $membership = $this->membership();
        Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test('pages::dashboard.admin.riwayat.detail', ['user' => $membership->user])
            ->call('openAddon', $membership->id)->set('addon_name', 'Bonus')->set($field, $value)
            ->call('submitAddon')->assertHasErrors($error);
        $this->assertDatabaseCount('membership_addons', 0);
    }

    public function test_non_admin_and_unrelated_contract_are_rejected(): void
    {
        $membership = $this->membership();
        foreach (['kasir_gym', 'head_coach'] as $role) {
            Livewire::actingAs(User::factory()->create(['role' => $role]))
                ->test('pages::dashboard.admin.riwayat.detail', ['user' => $membership->user])
                ->call('openAddon', $membership->id)->assertForbidden();
            Livewire::test('pages::dashboard.admin.riwayat.detail', ['user' => $membership->user])
                ->call('submitAddon')->assertForbidden();
        }
        $other = $this->membership();
        $this->assertDatabaseCount('membership_addons', 0);
        $this->expectException(ModelNotFoundException::class);
        Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test('pages::dashboard.admin.riwayat.detail', ['user' => $membership->user])
            ->call('openAddon', $other->id);
    }

    public function test_ineligible_or_duplicate_addon_is_rejected_even_if_form_was_open(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        foreach ([['type' => 'membership'], ['status' => 'rejected']] as $attributes) {
            $membership = $this->membership();
            $form = Livewire::actingAs($admin)->test('pages::dashboard.admin.riwayat.detail', ['user' => $membership->user])
                ->call('openAddon', $membership->id)->set('addon_name', 'Bonus')->set('addon_duration_days', 1);
            $membership->update($attributes);
            $form->call('submitAddon')->assertHasErrors('addon');
        }
        $membership = $this->membership();
        $form = Livewire::test('pages::dashboard.admin.riwayat.detail', ['user' => $membership->user])
            ->call('openAddon', $membership->id)->set('addon_name', 'Bonus')->set('addon_duration_days', 1);
        MembershipAddon::factory()->create(['membership_id' => $membership->id, 'approval_status' => 'rejected', 'status' => 'rejected']);
        $form->call('submitAddon')->assertHasErrors('addon')->call('closeAddon')
            ->call('openAddon', $membership->id)->assertHasErrors('addon');
        $this->assertDatabaseCount('membership_addons', 1);
    }

    public function test_completed_unpaid_contract_cannot_activate_addon_until_paid(): void
    {
        $membership = $this->membership(['status' => 'completed', 'is_active' => false, 'payment_status' => 'partial']);
        $admin = User::factory()->create(['role' => 'admin']);
        Livewire::actingAs($admin)->test('pages::dashboard.admin.riwayat.detail', ['user' => $membership->user])
            ->call('openAddon', $membership->id)->set('addon_name', 'Bonus')->set('addon_duration_days', 2)
            ->call('submitAddon')->assertHasNoErrors();
        $addon = MembershipAddon::sole();
        app(MembershipAddonApproval::class)->approve($admin, $addon->id);
        $this->assertSame('pending', $addon->refresh()->status);
        $membership->update(['payment_status' => 'paid']);
        $this->assertSame('active', $addon->refresh()->status);
    }
}
