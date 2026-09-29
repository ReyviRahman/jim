<?php

namespace Tests\Feature;

use App\Actions\MembershipOperationalApproval;
use App\MembershipDates;
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

class MembershipPackageDurationTest extends TestCase
{
    use RefreshDatabase;

    public static function durations(): array
    {
        return [
            [0, 0, 1, '2026-10-01', '2026-10-01'],
            [0, 1, 0, '2026-12-29', '2027-01-04'],
            [1, 0, 0, '2026-10-01', '2026-10-30'],
            [1, 0, 0, '2026-02-01', '2026-03-02'],
            [1, 0, 0, '2028-02-01', '2028-03-01'],
            [1, 2, 3, '2026-10-01', '2026-11-16'],
        ];
    }

    #[DataProvider('durations')]
    public function test_duration_uses_fixed_inclusive_days(int $months, int $weeks, int $days, string $start, string $end): void
    {
        $package = new GymPackage(['duration_months' => $months, 'duration_weeks' => $weeks, 'duration_days' => $days]);
        $this->assertSame($end, app(MembershipDates::class)->endDates($start, $package, null)['membership_end_date']);
    }

    public static function invalidDurations(): array
    {
        return [['', '', '', 'duration_months'], [0, 0, 0, 'duration_months'], [-1, 0, 1, 'duration_months'], [0, 1.5, 0, 'duration_weeks'], [0, 0, 'abc', 'duration_days']];
    }

    #[DataProvider('invalidDurations')]
    public function test_master_rejects_invalid_duration(mixed $months, mixed $weeks, mixed $days, string $error): void
    {
        $this->masterForm()->set('duration_months', $months)->set('duration_weeks', $weeks)
            ->set('duration_days', $days)->call('save')->assertHasErrors($error);
        $this->assertDatabaseCount('gym_packages', 0);
    }

    public function test_master_creates_and_edits_duration_and_displays_it(): void
    {
        $this->masterForm()->set('duration_weeks', 2)->call('save')->assertHasNoErrors();
        $package = GymPackage::sole();
        $this->assertSame(14, $package->durationInDays());
        $this->assertSame(0, $package->duration_months);
        Livewire::test('pages::dashboard.admin.package.edit', ['package' => $package])
            ->assertSet('duration_weeks', 2)->set('duration_months', 1)->set('duration_days', 3)
            ->call('update')->assertHasNoErrors();
        $this->assertSame(47, $package->refresh()->durationInDays());
        Livewire::test('pages::dashboard.admin.package.index')->assertSee('1 bulan 2 minggu 3 hari (47 hari)');
        Livewire::test('pages::dashboard.admin.package.edit', ['package' => $package])
            ->set('duration_months', '')->set('duration_weeks', '')->set('duration_days', '')
            ->call('update')->assertHasErrors('duration_months');
    }

