<?php

namespace Tests\Feature;

use App\Actions\MembershipOperationalApproval;
use App\Models\GymPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MembershipOperationalApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_cashier_submission_has_no_membership_side_effect_and_duplicate_token_is_idempotent(): void
    {
        [$actor, $input, $waivers] = $this->fixture();
        $action = app(MembershipOperationalApproval::class);
        $request = $action->submit($actor, $input, $waivers, []);
        $duplicate = $action->submit($actor, $input, $waivers, []);
        $this->assertSame($request->id, $duplicate->id);
        $this->assertSame('pending', $request->status);
        $this->assertDatabaseCount('memberships', 0);
        $this->assertDatabaseCount('membership_transactions', 0);
        $this->assertNull($request->snapshot['membership']['follow_up_id']);
        $this->assertNull($request->snapshot['membership']['follow_up_id_two']);
        Storage::disk('local')->assertExists($request->documents[0]['signature_path']);
    }

    public function test_all_registration_types_preserve_shared_snapshot_and_ignore_client_prices(): void
    {
        foreach (['membership', 'pt', 'bundle_pt_membership', 'visit'] as $type) {
            [$actor, $input, $waivers] = $this->fixture($type, $type === 'visit' ? 1 : 2);
            $action = app(MembershipOperationalApproval::class);
            $request = $action->submit($actor, $input + ['price_paid' => 1, 'total_paid' => 1, 'follow_up_id' => $actor->id, 'follow_up_id_two' => $actor->id], $waivers, []);
            GymPackage::whereIn('id', array_filter([$input['gym_package_id'], $input['pt_package_id']]))->update(['price' => 999999]);
            $admin = User::factory()->create(['role' => 'admin']);
            $action->approve($admin, $request->id);
            $action->approve($admin, $request->id);
            $membership = $request->membership()->firstOrFail();
            $this->assertSame($type, $membership->type);
            $this->assertCount(count($input['user_ids']), $membership->members);
            $this->assertCount(count($input['user_ids']), $membership->waivers);
            $this->assertSame($type === 'bundle_pt_membership' ? 200000 : 100000, (int) $membership->price_paid);
            $this->assertNull($membership->follow_up_id);
            $this->assertNull($membership->follow_up_id_two);
            $this->assertSame($actor->id, $membership->admin_id);
            $this->assertSame(1, $membership->transactions()->count());
            $this->assertSame('operasional', $membership->transactions()->first()->payment_method);
        }
    }

    public function test_approval_page_scopes_requests_and_refreshes_navbar_after_decisions(): void
    {
        [$actor, $input, $waivers] = $this->fixture();
        $action = app(MembershipOperationalApproval::class);
        $request = $action->submit($actor, array_replace($input, ['reason' => 'Pengajuan milik kasir pertama']), $waivers, []);
        [$other, $otherInput, $otherWaivers] = $this->fixture();
        $otherRequest = $action->submit($other, array_replace($otherInput, ['reason' => 'Pengajuan milik kasir kedua']), $otherWaivers, []);

        Livewire::actingAs($actor)->test('dashboard.membership-operational-approvals')
            ->assertSee('Pengajuan milik kasir pertama')
            ->assertDontSee('Pengajuan milik kasir kedua');
        Livewire::actingAs($actor)->test('dashboard.membership-operational-approvals')
            ->call('approve', $request->id)->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        Livewire::actingAs($admin)->withQueryParams(['approval' => 'pending', 'membershipApprovalPage' => 2])
            ->test('dashboard.membership-operational-approvals')
            ->assertSet('status', 'pending')->assertSet('paginators.membershipApprovalPage', 1)
            ->assertSee('Pengajuan milik kasir pertama')->assertSee('Pengajuan milik kasir kedua')
            ->call('approve', $request->id)->assertHasNoErrors()
            ->assertDispatched('operational-approvals-updated')
            ->set('rejectionReasons.'.$otherRequest->id, 'Tidak sesuai kebutuhan')
            ->call('reject', $otherRequest->id)->assertHasNoErrors()
            ->assertDispatched('operational-approvals-updated')
            ->set('status', 'rejected')->assertSee('Tidak sesuai kebutuhan');

        $pending = $action->submit($actor, array_replace($input, ['submission_token' => (string) Str::uuid()]), $waivers, []);
        Livewire::actingAs($actor)->test('dashboard.membership-operational-approvals')
            ->call('deletePending', $pending->id)->assertHasNoErrors()
            ->assertDispatched('operational-approvals-updated');
        $this->assertDatabaseMissing('membership_operational_requests', ['id' => $pending->id]);
    }

    public function test_admin_submission_auto_approves_unactivated_membership(): void
    {
        [$actor, $input, $waivers] = $this->fixture();
        $actor->update(['role' => 'admin']);
        $input = array_replace($input, ['is_active' => false, 'start_date' => null, 'membership_end_date' => null, 'pt_end_date' => null]);
        $request = app(MembershipOperationalApproval::class)->submit($actor, $input, $waivers, []);
        $this->assertSame('approved', $request->status);
        $membership = $request->membership;
        $this->assertFalse($membership->is_active);
        $this->assertNull($membership->start_date);
        $this->assertSame('paid', $membership->payment_status);
    }

    public function test_historical_bundle_approval_expires_sessions_without_extending_dates(): void
    {
        [$actor, $input, $waivers] = $this->fixture('bundle_pt_membership');
        $input = array_replace($input, ['start_date' => '2025-01-01', 'pt_end_date' => '2025-02-01', 'membership_end_date' => '2025-03-01', 'payment_date' => '2025-01-01']);
        $action = app(MembershipOperationalApproval::class);
        $request = $action->submit($actor, $input, $waivers, []);
        $action->approve(User::factory()->create(['role' => 'admin']), $request->id);
        $membership = $request->membership;
        $this->assertSame('completed', $membership->status);
        $this->assertFalse($membership->is_active);
        $this->assertSame(0, $membership->remaining_sessions);
        $this->assertSame(8, $membership->sesi_hangus);
        $this->assertSame('2025-03-01', $membership->membership_end_date->toDateString());
        $this->assertSame('2025-01-01', $membership->transactions()->first()->payment_date->toDateString());
    }

    public function test_only_admin_decides_and_rejected_requests_cannot_be_approved(): void
    {
        [$actor, $input, $waivers] = $this->fixture();
        $action = app(MembershipOperationalApproval::class);
        $request = $action->submit($actor, $input, $waivers, []);
        try {
            $action->approve($actor, $request->id);
            $this->fail('Cashier cannot approve.');
        } catch (HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
        $admin = User::factory()->create(['role' => 'admin']);
        $action->reject($admin, $request->id, 'Tidak sesuai');
        $this->assertSame('rejected', $request->refresh()->status);
        try {
            $action->approve($admin, $request->id);
            $this->fail('Rejected request cannot approve.');
        } catch (ValidationException $error) {
            $this->assertNotEmpty($error->errors());
        }
        $this->assertDatabaseCount('memberships', 0);
    }

    public function test_partial_and_split_flags_are_rejected_before_creating_request(): void
    {
        foreach ([['payment_type' => 'partial'], ['is_split_payment' => true]] as $changes) {
            [$actor, $input, $waivers] = $this->fixture();
            try {
                app(MembershipOperationalApproval::class)->submit($actor, array_replace($input, $changes), $waivers, []);
                $this->fail('Invalid payment flags must fail.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey(array_key_first($changes), $error->errors());
            }
        }
        $this->assertDatabaseCount('membership_operational_requests', 0);
    }

    public function test_form_clears_followups_for_operational_and_cash_still_requires_them(): void
    {
        [$actor, $input, $waivers] = $this->fixture();
        $this->actingAs($actor);
        $form = Livewire::withQueryParams(['users' => $input['user_ids']])->test('pages::dashboard.admin.membership.paket')
            ->set('registration_type', 'membership')->set('gym_package_id', $input['gym_package_id'])->set('admin_id', $actor->id)
            ->set('follow_up_id', $actor->id)->set('follow_up_id_two', $actor->id)
            ->set('payment_method', 'operasional')->assertSet('follow_up_id', null)->assertSet('follow_up_id_two', null)
            ->set('transaction_type', 'Baru')->set('package_name', 'Gym')->set('notes', 'Internal')
            ->set('pt_trial_interest', 'no')->set('waivers', $waivers)->set('operational_reason', 'Internal');
        $form->assertDontSee('Lihat pengajuan Operasional')
            ->call('save')->assertHasNoErrors()->assertRedirect(route('admin.riwayat.index'));
        $this->assertDatabaseCount('membership_operational_requests', 1);
        $this->assertDatabaseCount('memberships', 0);
        $form = Livewire::withQueryParams(['users' => $input['user_ids']])->test('pages::dashboard.admin.membership.paket')
            ->set('registration_type', 'membership')->set('gym_package_id', $input['gym_package_id'])->set('admin_id', $actor->id)
            ->set('transaction_type', 'Baru')->set('package_name', 'Gym')->set('notes', 'Cash')
            ->set('pt_trial_interest', 'no')->set('waivers', $waivers)->set('payment_method', 'cash')
            ->set('follow_up_id', null)->set('follow_up_id_two', null)->call('save')->assertHasErrors(['follow_up_id', 'follow_up_id_two']);
    }

    public function test_history_embeds_approvals_and_refreshes_after_membership_is_created(): void
    {
        [$actor, $input, $waivers] = $this->fixture();
        $action = app(MembershipOperationalApproval::class);
        $request = $action->submit($actor, $input, $waivers, []);
        $admin = User::factory()->create(['role' => 'admin']);
        $history = Livewire::actingAs($admin)->test('pages::dashboard.admin.riwayat.index')
            ->assertSeeLivewire('dashboard.membership-operational-approvals');
        $this->assertSame(0, $history->get('users')->total());

        $action->approve($admin, $request->id);
        $history->dispatch('operational-approvals-updated')->assertOk();
        $this->assertSame(1, $history->get('users')->total());
        $this->get(route('admin.riwayat.index'))->assertOk()->assertSee('Approval Membership');
        $this->get('/dashboard/admin/membership/operational-approvals')->assertNotFound();
    }

    public function test_approval_pagination_does_not_use_the_history_page_parameter(): void
    {
        [$actor] = $this->fixture();
        Livewire::actingAs($actor)->withQueryParams(['page' => 4, 'membershipApprovalPage' => 2])
            ->test('dashboard.membership-operational-approvals')
            ->assertSet('paginators.membershipApprovalPage', 2)
            ->set('status', 'approved')
            ->assertSet('paginators.membershipApprovalPage', 1);
    }

    public function test_operational_membership_can_activate_without_becoming_cash_payment(): void
    {
        [$actor, $input, $waivers] = $this->fixture();
        $actor->update(['role' => 'admin']);
        $input = array_replace($input, ['is_active' => false, 'start_date' => null, 'membership_end_date' => null]);
        $request = app(MembershipOperationalApproval::class)->submit($actor, $input, $waivers, []);
        $membership = $request->membership;
        Livewire::actingAs($actor)->test('pages::dashboard.admin.membership.edit', ['id' => $membership->id])
            ->set('is_active', true)->set('start_date', today()->toDateString())
            ->set('membership_end_date', today()->addMonth()->toDateString())
            ->call('save')->assertHasNoErrors();
        $this->assertTrue($membership->refresh()->is_active);
        $this->assertNull($membership->follow_up_id);
        $this->assertSame('operasional', $membership->transactions()->first()->payment_method);
        Livewire::actingAs($actor)->test('pages::dashboard.admin.membership.edit', ['id' => $membership->id])
            ->set('payment_method', 'cash')->call('save')->assertHasErrors('payment_method');
        $this->assertSame('operasional', $membership->transactions()->first()->payment_method);
    }

    public function test_owner_can_delete_pending_request_and_its_staged_documents(): void
    {
        [$actor, $input, $waivers] = $this->fixture();
        $member = User::findOrFail($input['user_ids'][0]);
        $member->update(['photo' => null]);
        $action = app(MembershipOperationalApproval::class);
        $request = $action->submit($actor, $input, $waivers, [$member->id => UploadedFile::fake()->image('member.jpg')]);
        $document = $request->documents[0];
        $this->assertNull($member->refresh()->photo);
        Storage::disk('local')->assertExists($document['signature_path']);
        Storage::disk('public')->assertExists($document['photo_path']);
        try {
            $action->deletePending(User::factory()->create(['role' => 'kasir_gym']), $request->id);
            $this->fail('Another cashier cannot delete request.');
        } catch (HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
        $action->deletePending($actor, $request->id);
        $this->assertModelMissing($request);
        Storage::disk('local')->assertMissing($document['signature_path']);
        Storage::disk('public')->assertMissing($document['photo_path']);
        $this->assertDatabaseCount('memberships', 0);
    }

    private function fixture(string $type = 'membership', int $count = 1): array
    {
        $actor = User::factory()->create(['role' => 'kasir_gym']);
        $members = User::factory()->count($count)->create(['role' => 'member', 'photo' => 'existing.jpg']);
        $category = $count === 1 ? 'single' : 'couple';
        $gym = in_array($type, ['membership', 'bundle_pt_membership', 'visit'], true) ? GymPackage::create(['name' => 'Gym', 'type' => $type === 'visit' ? 'visit' : 'gym', 'category' => $category, 'price' => 100000, 'discount' => 0, 'duration_days' => 30, 'is_active' => true]) : null;
        $pt = in_array($type, ['pt', 'bundle_pt_membership'], true) ? GymPackage::create(['name' => 'PT', 'type' => 'pt', 'category' => $category, 'price' => 100000, 'discount' => 0, 'pt_sessions' => 8, 'duration_days' => 30, 'is_active' => true]) : null;
        $input = ['submission_token' => (string) Str::uuid(), 'user_ids' => $members->modelKeys(), 'registration_type' => $type, 'gym_package_id' => $gym?->id, 'pt_package_id' => $pt?->id, 'admin_id' => $actor->id, 'is_active' => true, 'start_date' => today()->toDateString(), 'membership_end_date' => $gym ? today()->addMonth()->toDateString() : null, 'pt_end_date' => $pt ? today()->addMonth()->toDateString() : null, 'payment_date' => today()->toDateString(), 'transaction_type' => 'Baru', 'package_name' => 'Paket', 'notes' => 'Internal', 'reason' => 'Internal', 'pt_trial_interest' => 'no', 'payment_type' => 'paid', 'is_split_payment' => false];
        $waivers = $members->mapWithKeys(fn (User $member): array => [$member->id => ['accepted' => true, 'signature' => $this->signature()]])->all();

        return [$actor, $input, $waivers];
    }

    private function signature(): string
    {
        $image = imagecreatetruecolor(400, 160);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imageline($image, 25, 100, 360, 50, imagecolorallocate($image, 0, 0, 0));
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return 'data:image/png;base64,'.base64_encode($bytes);
    }
}
