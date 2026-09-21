<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class InstallmentSeparationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_lists_separate_types_and_exclude_paid_memberships(): void
    {
        $expected = [0 => [], 1 => []];
        foreach (['membership', 'bundle_pt_membership', 'visit', 'pt'] as $type) {
            foreach (['partial', 'unpaid', 'paid'] as $status) {
                $membership = $this->membership($type, $status);
                if ($status !== 'paid') {
                    $expected[(int) ($type === 'pt')][] = $membership->id;
                }
            }
        }
        foreach ([false, true] as $ptOnly) {
            $page = Livewire::test('pages::dashboard.admin.cicilan.index', ['ptOnly' => $ptOnly]);
            $this->assertEqualsCanonicalizing($expected[(int) $ptOnly], $page->get('memberships')->pluck('id')->all());
        }
    }

    public function test_search_and_pagination_remain_scoped_to_type(): void
    {
        $owner = User::factory()->create(['name' => 'Pemilik Target']);
        $member = User::factory()->create(['name' => 'Anggota Target']);
        foreach (['membership', 'pt'] as $type) {
            for ($i = 0; $i < 11; $i++) {
                $membership = $this->membership($type);
                $membership->update(['user_id' => $owner->id]);
                $membership->members()->attach($member);
            }
        }
        foreach ([false, true] as $ptOnly) {
            $page = Livewire::test('pages::dashboard.admin.cicilan.index', ['ptOnly' => $ptOnly]);
            $this->assertSame(11, $page->get('memberships')->total());
            $page->call('gotoPage', 2);
            $this->assertCount(1, $page->get('memberships')->items());
            foreach ([$owner->name, $member->name] as $search) {
                $page->set('search', $search)->assertSet('paginators.page', 1);
                $this->assertSame(11, $page->get('memberships')->total());
                foreach ($page->get('memberships') as $membership) {
                    $this->assertSame($ptOnly, $membership->type === 'pt');
                }
            }
        }
    }

    public function test_routes_sidebar_and_payment_return_destinations(): void
    {
        $pt = $this->membership('pt');
        $gym = $this->membership('membership');
        foreach ([User::factory()->create(['role' => 'admin']), User::factory()->create(['role' => 'kasir_gym']), User::factory()->headCoach()->create()] as $user) {
            $this->actingAs($user);
            foreach ([$pt, $gym] as $membership) {
                $route = $membership->type === 'pt' ? 'admin.pt-cicilan.index' : 'admin.cicilan.index';
                $this->get(route($route))->assertOk()->assertSeeHtml(route('admin.cicilan.pay', $membership))
                    ->assertSeeHtml(route('admin.pt-cicilan.index'));
                $this->get(route('admin.cicilan.pay', $membership))->assertOk()->assertSeeHtml(route($route));
            }
        }
        foreach ([$pt, $gym] as $membership) {
            $membership->update(['payment_status' => 'paid']);
            $this->get(route('admin.cicilan.pay', $membership))
                ->assertRedirect(route($membership->type === 'pt' ? 'admin.pt-cicilan.index' : 'admin.cicilan.index'));
        }
        foreach (['pt', 'member', 'kasir_minum', 'head_coach'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('admin.pt-cicilan.index'))->assertRedirect(route('home'));
        }
        auth()->logout();
        $this->get(route('admin.pt-cicilan.index'))->assertRedirect(route('login'));
    }

    public function test_pt_mode_cannot_be_changed_by_client(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::test('pages::dashboard.admin.cicilan.index')->set('ptOnly', true);
    }

    private function membership(string $type, string $paymentStatus = 'partial'): Membership
    {
        return Membership::create([
            'user_id' => User::factory()->create(['role' => 'member'])->id,
            'admin_id' => auth()->id(), 'type' => $type,
            'base_price' => 300000, 'price_paid' => 300000, 'total_paid' => 100000,
            'payment_status' => $paymentStatus, 'status' => 'active', 'is_active' => true,
            'start_date' => today(),
        ]);
    }
}
