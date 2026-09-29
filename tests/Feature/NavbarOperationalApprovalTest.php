<?php

namespace Tests\Feature;

use App\Actions\BeverageOperationalApproval;
use App\Models\Beverage;
use App\Models\BeverageOperationalRequest;
use App\Models\MembershipAddon;
use App\Models\MembershipOperationalRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class NavbarOperationalApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_pending_request_count_and_direct_pos_link(): void
    {
        BeverageOperationalRequest::factory()->count(12)->create();
        BeverageOperationalRequest::factory()->create(['status' => 'approved']);
        BeverageOperationalRequest::factory()->create(['status' => 'rejected']);
        Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test('dashboard.navbar')
            ->assertSet('pendingOperationalCount', 12)
            ->assertSee('Approval, 12 pengajuan menunggu')
            ->assertSee('data-operational-approval-badge', false)
            ->assertSee('wire:poll.30s.visible', false)
            ->assertSee('wire:navigate', false)
            ->assertSee(route('admin.beverages.pos', ['approval' => 'pending', 'approvalPage' => 1]).'#operational-approvals');
    }

    public function test_zero_count_keeps_link_without_badge_and_polling_detects_new_requests(): void
    {
        $navbar = Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test('dashboard.navbar')
            ->assertSee('Approval, 0 pengajuan menunggu')
            ->assertDontSee('data-operational-approval-badge', false);
        $request = BeverageOperationalRequest::factory()->create();
        $navbar->call('refreshOperationalApprovals')->assertSet('pendingOperationalCount', 1);
        $request->update(['status' => 'approved']);
        $navbar->dispatch('operational-approvals-updated')->assertSet('pendingOperationalCount', 0)
            ->assertDontSee('data-operational-approval-badge', false);
    }

    public function test_combined_badge_separates_membership_and_beverage_counts(): void
    {
        BeverageOperationalRequest::factory()->count(2)->create();
        MembershipOperationalRequest::factory()->count(3)->create();
        MembershipOperationalRequest::factory()->create(['status' => 'approved']);
        $rejected = MembershipOperationalRequest::factory()->create(['status' => 'rejected']);
        $navbar = Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test('dashboard.navbar')
            ->assertSet('pendingOperationalCount', 5)
            ->assertSet('pendingBeverageCount', 2)
            ->assertSet('pendingMembershipCount', 3)
            ->assertSee('Operasional Minuman')->assertSee('Operasional Membership')
            ->assertSee(route('admin.riwayat.index', ['approval' => 'pending', 'membershipApprovalPage' => 1]).'#membership-operational-approvals');
        $rejected->update(['status' => 'pending']);
        $navbar->dispatch('operational-approvals-updated')
            ->assertSet('pendingMembershipCount', 4)->assertSet('pendingOperationalCount', 6);
    }

    public function test_other_roles_cannot_see_or_read_count(): void
    {
        BeverageOperationalRequest::factory()->count(2)->create();
        foreach (['kasir_minum', 'kasir_gym', 'member', 'pt', 'head_coach'] as $role) {
            $navbar = Livewire::actingAs(User::factory()->create(['role' => $role]))->test('dashboard.navbar')
                ->assertDontSee('Approval Add-on')
                ->assertDontSee('wire:poll.30s.visible', false)
                ->dispatch('operational-approvals-updated')->assertOk();
            foreach (['pendingOperationalCount', 'pendingBeverageCount', 'pendingMembershipCount', 'pendingAddonCount'] as $property) {
                try {
                    $navbar->instance()->{$property};
                    $this->fail('Count must be restricted to admins.');
                } catch (HttpException $exception) {
                    $this->assertSame(403, $exception->getStatusCode());
                }
            }
        }
    }

    public function test_badge_destination_resets_filter_and_pagination(): void
    {
        Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->withQueryParams(['approval' => 'pending', 'approvalPage' => 4])
            ->test('pages::dashboard.admin.beverages.pos')
            ->assertSet('approvalStatus', 'pending')
            ->assertSet('paginators.approvalPage', 1)
            ->assertSee('id="operational-approvals"', false);
    }

    public function test_addons_contribute_to_badge_and_refresh_after_decisions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        BeverageOperationalRequest::factory()->create();
        MembershipOperationalRequest::factory()->create();
        MembershipAddon::factory()->create(['approval_status' => 'approved']);
        MembershipAddon::factory()->create(['approval_status' => 'rejected']);
        $navbar = Livewire::actingAs($admin)->test('dashboard.navbar')->assertSet('pendingAddonCount', 0);
        $first = MembershipAddon::factory()->create();
        $second = MembershipAddon::factory()->create();
        $navbar->call('refreshOperationalApprovals')->assertSet('pendingAddonCount', 2)
            ->assertSet('pendingOperationalCount', 4)->assertSee('Approval, 4 pengajuan menunggu')
            ->assertSee(route('admin.riwayat.index', ['addonApproval' => 'pending', 'addonApprovalPage' => 1]).'#membership-addon-approvals');
        Livewire::test('dashboard.membership-addon-approvals')->call('approve', $first->id)
            ->assertHasNoErrors()->assertDispatched('addon-approvals-updated');
        $navbar->dispatch('addon-approvals-updated')->assertSet('pendingAddonCount', 1)->assertSet('pendingOperationalCount', 3);
        Livewire::test('dashboard.membership-addon-approvals')->set('rejectionReasons.'.$second->id, 'Tidak sesuai')
            ->call('reject', $second->id)->assertHasNoErrors()->assertDispatched('addon-approvals-updated');
        $navbar->dispatch('addon-approvals-updated')->assertSet('pendingAddonCount', 0)->assertSet('pendingOperationalCount', 2);
    }

    public function test_addon_link_opens_pending_filter_and_first_page(): void
    {
        Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->withQueryParams(['addonApproval' => 'pending', 'addonApprovalPage' => 1])
            ->test('dashboard.membership-addon-approvals')->assertSet('status', 'pending')
            ->assertSet('paginators.addonApprovalPage', 1)
            ->assertSee('id="membership-addon-approvals"', false);
    }

    public function test_successful_decisions_dispatch_navbar_refresh(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        foreach (['approveOperational', 'rejectOperational', 'deleteOperational'] as $action) {
            $product = Beverage::factory()->create(['stok_sekarang' => 10]);
            $request = app(BeverageOperationalApproval::class)->submit(
                User::factory()->create(['role' => 'kasir_minum']),
                ['selected_products' => [['beverage_id' => $product->id, 'jumlah_beli' => 1]], 'reason' => 'Rapat', 'keterangan_bayar' => 'operasional'],
            );
            Livewire::actingAs($admin)->test('pages::dashboard.admin.beverages.pos')
                ->set('rejectionReasons.'.$request->id, 'Salah input')
                ->call($action, $request->id)->assertHasNoErrors()
                ->assertDispatched('operational-approvals-updated');
        }
    }
}