    #[DataProvider('forms')]
    public function test_master_validation_is_visible_and_identifies_fields(bool $edit): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $form = $edit
            ? Livewire::test('pages::dashboard.admin.package.edit', ['package' => $this->package()])
            : Livewire::test('pages::dashboard.admin.package.create');
        $action = $edit ? 'update' : 'save';
        $form->set('name', '')->set('price', '')->call($action)
            ->assertHasErrors(['name', 'price'])
            ->assertSee('Nama Paket wajib diisi.')
            ->assertSee('Isi salah satu harga, lalu pilih Harga Dipakai.')
            ->assertSee('Belum tersimpan. Periksa isian berikut.')
            ->assertSee('Paket belum tersimpan.')
            ->assertSee('data-validation-target="name"', false)
            ->assertDispatched('membership-form-invalid');
        $form->set('name', 'Paket uji')->set('price', 300000)
            ->set('duration_months', 0)->set('duration_weeks', 0)->set('duration_days', 0)
            ->call($action)->assertHasErrors('duration_months')
            ->assertSee('Isi minimal salah satu durasi')
            ->assertDispatched('membership-form-invalid', fields: ['duration_months']);
        $form->set('duration_days', 1)->set('type', 'pt')->set('pt_sessions', '')
            ->call($action)->assertHasErrors('pt_sessions')
            ->assertSee('Jumlah sesi wajib diisi untuk paket Personal Trainer.')
            ->assertDispatched('membership-form-invalid', fields: ['pt_sessions']);
        $form->set('pt_sessions', 10)->set('discount', 400000)
            ->call($action)->assertHasErrors('discount')
            ->assertSee('Diskon tidak boleh lebih besar dari harga paket.')
            ->assertDispatched('membership-form-invalid', fields: ['discount']);
    }

    public static function forms(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('forms')]
    public function test_dates_follow_start_and_package_and_save_edited_end_date(bool $renew): void
    {
        [$form, $package] = $this->membershipForm($renew);
        $form->set('start_date', '2026-10-01')->assertSet('membership_end_date', '2026-10-30')
            ->set('start_date', '2026-02-01')->assertSet('membership_end_date', '2026-03-02')
            ->set('start_date', '2026-12-20')->assertSet('membership_end_date', '2027-01-18')
            ->assertSeeHtml('wire:model.live="membership_end_date"');
        $weekly = $this->package(['duration_months' => 0, 'duration_weeks' => 1]);
        $form->set('gym_package_id', $weekly->id)->assertSet('membership_end_date', '2026-12-26');
        $form->set('gym_package_id', $package->id)->set('membership_end_date', '2099-01-01')
            ->assertSet('membership_end_date', '2099-01-01');
        $form->set('membership_end_date', '2026-12-19')->call('save')->assertHasErrors('membership_end_date');
        $form->set('membership_end_date', 'bad-date')->call('save')->assertHasErrors('membership_end_date');
        $form->set('membership_end_date', '2099-01-01');
        $package->update(['duration_months' => 0, 'duration_days' => 2]);
        $form->call('save')->assertHasNoErrors()->assertRedirect();
        $membership = Membership::latest('id')->firstOrFail();
        $this->assertSame('2099-01-01', $membership->membership_end_date->toDateString());
        $this->assertSame($renew, $membership->is_renewal);
        $this->assertSame('2099-01-01', $membership->transactions()->sole()->end_date->toDateString());
    }

    #[DataProvider('forms')]
    public function test_bundle_dates_and_partial_payment(bool $renew): void
    {
        [$form, $gym] = $this->membershipForm($renew);
        $pt = $this->package(['type' => 'pt', 'duration_months' => 0, 'duration_weeks' => 2, 'pt_sessions' => 8]);
        $form->set('registration_type', 'bundle_pt_membership')->set('gym_package_id', $gym->id)
            ->set('pt_package_id', $pt->id)->set('start_date', '2026-10-01')
            ->assertSet('membership_end_date', '2026-10-30')->assertSet('pt_end_date', '2026-10-14')
            ->assertSeeHtml('id="membership_end_date"')->assertSeeHtml('id="pt_end_date"')
            ->assertSee('Gym: 1 bulan / PT: 2 minggu')
            ->set('payment_type', 'partial')->set('start_date', '2026-11-01')
            ->set('gym_package_id', $gym->id)->assertSet('start_date', null)
            ->assertSet('membership_end_date', null)->assertSet('pt_end_date', null)
            ->set('amount_paid', 10000)->call('save')->assertHasNoErrors();
        $membership = Membership::latest('id')->firstOrFail();
        $this->assertSame($gym->name, $membership->gym_package_name_snapshot);
        $this->assertSame($pt->name, $membership->pt_package_name_snapshot);
        $this->assertNull($membership->start_date);
        $this->assertNull($membership->membership_end_date);
        $this->assertNull($membership->pt_end_date);
        $this->assertSame($renew, $membership->is_renewal);
    }

    public function test_checkbox_only_marks_renewal_and_split_payment_keeps_it(): void
    {
        [$form] = $this->membershipForm(false);
        $form->set('start_date', '2026-10-01')->set('is_renewal', true)
            ->assertSet('start_date', '2026-10-01')->assertSet('membership_end_date', '2026-10-30')
            ->set('is_split_payment', true)->set('split_cash', 300000)->call('save')->assertHasNoErrors();
        $membership = Membership::sole();
        $this->assertSame('Paket durasi', $membership->gym_package_name_snapshot);
        $this->assertTrue($membership->is_renewal);
        $this->assertSame('2026-10-30', $membership->transactions()->sole()->end_date->toDateString());
    }

    #[DataProvider('forms')]
    public function test_visit_uses_master_duration(bool $renew): void
    {
        [$form] = $this->membershipForm($renew);
        $visit = $this->package(['type' => 'visit', 'duration_months' => 0, 'duration_days' => 3]);
        $form->set('registration_type', 'visit')->set('gym_package_id', $visit->id)
            ->set('start_date', '2026-10-01')->assertSet('membership_end_date', '2026-10-03')
            ->assertSee('3 hari')->call('save')->assertHasNoErrors();
        $this->assertSame('2026-10-03', Membership::latest('id')->firstOrFail()->membership_end_date->toDateString());
    }

    #[DataProvider('forms')]
    public function test_names_are_snapshotted_from_selected_master(bool $renew): void
    {
        foreach (['membership', 'visit', 'pt', 'bundle_pt_membership'] as $type) {
            [$form, $gym] = $this->membershipForm($renew);
            $gym->update(['name' => 'Gym asli', 'type' => $type === 'visit' ? 'visit' : 'gym']);
            $pt = $this->package(['type' => 'pt', 'name' => 'PT asli', 'pt_sessions' => 8]);
            $form->set('registration_type', $type)->set('gym_package_id', $gym->id)
                ->set('has_addon', 'no')
                ->set('pt_package_id', $pt->id)->set('start_date', '2026-10-01')
                ->set('package_name', 'Catatan manual')->call('save')->assertHasNoErrors();
            $membership = Membership::latest('id')->firstOrFail();
            $gym->update(['name' => 'Gym baru']);
            $pt->update(['name' => 'PT baru']);
            $membership->update(['is_active' => true, 'payment_status' => 'paid']);
            $this->assertSame($type === 'pt' ? null : 'Gym asli', $membership->fresh()->gym_package_name_snapshot);
            $this->assertSame(in_array($type, ['pt', 'bundle_pt_membership']) ? 'PT asli' : null, $membership->pt_package_name_snapshot);
            $this->assertSame('Catatan manual', $membership->package_name);
        }
    }

    public function test_name_backfill_is_idempotent_and_preserves_timestamps(): void
    {
        [$form, $gym] = $this->membershipForm(false);
        $form->call('save')->assertHasNoErrors();
        $membership = Membership::sole();
        $membership->update(['gym_package_name_snapshot' => '']);
        $before = $membership->updated_at->toDateTimeString();
        $missing = $membership->replicate();
        $missing->gym_package_id = null;
        $missing->gym_package_name_snapshot = null;
        $missing->save();
        $migration = require database_path('migrations/2026_09_29_095322_backfill_membership_package_name_snapshots.php');
        $migration->up();
        $gym->update(['name' => 'Changed']);
        $migration->up();
        $this->assertSame('Paket durasi', $membership->fresh()->gym_package_name_snapshot);
        $this->assertSame($before, $membership->fresh()->updated_at->toDateTimeString());
        $this->assertNull($missing->fresh()->gym_package_name_snapshot);
    }

    public static function renewalDates(): array
    {
        return [
            ['membership', '2026-10-05', null, '2026-10-05'],
            ['membership', '2026-09-27', null, '2026-09-27'],
            ['membership', '2026-09-26', null, '2026-09-30'],
            ['membership', null, null, '2026-09-30'],
            ['pt', null, '2026-09-28', '2026-09-28'],
            ['bundle_pt_membership', '2026-09-20', '2026-10-03', '2026-10-03'],
            ['bundle_pt_membership', '2026-10-04', '2026-09-20', '2026-10-04'],
        ];
    }

    #[DataProvider('renewalDates')]
    public function test_renewal_start_obeys_three_day_boundary(string $type, ?string $gymEnd, ?string $ptEnd, string $expected): void
    {
        $this->travelTo(now()->setDate(2026, 9, 30)->setTime(23, 59));
        $membership = new Membership(['type' => $type, 'membership_end_date' => $gymEnd, 'pt_end_date' => $ptEnd]);
        $this->assertSame($expected, app(MembershipDates::class)->renewalStartDate($membership));
    }

    public function test_renewal_initializes_from_old_end_and_preserves_pt_extension(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 30));
        [$form, $package, $member, $actor, $old] = $this->membershipForm(true);
        $form->assertSet('start_date', '2026-09-27')->assertSet('membership_end_date', '2026-10-26');
        $pt = $this->package(['type' => 'pt', 'pt_sessions' => 8]);
        $old->update(['type' => 'pt', 'gym_package_id' => null, 'pt_package_id' => $pt->id, 'pt_end_date' => '2026-09-27']);
        $form = Livewire::test('pages::dashboard.admin.renew.create', ['id' => $old->id]);
        $this->completeForm($form, $member, $actor)->set('has_addon', 'no')->call('save')->assertHasNoErrors();
        $this->assertSame('2026-10-26', $old->refresh()->pt_end_date->toDateString());
        $this->assertTrue(Membership::latest('id')->firstOrFail()->is_renewal);
    }

    public function test_operational_snapshot_keeps_calculated_dates_and_renewal_on_approval(): void
    {
        [$form, $package] = $this->membershipForm(false);
        $form->set('payment_method', 'operasional')->set('operational_reason', 'Paket internal')
            ->set('start_date', '2026-10-01')->set('is_renewal', true)->call('save')->assertHasNoErrors();
        $request = MembershipOperationalRequest::sole();
        $this->assertSame('2026-10-30', $request->snapshot['membership']['membership_end_date']);
        $this->assertTrue($request->snapshot['membership']['is_renewal']);
        $package->update(['duration_days' => 10, 'name' => 'Renamed master']);
        app(MembershipOperationalApproval::class)->approve(User::factory()->create(['role' => 'admin']), $request->id);
        $membership = $request->membership()->sole();
        $this->assertSame('Paket durasi', $membership->gym_package_name_snapshot);
        $this->assertTrue($membership->is_renewal);
        $this->assertSame('2026-10-30', $membership->membership_end_date->toDateString());
    }

    public function test_backfill_defaults_preserve_configured_duration(): void
    {
        $gym = $this->package(['duration_months' => 0]);
        $pt = $this->package(['type' => 'pt', 'duration_months' => 0]);
        $visit = $this->package(['type' => 'visit', 'duration_months' => 0]);
        $configured = $this->package(['duration_weeks' => 1]);
        $migration = require database_path('migrations/2026_09_29_083508_backfill_gym_package_durations.php');
        $migration->up();
        $migration->up();
        $this->assertSame(30, $gym->refresh()->durationInDays());
        $this->assertSame(30, $pt->refresh()->durationInDays());
        $this->assertSame(1, $visit->refresh()->durationInDays());
        $this->assertSame(37, $configured->refresh()->durationInDays());
    }

    #[DataProvider('forms')]
    public function test_empty_start_date_clears_end_date_and_blocks_active_save(bool $renew): void
    {
        [$form] = $this->membershipForm($renew);
        $form->set('start_date', '')->assertSet('membership_end_date', null)
            ->call('save')->assertHasErrors(['start_date' => 'required']);
        $this->assertDatabaseCount('memberships', $renew ? 1 : 0);
    }

    public function test_inactive_operational_form_does_not_restore_dates(): void
    {
        [$form, $package] = $this->membershipForm(false);
        $form->set('payment_method', 'operasional')->set('operational_reason', 'Belum aktif')
            ->set('is_active', false)->set('start_date', '2026-10-01')
            ->set('gym_package_id', $package->id)->assertSet('start_date', null)
            ->assertSet('membership_end_date', null)->call('save')->assertHasNoErrors();
        $snapshot = MembershipOperationalRequest::sole()->snapshot['membership'];
        $this->assertNull($snapshot['start_date']);
        $this->assertNull($snapshot['membership_end_date']);
    }

    public function test_renewal_choice_is_required_and_accepts_explicit_no(): void
    {
        [$form] = $this->membershipForm(false);
        $form->set('is_renewal', null)->call('save')->assertHasErrors(['is_renewal' => 'required'])
            ->assertSee('Pilih Renewal atau Tidak Renewal pada Status Renewal.')
            ->assertSee('Belum tersimpan. Periksa isian berikut.')
            ->assertDispatched('membership-form-invalid', fields: ['is_renewal'])
            ->set('is_renewal', 'invalid')->call('save')->assertHasErrors(['is_renewal' => 'boolean'])
            ->set('is_renewal', '0')->call('save')->assertHasNoErrors();
        $this->assertFalse(Membership::sole()->is_renewal);
    }

    public function test_renewal_choice_starts_empty_and_is_required_for_operational_payment(): void
    {
        [$form, $package, $member] = $this->membershipForm(false);
        Livewire::withQueryParams(['users' => [$member->id]])->test('pages::dashboard.admin.membership.paket')
            ->assertSet('is_renewal', null)->assertSee('Tidak Renewal');
        $form->set('payment_method', 'operasional')->set('operational_reason', 'Internal')
            ->set('is_renewal', null)->call('save')->assertHasErrors(['is_renewal' => 'required']);
        $this->assertDatabaseCount('membership_operational_requests', 0);
    }

    #[DataProvider('forms')]
    public function test_required_fields_have_readable_messages_and_focus_targets(bool $renew): void
    {
        [$form] = $this->membershipForm($renew);
        $form->set('notes', '')->set('package_name', '')->set('transaction_type', '')
            ->set('follow_up_id', null)->set('follow_up_id_two', null)
            ->call('save')->assertHasErrors(['package_name', 'transaction_type'])
            ->assertSee('Paket Member wajib diisi.')
            ->assertSee('Status Transaksi wajib diisi.')
            ->assertSee('Belum tersimpan. Periksa isian berikut.')
            ->assertSee('data-validation-target="package_name"', false)
            ->assertDispatched('membership-form-invalid');
    }

    private function masterForm(): Testable
    {
        return Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test('pages::dashboard.admin.package.create')->set('name', 'Paket durasi')->set('price', 300000);
    }

    #[DataProvider('forms')]
    public function test_program_duration_displays_months_then_weeks_then_days(bool $renew): void
    {
        [$form, $package] = $this->membershipForm($renew);
        foreach ([240 => '8 bulan', 45 => '1 bulan 2 minggu 1 hari', 30 => '1 bulan', 29 => '4 minggu 1 hari', 7 => '1 minggu', 1 => '1 hari'] as $days => $label) {
            $package->update(['duration_months' => 0, 'duration_days' => $days]);
            $form->set('start_date', '2026-10-01')->assertSet('programDuration', $label);
        }
    }

    /** @param array<string, mixed> $attributes */
    private function package(array $attributes = []): GymPackage
    {
        return GymPackage::create(array_replace([
            'name' => 'Paket durasi', 'type' => 'gym', 'category' => 'single', 'max_members' => 1,
            'price' => 300000, 'discount' => 0, 'is_active' => true, 'duration_months' => 1,
        ], $attributes));
    }

    /** @return array{Testable, GymPackage, User, User, ?Membership} */
    private function membershipForm(bool $renew): array
    {
        Storage::fake('local');
        $actor = User::factory()->create(['role' => 'kasir_gym', 'shift' => Shift::factory()->create(['role' => 'kasir_gym'])->id]);
        $this->actingAs($actor);
        $member = User::factory()->create(['role' => 'member', 'photo' => 'existing.webp']);
        $package = $this->package();
        $old = null;
        if ($renew) {
            $old = Membership::create([
                'user_id' => $member->id, 'type' => 'membership', 'gym_package_id' => $package->id,
                'base_price' => 300000, 'price_paid' => 300000, 'total_paid' => 300000,
                'payment_status' => 'paid', 'is_active' => true, 'status' => 'active',
                'start_date' => '2026-08-29', 'membership_end_date' => '2026-09-27',
            ]);
            $old->members()->attach($member->id);
            $form = Livewire::test('pages::dashboard.admin.renew.create', ['id' => $old->id]);
        } else {
            $form = Livewire::withQueryParams(['users' => [$member->id]])->test('pages::dashboard.admin.membership.paket')->set('is_renewal', '0')
                ->set('registration_type', 'membership')->set('gym_package_id', $package->id);
        }

        return [$this->completeForm($form, $member, $actor), $package, $member, $actor, $old];
    }

    private function completeForm(Testable $form, User $member, User $actor): Testable
    {
        $image = imagecreatetruecolor(400, 160);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imageline($image, 25, 100, 360, 50, imagecolorallocate($image, 0, 0, 0));
        ob_start();
        imagepng($image);
        $signature = 'data:image/png;base64,'.base64_encode(ob_get_clean());
        imagedestroy($image);

        return $form->set('admin_id', $actor->id)->set('follow_up_id', $actor->id)->set('follow_up_id_two', $actor->id)
            ->set('transaction_type', 'MEMBERSHIP')->set('package_name', 'Paket durasi')->set('notes', 'Test durasi')
            ->set('pt_trial_interest', 'no')->set('waivers.'.$member->id.'.accepted', true)
            ->set('waivers.'.$member->id.'.signature', $signature);
    }
}
