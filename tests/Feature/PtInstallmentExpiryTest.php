<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PtInstallmentExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_allowed_roles_can_hide_and_restore_without_changing_membership_or_payments(): void
    {
        foreach (['admin', 'kasir_gym', 'head_coach'] as $role) {
            $actor = $role === 'head_coach' ? User::factory()->headCoach()->create() : User::factory()->create(['role' => $role]);
            $membership = $this->membership();
            $original = $membership->refresh()->getAttributes();
            $page = Livewire::actingAs($actor)->test('pages::dashboard.admin.cicilan.index', ['ptOnly' => true]);
            $page->call('markExpired', $membership->id)->assertHasNoErrors()
                ->assertDontSee($membership->user->name);
            $membership->refresh();
            $this->assertNotNull($membership->pt_installment_expired_at);
            $this->assertSame($actor->id, $membership->pt_installment_expired_by);
            $markedAt = $membership->pt_installment_expired_at->toDateTimeString();
            $this->travel(1)->minute();
            $page->call('markExpired', $membership->id)->assertHasNoErrors();
            $this->assertSame($markedAt, $membership->refresh()->pt_installment_expired_at->toDateTimeString());
            foreach ($original as $field => $value) {
                if (! in_array($field, ['updated_at', 'pt_installment_expired_at', 'pt_installment_expired_by'], true)) {
                    $this->assertSame($value, $membership->getAttributes()[$field], $field);
                }
            }
            $page->set('installmentFilter', 'expired')->assertSee($membership->user->name)->assertSee('Pulihkan')
                ->call('restoreInstallment', $membership->id)->assertHasNoErrors()->assertDontSee($membership->user->name)
                ->call('restoreInstallment', $membership->id)->assertHasNoErrors()
                ->set('installmentFilter', 'active')->assertSee($membership->user->name);
            $this->assertNull($membership->refresh()->pt_installment_expired_at);
            $this->assertNull($membership->pt_installment_expired_by);
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
        $hidden = $this->membership(['pt_installment_expired_at' => now(), 'pt_installment_expired_by' => $admin->id]);
        $hidden->user->update(['name' => 'Member Yang Hangus']);
        $page = Livewire::actingAs($admin)->test('pages::dashboard.admin.cicilan.index', ['ptOnly' => true]);
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
        foreach ([['type' => 'membership'], ['type' => 'bundle_pt_membership'], ['payment_status' => 'paid']] as $attributes) {
            $membership = $this->membership($attributes);
            foreach (['markExpired', 'restoreInstallment'] as $method) {
                Livewire::actingAs($admin)->test('pages::dashboard.admin.cicilan.index', ['ptOnly' => true])
                    ->call($method, $membership->id)->assertHasErrors('installment');
            }
            $this->assertNull($membership->refresh()->pt_installment_expired_at);
        }
        $membership = $this->membership();
        foreach (['member', 'pt', 'kasir_minum'] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            foreach (['markExpired', 'restoreInstallment'] as $method) {
                Livewire::actingAs($actor)->test('pages::dashboard.admin.cicilan.index', ['ptOnly' => true])
                    ->call($method, $membership->id)->assertForbidden();
            }
        }
        Livewire::actingAs($admin)->test('pages::dashboard.admin.cicilan.index')
            ->call('markExpired', $membership->id)->assertForbidden();
        $this->assertNull($membership->refresh()->pt_installment_expired_at);
    }

    public function test_regular_installments_are_unchanged_and_empty_pt_list_has_accurate_message(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $regular = $this->membership(['type' => 'membership']);
        Livewire::actingAs($admin)->test('pages::dashboard.admin.cicilan.index')
            ->assertSee($regular->user->name)->assertDontSee('Tandai Hangus')->assertDontSee('Status cicilan');
        $this->actingAs($admin)->get(route('admin.pt-cicilan.index'))->assertOk()
            ->assertSee('Tidak ada cicilan PT aktif.')->assertDontSee('Semua lunas!');
    }

    private function membership(array $attributes = []): Membership
    {
        return Membership::create(array_replace([
            'user_id' => User::factory()->create(['role' => 'member'])->id,
            'type' => 'pt', 'base_price' => 300000, 'price_paid' => 300000,
            'total_paid' => 100000, 'payment_status' => 'partial',
            'start_date' => today(), 'pt_end_date' => today()->addMonth(),
            'status' => 'active', 'is_active' => true,
            'total_sessions' => 10, 'remaining_sessions' => 7,
        ], $attributes));
    }
}
