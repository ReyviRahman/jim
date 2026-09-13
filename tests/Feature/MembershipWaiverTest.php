<?php

namespace Tests\Feature;

use App\MembershipWaiverTerms;
use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\MembershipTransaction;
use App\Models\MembershipWaiver;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class MembershipWaiverTest extends TestCase
{
    use RefreshDatabase;

    public static function formCases(): array
    {
        return [
            'new single empty' => [false, 1, false],
            'new couple empty' => [false, 2, false],
            'new group mixed' => [false, 4, true],
            'renew single empty' => [true, 1, false],
            'renew couple empty' => [true, 2, false],
            'renew group mixed' => [true, 4, true],
        ];
    }

    #[DataProvider('formCases')]
    public function test_optional_waivers_save_for_each_member(bool $renew, int $count, bool $mixed): void
    {
        Storage::fake('local');
        [$form, $members, $oldMembership, $admin] = $this->form($renew, $count);
        $html = $form->html();
        $this->assertSame($count, substr_count($html, '<canvas '));
        $form->assertSee('Persetujuan & Waiver')->assertSee(MembershipWaiverTerms::CONSENT_LABEL);
        $this->assertSame([], $form->get('waivers'));

        if ($mixed) {
            $form->set('waivers.'.$members[0]->id.'.accepted', true)
                ->set('waivers.'.$members[1]->id.'.signature', $this->signature())
                ->set('waivers.'.$members[2]->id.'.accepted', true)
                ->set('waivers.'.$members[2]->id.'.signature', $this->signature());
        }

        $form->call('save')->assertHasNoErrors()->assertRedirect();
        $membership = Membership::latest('id')->firstOrFail();
        $waivers = $membership->waivers()->orderBy('user_id')->get();
        $this->assertCount($count, $waivers);
        foreach ($waivers as $index => $waiver) {
            $this->assertSame($members[$index]->id, $waiver->user_id);
            $this->assertSame($members[$index]->name, $waiver->member_name);
            $this->assertSame($admin->id, $waiver->admin_id);
            $this->assertSame($mixed && in_array($index, [0, 2], true), $waiver->accepted);
            $this->assertEquals(MembershipWaiverTerms::snapshot(), $waiver->terms_snapshot);
            if ($mixed && in_array($index, [1, 2], true)) {
                Storage::disk('local')->assertExists($waiver->signature_path);
                $this->assertSame(IMAGETYPE_PNG, getimagesizefromstring(Storage::disk('local')->get($waiver->signature_path))[2]);
            } else {
                $this->assertNull($waiver->signature_path);
            }
        }
        if ($renew) {
            $this->assertNotSame($oldMembership->id, $membership->id);
            $this->assertTrue($oldMembership->waivers()->firstOrFail()->accepted);
        }
    }

    public function test_unknown_member_and_invalid_signature_are_rejected_without_writes(): void
    {
        Storage::fake('local');
        [$form, $members] = $this->form(false, 1);
        $other = User::factory()->create();
        $form->set('waivers', [$other->id => ['accepted' => true]])
            ->call('save')->assertHasErrors('waivers');
        $form->set('waivers', [$members[0]->id => ['signature' => 'data:image/png;base64,invalid']])
            ->call('save')->assertHasErrors('waivers.'.$members[0]->id.'.signature');
        $form->set('waivers', [$members[0]->id => ['signature' => 'data:image/png;base64,'.base64_encode(str_repeat('x', 1048577))]])
            ->call('save')->assertHasErrors('waivers.'.$members[0]->id.'.signature');
        $this->assertDatabaseCount('memberships', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_validation_error_keeps_signature_and_can_be_corrected(): void
    {
        Storage::fake('local');
        [$form, $members] = $this->form(false, 1);
        $signature = $this->signature();
        $form->set('waivers.'.$members[0]->id.'.signature', $signature)->set('notes', '')
            ->call('save')->assertHasErrors('notes')
            ->assertSet('waivers.'.$members[0]->id.'.signature', $signature);
        $form->set('notes', 'Sudah diperbaiki')->call('save')->assertHasNoErrors()->assertRedirect();
    }

    public function test_database_failure_cleans_new_signatures_and_rolls_back_membership(): void
    {
        Storage::fake('local');
        [$form, $members] = $this->form(false, 2);
        $form->set('waivers.'.$members[0]->id.'.signature', $this->signature());
        MembershipWaiver::creating(function (MembershipWaiver $waiver) use ($members): void {
            if ($waiver->user_id === $members[1]->id) {
                throw new RuntimeException('Simulated failure');
            }
        });
        try {
            $form->call('save');
            $this->assertDatabaseCount('memberships', 0);
            $this->assertDatabaseCount('membership_waivers', 0);
            $this->assertSame([], Storage::disk('local')->allFiles());
        } finally {
            MembershipWaiver::flushEventListeners();
        }
    }

    public function test_later_payment_failure_also_cleans_saved_signatures(): void
    {
        Storage::fake('local');
        [$form, $members] = $this->form(false, 1);
        $form->set('waivers.'.$members[0]->id.'.signature', $this->signature());
        MembershipTransaction::creating(function (): void {
            throw new RuntimeException('Simulated payment failure');
        });
        try {
            $form->call('save');
            $this->assertDatabaseCount('memberships', 0);
            $this->assertDatabaseCount('membership_waivers', 0);
            $this->assertSame([], Storage::disk('local')->allFiles());
        } finally {
            MembershipTransaction::flushEventListeners();
        }
    }

    public function test_detail_allows_staff_and_head_coach_but_denies_member(): void
    {
        $waiver = MembershipWaiver::factory()->create(['member_name' => 'Nama persetujuan tersimpan']);
        foreach ([User::factory()->create(['role' => 'admin']), User::factory()->headCoach()->create()] as $staff) {
            Livewire::actingAs($staff)->test('pages::dashboard.admin.membership.index')
                ->call('openDetailModal', $waiver->membership_id)
                ->assertSee('Nama persetujuan tersimpan')->assertSee('Belum dicentang')->assertSee('Tidak diisi');
        }
        Livewire::actingAs(User::factory()->create(['role' => 'member']))->test('pages::dashboard.admin.membership.index')
            ->call('openDetailModal', $waiver->membership_id)->assertForbidden();
    }

    /** @return array{Testable, Collection<int, User>, Membership|null, User} */
    private function form(bool $renew, int $count): array
    {
        $shift = Shift::factory()->create(['role' => 'kasir_gym']);
        $admin = User::factory()->create(['role' => 'kasir_gym', 'shift' => $shift->id]);
        $this->actingAs($admin);
        $members = User::factory()->count($count)->create(['role' => 'member', 'photo' => 'profile-photos/existing.webp']);
        $package = GymPackage::create([
            'type' => 'gym', 'name' => 'Paket Waiver', 'category' => $count === 1 ? 'single' : ($count === 2 ? 'couple' : 'group'),
            'max_members' => $count, 'price' => 300000, 'discount' => 0, 'is_active' => true,
        ]);
        $old = null;
        if ($renew) {
            $old = Membership::create([
                'user_id' => $members[0]->id, 'type' => 'membership', 'gym_package_id' => $package->id,
                'base_price' => 300000, 'price_paid' => 300000, 'total_paid' => 300000, 'payment_status' => 'paid',
                'start_date' => today()->subMonth(), 'membership_end_date' => today(), 'status' => 'active', 'is_active' => true,
            ]);
            $old->members()->attach($members->modelKeys());
            $old->waivers()->create([
                'user_id' => $members[0]->id, 'member_name' => $members[0]->name, 'accepted' => true,
                'signature_path' => 'membership-waivers/old.png', 'recorded_at' => now(),
                'terms_snapshot' => MembershipWaiverTerms::snapshot(), 'consent_label' => MembershipWaiverTerms::CONSENT_LABEL,
            ]);
            $form = Livewire::test('pages::dashboard.admin.renew.create', ['id' => $old->id]);
        } else {
            $form = Livewire::withQueryParams(['users' => $members->modelKeys()])->test('pages::dashboard.admin.membership.paket');
        }
        $form->set('registration_type', 'membership')->set('gym_package_id', $package->id)
            ->set('admin_id', $admin->id)->set('follow_up_id', $admin->id)->set('follow_up_id_two', $admin->id)
            ->set('transaction_type', 'MEMBERSHIP')->set('package_name', 'Paket Waiver')->set('notes', 'Test waiver')
            ->set('payment_method', 'cash')->set('payment_type', 'paid');

        return [$form, $members, $old, $admin];
    }

    public function test_edit_loads_updates_and_clears_existing_signatures(): void
    {
        Storage::fake('local');
        [$create, $members] = $this->form(false, 2);
        $create->set('waivers.'.$members[0]->id.'.signature', $this->signature())->call('save')->assertHasNoErrors();
        $membership = Membership::latest('id')->firstOrFail();
        $waiver = $membership->waivers()->where('user_id', $members[0]->id)->firstOrFail();
        $oldPath = $waiver->signature_path;
        $edit = Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test('pages::dashboard.admin.membership.edit', ['id' => $membership->id]);
        $this->assertSame(2, substr_count($edit->html(), '<canvas '));
        $this->assertStringStartsWith('data:image/png;base64,', $edit->get('waivers')[$members[0]->id]['signature']);
        $edit->set('waivers.'.$members[0]->id.'.accepted', true)->call('save')->assertHasNoErrors();
        $this->assertTrue($waiver->fresh()->accepted);
        $this->assertSame($oldPath, $waiver->fresh()->signature_path);
        $edit->set('waivers.'.$members[0]->id.'.signature', $this->signature(60))->call('save')->assertHasNoErrors();
        $replacementPath = $waiver->fresh()->signature_path;
        $this->assertNotSame($oldPath, $replacementPath);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($replacementPath);
        MembershipWaiver::updating(function (): void {
            throw new RuntimeException('Simulated edit failure');
        });
        try {
            $edit->set('waivers.'.$members[0]->id.'.signature', $this->signature(70))->call('save');
            $this->assertSame($replacementPath, $waiver->fresh()->signature_path);
            $this->assertSame([$replacementPath], Storage::disk('local')->allFiles());
        } finally {
            MembershipWaiver::flushEventListeners();
        }
        $edit->set('waivers.'.$members[0]->id.'.signature', null)->call('save')->assertHasNoErrors();
        $this->assertNull($waiver->fresh()->signature_path);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertMissing($replacementPath);
        $this->assertSame(2, $membership->waivers()->count());
    }

    public function test_edit_legacy_membership_rejects_unknown_members_and_invalid_images(): void
    {
        Storage::fake('local');
        [$create, $members] = $this->form(false, 1);
        $create->call('save')->assertHasNoErrors();
        $membership = Membership::latest('id')->firstOrFail();
        $membership->waivers()->delete();
        $edit = Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test('pages::dashboard.admin.membership.edit', ['id' => $membership->id])->assertSet('waivers', []);
        $edit->set('waivers', [999999 => ['accepted' => true]])->call('save')->assertHasErrors('waivers');
        $edit->set('waivers', [$members[0]->id => ['signature' => 'invalid']])->call('save')
            ->assertHasErrors('waivers.'.$members[0]->id.'.signature');
        $edit->set('waivers', [])->call('save')->assertHasNoErrors();
        $this->assertFalse($membership->waivers()->firstOrFail()->accepted);
        Livewire::actingAs($members[0])->test('pages::dashboard.admin.membership.edit', ['id' => $membership->id])->assertForbidden();
    }

    private function signature(int $endY = 50): string
    {
        $image = imagecreatetruecolor(400, 160);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imageline($image, 25, 100, 360, $endY, imagecolorallocate($image, 0, 0, 0));
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return 'data:image/png;base64,'.base64_encode($bytes);
    }
}
