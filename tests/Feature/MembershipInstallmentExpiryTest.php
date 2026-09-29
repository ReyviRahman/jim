<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MembershipInstallmentExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_allowed_roles_can_hide_and_restore_without_changing_membership_or_payments(): void
    {
        foreach (['admin', 'kasir_gym', 'head_coach'] as $role) {
            $actor = $role === 'head_coach' ? User::factory()->headCoach()->create() : User::factory()->create(['role' => $role]);
            $membership = $this->membership();
            $original = $membership->refresh()->getAttributes();
            $page = Livewire::actingAs($actor)->test('pages::dashboard.admin.cicilan.index', ['ptOnly' => false]);
            $page->call('markExpired', $membership->id)->assertHasNoErrors()
                ->assertDontSee($membership->user->name);
            $membership->refresh();
            $this->assertNotNull($membership->membership_installment_expired_at);
            $this->assertSame($actor->id, $membership->membership_installment_expired_by);
            $markedAt = $membership->membership_installment_expired_at->toDateTimeString();
            $this->travel(1)->minute();
            $page->call('markExpired', $membership->id)->assertHasNoErrors();
            $this->assertSame($markedAt, $membership->refresh()->membership_installment_expired_at->toDateTimeString());
            foreach ($original as $field => $value) {
                if (! in_array($field, ['updated_at', 'membership_installment_expired_at', 'membership_installment_expired_by'], true)) {
                    $this->assertSame($value, $membership->getAttributes()[$field], $field);
                }
            }
            $page->set('installmentFilter', 'expired')->assertSee($membership->user->name)->assertSee('Pulihkan')
                ->call('restoreInstallment', $membership->id)->assertHasNoErrors()->assertDontSee($membership->user->name)
                ->call('restoreInstallment', $membership->id)->assertHasNoErrors()
                ->set('installmentFilter', 'active')->assertSee($membership->user->name);
            $this->assertNull($membership->refresh()->membership_installment_expired_at);
            $this->assertNull($membership->membership_installment_expired_by);
            $this->travelBack();
        }
        $this->assertDatabaseCount('membership_transactions', 0);
    }

    public function test_search_and_pagination_follow_selected_filter(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        for ($i = 0; $i < 11; $i++) {
            $this->membership();
        }
        $hidden = $this->membership(['membership_installment_expired_at' => now(), 'membership_installment_expired_by' => $admin->id]);
        $hidden->user->update(['name' => 'Member Yang Hangus']);
        $page = Livewire::actingAs($admin)->test('pages::dashboard.admin.cicilan.index', ['ptOnly' => false]);
        $this->assertSame(11, $page->get('memberships')->total());
        $page->call('setPage', 2)->assertSet('paginators.page', 2)
            ->set('installmentFilter', 'expired')->assertSet('paginators.page', 1)->assertSee('Member Yang Hangus')
            ->set('search', 'Member Yang Hangus')->assertSee('Member Yang Hangus')
            ->set('installmentFilter', 'active')->assertDontSee('Member Yang Hangus')
            ->assertSee('Tidak ada member yang cocok dengan pencarian.');
    }

    public function test_invalid_memberships_and_roles_cannot_change_marker(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        foreach ([['type' => 'pt'], ['payment_status' => 'paid']] as $attributes) {
            $membership = $this->membership($attributes);
            foreach (['markExpired', 'restoreInstallment'] as $method) {
                Livewire::actingAs($admin)->test('pages::dashboard.admin.cicilan.index', ['ptOnly' => false])
                    ->call($method, $membership->id)->assertHasErrors('installment');
            }
            $this->assertNull($membership->refresh()->membership_installment_expired_at);
        }
        $membership = $this->membership();
        foreach (['member', 'pt', 'kasir_minum'] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            foreach (['markExpired', 'restoreInstallment'] as $method) {
                Livewire::actingAs($actor)->test('pages::dashboard.admin.cicilan.index', ['ptOnly' => false])
                    ->call($method, $membership->id)->assertForbidden();
            }
        }

    }

    public function test_all_non_pt_types_support_expiry_and_dashboard_counts_follow_filter(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        foreach (['membership', 'bundle_pt_membership', 'visit'] as $type) {
            $membership = $this->membership(['type' => $type, 'status' => 'pending', 'is_active' => false]);
            $page = Livewire::actingAs($admin)->test('pages::dashboard.admin.cicilan.index');
            $this->assertSame(1, Livewire::test('pages::dashboard.admin.index')->get('chartData')['data'][2]);
            $page->call('markExpired', $membership->id)->assertHasNoErrors();
            $this->assertSame(0, Livewire::test('pages::dashboard.admin.index')->get('chartData')['data'][2]);
            $page->set('installmentFilter', 'expired')->assertSee($membership->user->name)
                ->call('restoreInstallment', $membership->id)->assertHasNoErrors();
            $this->assertSame(1, Livewire::test('pages::dashboard.admin.index')->get('chartData')['data'][2]);
            $membership->update(['payment_status' => 'paid']);
            $page->call('$refresh');
            $this->assertSame(0, $page->get('memberships')->total());
        }
    }

    private function membership(array $attributes = []): Membership
    {
        return Membership::create(array_replace([
            'user_id' => User::factory()->create(['role' => 'member'])->id,
            'type' => 'membership', 'base_price' => 300000, 'price_paid' => 300000,
            'total_paid' => 100000, 'payment_status' => 'partial',
            'start_date' => today(), 'pt_end_date' => today()->addMonth(),
            'status' => 'active', 'is_active' => true,
            'total_sessions' => 10, 'remaining_sessions' => 7,
        ], $attributes));
    }
}
