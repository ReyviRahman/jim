<?php

namespace Tests\Feature;

use App\Actions\CheckInMembershipAddon;
use App\Actions\MembershipAddonApproval;
use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\MembershipAddon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MembershipAddonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 9, 29)->setTime(12, 0));
        Storage::fake('local');
    }

    public static function forms(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('forms')]
    public function test_purchase_and_renewal_require_choice_and_save_pending_addon(bool $renew): void
    {
        [$form, $member] = $this->form($renew);
        $form->assertSee('Mendapatkan add-on?')->assertSet('has_addon', '')
            ->call('save')->assertHasErrors('has_addon')
            ->set('has_addon', 'yes')->assertSee('Membership 1 Monthly Pass')
            ->set('addon_name', 'Gratis Gym Khusus')->set('addon_duration_months', 1)
            ->set('addon_duration_weeks', 2)->set('addon_duration_days', 3)
            ->call('save')->assertHasNoErrors();
        $addon = MembershipAddon::sole();
        $this->assertSame('pending', $addon->approval_status);
        $this->assertSame('pending', $addon->status);
        $this->assertSame(47, $addon->durationInDays());
        $this->assertSame('2026-11-14', $addon->end_date->toDateString());
        $this->assertSame($member->id, $addon->membership->user_id);
        $this->assertDatabaseCount('membership_transactions', 1);
        $this->assertFalse(MembershipAddon::usable()->exists());
    }

    #[DataProvider('forms')]
    public function test_no_addon_and_type_changes_clear_fields(bool $renew): void
    {
        [$form] = $this->form($renew);
        $ptPackageId = $form->get('pt_package_id');
        $form->set('has_addon', 'yes')->set('addon_name', 'Discard me')->set('addon_duration_months', 1)
            ->set('has_addon', 'no')->assertSet('addon_name', '')->assertSet('addon_duration_months', '')
            ->set('has_addon', 'yes')->set('addon_name', 'Discard again')->set('registration_type', 'membership')
            ->assertSet('has_addon', '')->assertSet('addon_name', '')->set('registration_type', 'pt')
            ->set('pt_package_id', $ptPackageId)->set('start_date', '2026-09-29')
            ->set('has_addon', 'no')->set('addon_name', 'Forged')->set('addon_duration_days', 10)
            ->call('save')->assertHasNoErrors();
        $this->assertDatabaseCount('membership_addons', 0);
    }

    public static function invalidInputs(): array
    {
        return [
            ['addon_name', '', 'addon_name'], ['addon_name', '   ', 'addon_name'],
            ['addon_name', str_repeat('x', 256), 'addon_name'],
            ['addon_duration_months', 0, 'addon_duration_months'],
            ['addon_duration_weeks', -1, 'addon_duration_weeks'],
            ['addon_duration_days', 1.5, 'addon_duration_days'],
            ['addon_duration_days', 'abc', 'addon_duration_days'],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_addon_is_rejected_before_saving(string $field, mixed $value, string $error): void
    {
        foreach ([false, true] as $renew) {
            [$form] = $this->form($renew);
            $form->set('has_addon', 'yes')->set('addon_name', 'Gym gratis')->set($field, $value)
                ->call('save')->assertHasErrors($error);
        }
        $this->assertDatabaseCount('membership_addons', 0);
        $this->assertDatabaseCount('membership_transactions', 0);
    }

    public function test_approval_is_separate_even_for_admin_and_dates_do_not_shift(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $addon = $this->addon(['start_date' => today()->subDays(5)], $admin);
        $this->assertSame('pending', $addon->approval_status);
        $start = $addon->start_date->toDateString();
        $action = app(MembershipAddonApproval::class);
        $action->approve($admin, $addon->id);
        $decidedAt = $addon->refresh()->decided_at->toDateTimeString();
        $this->travel(1)->hours();
        $action->approve($admin, $addon->id);
        $this->assertSame('active', $addon->refresh()->status);
        $this->assertSame($start, $addon->start_date->toDateString());
        $this->assertSame($decidedAt, $addon->decided_at->toDateTimeString());
        $this->assertDatabaseCount('membership_addons', 1);
        $this->assertDatabaseCount('membership_transactions', 0);
    }

    public static function activationOrders(): array
    {
        return [[['approve', 'pay', 'activate']], [['pay', 'activate', 'approve']], [['activate', 'approve', 'pay']]];
    }

    #[DataProvider('activationOrders')]
    public function test_all_requirements_are_needed_in_any_order(array $order): void
    {
        $addon = $this->addon(['payment_status' => 'partial', 'status' => 'pending', 'is_active' => false, 'start_date' => null]);
        $admin = User::factory()->create(['role' => 'admin']);
        foreach ($order as $index => $step) {
            match ($step) {
                'approve' => app(MembershipAddonApproval::class)->approve($admin, $addon->id),
                'pay' => $addon->membership->update(['payment_status' => 'paid', 'status' => 'active']),
                'activate' => $addon->membership->update(['is_active' => true, 'start_date' => today()]),
            };
            $this->assertSame($index === 2 ? 'active' : 'pending', $addon->refresh()->status);
            $this->assertSame($index === 2, MembershipAddon::usable()->whereKey($addon->id)->exists());
        }
    }

    public function test_addon_survives_pt_completion_hold_and_repeated_synchronization(): void
    {
        $addon = $this->approvedAddon();
        $end = $addon->end_date->toDateString();
        $addon->membership->update(['pt_end_date' => today()->addMonths(3), 'remaining_sessions' => 0, 'status' => 'completed', 'is_active' => false]);
        app(MembershipAddonApproval::class)->synchronize($addon->membership);
        $this->assertSame('active', $addon->refresh()->status);
        $this->assertSame($end, $addon->end_date->toDateString());
        $this->assertTrue(MembershipAddon::usable()->whereKey($addon->id)->exists());
    }

    public function test_late_approval_can_complete_addon_even_after_pt_completed(): void
    {
        $addon = $this->addon(['start_date' => today()->subDays(40)]);
        $addon->membership->update(['status' => 'completed', 'is_active' => false]);
        app(MembershipAddonApproval::class)->approve(User::factory()->create(['role' => 'admin']), $addon->id);
        $this->assertSame('completed', $addon->refresh()->status);
        $this->assertSame('approved', $addon->approval_status);
        $this->assertFalse(MembershipAddon::usable()->exists());
    }

    public function test_approval_page_permissions_scoping_and_rejection(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir_gym']);
        $own = $this->addon([], $cashier);
        $other = $this->addon();
        $other->update(['name' => 'Other cashier addon']);
        Livewire::actingAs($cashier)->test('dashboard.membership-addon-approvals')
            ->assertSee($own->name)->assertDontSee('Other cashier addon')
            ->call('approve', $own->id)->assertForbidden();
        Livewire::actingAs($cashier)->test('dashboard.membership-addon-approvals')
            ->call('reject', $own->id)->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin']);
        Livewire::actingAs($admin)->test('dashboard.membership-operational-approvals')->assertSee('Pengajuan Add-on');
        Livewire::test('dashboard.membership-addon-approvals')->call('reject', $own->id)
            ->assertHasErrors('rejection_reason')->set('rejectionReasons.'.$own->id, 'Tidak memenuhi syarat')
            ->call('reject', $own->id)->assertHasNoErrors()->set('status', 'rejected')
            ->assertSee('Tidak memenuhi syarat')->call('approve', $own->id)->assertHasErrors('addon_approval');
        $this->assertSame('rejected', $own->refresh()->approval_status);
        $this->assertSame('active', $own->membership->status);
        $this->assertSame($admin->id, $own->decided_by);
    }

    public function test_rejected_parent_blocks_unactivated_addon_and_delete_cascades(): void
    {
        $addon = $this->addon(['is_active' => false, 'start_date' => null]);
        $addon->membership->update(['status' => 'rejected']);
        $this->assertSame('rejected', $addon->refresh()->status);
        Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test('dashboard.membership-addon-approvals')
            ->call('approve', $addon->id)->assertHasErrors('addon_approval');
        $addon->membership->delete();
        $this->assertDatabaseMissing('membership_addons', ['id' => $addon->id]);
    }

    public function test_shared_members_get_card_and_gym_checkin_after_pt_completed(): void
    {
        $addon = $this->approvedAddon();
        $shared = User::factory()->create(['role' => 'member']);
        $addon->membership->members()->attach($shared);
        $addon->membership->update(['status' => 'completed', 'is_active' => false, 'remaining_sessions' => 0]);
        foreach ([$addon->membership->user, $shared] as $member) {
            Livewire::actingAs($member)->test('pages::dashboard.member.home')
                ->assertSee('Add-on Gratis')->assertSee($addon->name);
            $component = Livewire::test('pages::dashboard.member.absensi')->call('selectAddon', $addon->id)->assertHasNoErrors();
            $this->assertNotNull($component->viewData('qrCode'));
            $this->assertNull($component->viewData('selectedBooking'));
        }
        $scanner = Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test('pages::dashboard.admin.absensi.index');
        $scanner->set('scannedCode', json_encode(['user_id' => $shared->id, 'membership_id' => $addon->membership_id, 'membership_addon_id' => $addon->id]))
            ->call('processScan')->assertHasNoErrors();
        $this->assertDatabaseHas('attendances', ['user_id' => $shared->id, 'membership_addon_id' => $addon->id, 'type' => 'gym']);
        $this->assertSame(0, $addon->membership->fresh()->remaining_sessions);
        $scanner->set('scannedCode', json_encode(['user_id' => $shared->id, 'membership_id' => $addon->membership_id, 'membership_addon_id' => $addon->id]))->call('processScan');
        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_pending_future_expired_and_unowned_addons_cannot_check_in(): void
    {
        $pending = $this->addon();
        $future = $this->approvedAddon(['start_date' => today()->addDay()]);
        $expired = $this->approvedAddon(['start_date' => today()->subDays(40)]);
        $unowned = $this->approvedAddon();
        $actor = User::factory()->create(['role' => 'admin']);
        foreach ([$pending, $future, $expired, $unowned] as $addon) {
            $member = $addon === $unowned ? User::factory()->create(['role' => 'member']) : $addon->membership->user;
            Livewire::actingAs($member)->test('pages::dashboard.member.home')->assertDontSee('Add-on Gratis');
            Livewire::test('pages::dashboard.member.absensi')->call('selectAddon', $addon->id)->assertForbidden();
            try {
                app(CheckInMembershipAddon::class)->execute($actor, $member, ['membership_id' => $addon->membership_id, 'membership_addon_id' => $addon->id]);
                $this->fail('Invalid add-on was accepted.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('attendances', 0);
            }
        }
    }

    public function test_qr_cannot_mix_addon_with_another_contract_or_booking(): void
    {
        $addon = $this->approvedAddon();
        $actor = User::factory()->create(['role' => 'admin']);
        foreach ([['membership_id' => $addon->membership_id + 1000], ['booking_id' => 1]] as $override) {
            try {
                app(CheckInMembershipAddon::class)->execute($actor, $addon->membership->user, array_replace([
                    'membership_id' => $addon->membership_id, 'membership_addon_id' => $addon->id,
                ], $override));
                $this->fail('Manipulated QR was accepted.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('attendances', 0);
            }
        }
    }

    public function test_expiration_is_inclusive_independent_and_dry_run_is_read_only(): void
    {
        $addon = $this->approvedAddon(['start_date' => today()->subDays(29)]);
        $addon->membership->update(['status' => 'completed', 'is_active' => false]);
        $this->artisan('memberships:check-expired')->assertSuccessful();
        $this->assertSame('active', $addon->refresh()->status);
        $this->travel(1)->days();
        $this->artisan('memberships:check-expired', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame('active', $addon->refresh()->status);
        $this->assertFalse(MembershipAddon::usable()->exists());
        $this->artisan('memberships:check-expired')->assertSuccessful();
        $this->artisan('memberships:check-expired')->assertSuccessful();
        $this->assertSame('completed', $addon->refresh()->status);
    }

    public function test_submission_rolls_back_with_parent_transaction(): void
    {
        try {
            DB::transaction(function (): void {
                $this->addon();
                throw new \RuntimeException('Simulated payment failure');
            });
        } catch (\RuntimeException) {
            $this->assertDatabaseCount('memberships', 0);
            $this->assertDatabaseCount('membership_addons', 0);
        }
    }

    public function test_installment_payment_and_pt_activation_use_the_same_addon(): void
    {
        [$form] = $this->form(false);
        $form->set('has_addon', 'yes')->set('addon_name', 'Gym setelah lunas')->set('addon_duration_days', 7)
            ->set('payment_type', 'partial')->set('amount_paid', 100000)->call('save')->assertHasNoErrors();
        $addon = MembershipAddon::sole();
        $this->assertNull($addon->start_date);
        app(MembershipAddonApproval::class)->approve(User::factory()->create(['role' => 'admin']), $addon->id);
        $this->assertSame('pending', $addon->refresh()->status);
        Livewire::test('pages::dashboard.admin.cicilan.pay', ['membership' => $addon->membership])
            ->set('amount_paid', 200000)->set('transaction_type', 'Pelunasan')->set('notes', 'Lunas')->call('save')->assertHasNoErrors();
        $this->assertSame('pending', $addon->refresh()->status);
        $coach = User::factory()->create(['role' => 'pt', 'is_active' => true]);
        Livewire::test('pages::dashboard.admin.pt-booking.index')->call('openModal', $addon->membership_id)
            ->set('selectedCoachId', $coach->id)->call('aktivatekan')->assertHasNoErrors();
        $this->assertSame('active', $addon->refresh()->status);
        $this->assertSame('2026-10-05', $addon->end_date->toDateString());
        $this->assertDatabaseCount('membership_addons', 1);
    }

    public function test_renewal_submits_new_addon_without_extending_the_old_one(): void
    {
        [$form] = $this->form(true);
        $old = Membership::sole();
        $oldAddon = app(MembershipAddonApproval::class)->submit($old, [
            'name' => 'Bonus lama', 'duration_months' => 1, 'duration_weeks' => 0, 'duration_days' => 0,
        ], auth()->id());
        app(MembershipAddonApproval::class)->approve(User::factory()->create(['role' => 'admin']), $oldAddon->id);
        $end = $oldAddon->refresh()->end_date->toDateString();
        $form->assertSet('has_addon', '')->set('has_addon', 'yes')->set('addon_name', 'Bonus renewal')
            ->set('addon_duration_weeks', 1)->call('save')->assertHasNoErrors();
        $this->assertSame($end, $oldAddon->refresh()->end_date->toDateString());
        $this->assertDatabaseCount('membership_addons', 2);
        $this->assertSame('pending', MembershipAddon::latest('id')->first()->approval_status);
    }

    private function addon(array $membershipOverrides = [], ?User $actor = null): MembershipAddon
    {
        $pending = MembershipAddon::factory()->create();
        $membership = $pending->membership;
        $pending->delete();
        if ($membershipOverrides !== []) {
            $membership->update($membershipOverrides);
        }

        return app(MembershipAddonApproval::class)->submit($membership, [
            'name' => 'Membership 1 Monthly Pass', 'duration_months' => 1, 'duration_weeks' => 0, 'duration_days' => 0,
        ], ($actor ?? User::factory()->create(['role' => 'kasir_gym']))->id);
    }

    private function approvedAddon(array $overrides = []): MembershipAddon
    {
        $addon = $this->addon($overrides);
        app(MembershipAddonApproval::class)->approve(User::factory()->create(['role' => 'admin']), $addon->id);

        return $addon->refresh();
    }

    /** @return array{Testable, User} */
    private function form(bool $renew): array
    {
        $actor = User::factory()->create(['role' => 'kasir_gym']);
        $member = User::factory()->create(['role' => 'member', 'photo' => 'existing.webp']);
        $package = GymPackage::create(['name' => 'PT Uji', 'type' => 'pt', 'category' => 'single', 'max_members' => 1,
            'price' => 300000, 'discount' => 0, 'is_active' => true, 'duration_months' => 1, 'pt_sessions' => 10]);
        $this->actingAs($actor);
        if ($renew) {
            $old = Membership::create(['user_id' => $member->id, 'type' => 'pt', 'pt_package_id' => $package->id,
                'base_price' => 300000, 'price_paid' => 300000, 'total_paid' => 300000, 'payment_status' => 'paid',
                'is_active' => true, 'status' => 'active', 'start_date' => today()->subDays(29), 'pt_end_date' => today()]);
            $old->members()->attach($member);
            $form = Livewire::test('pages::dashboard.admin.renew.create', ['id' => $old->id]);
        } else {
            $form = Livewire::withQueryParams(['users' => [$member->id]])->test('pages::dashboard.admin.membership.paket')->set('is_renewal', '0');
        }
        $image = imagecreatetruecolor(400, 160);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imageline($image, 25, 100, 360, 50, imagecolorallocate($image, 0, 0, 0));
        ob_start();
        imagepng($image);
        $signature = 'data:image/png;base64,'.base64_encode(ob_get_clean());
        imagedestroy($image);
        $form->set('registration_type', 'pt')->set('pt_package_id', $package->id)
            ->set('is_active', true)->set('start_date', '2026-09-29')->set('payment_type', 'paid')->set('payment_method', 'cash')
            ->set('admin_id', $actor->id)->set('follow_up_id', $actor->id)->set('follow_up_id_two', $actor->id)
            ->set('transaction_type', 'PT')->set('package_name', 'PT Uji')->set('notes', 'Uji add-on')
            ->set('pt_trial_interest', 'no')->set('waivers.'.$member->id.'.accepted', true)->set('waivers.'.$member->id.'.signature', $signature);

        return [$form, $member];
    }
}
