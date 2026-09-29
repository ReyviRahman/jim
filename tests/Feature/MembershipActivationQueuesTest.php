<?php

namespace Tests\Feature;

use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MembershipActivationQueuesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_exact_queue_filters(): void
    {
        $coach = User::factory()->create(['role' => 'pt']);
        $gym = $this->membership('membership');
        $pt = $this->membership('pt');
        $activePt = $this->membership('pt', ['is_active' => true, 'remaining_sessions' => 3]);
        $package = GymPackage::create(['type' => 'pt', 'name' => 'PT Package', 'category' => 'single', 'price' => 300000]);
        $withPackage = $this->membership('pt', ['pt_package_id' => $package->id]);
        $this->membership('membership', ['is_active' => true]);
        foreach (['bundle_pt_membership', 'visit'] as $type) {
            $this->membership($type);
        }
        foreach (['pending', 'rejected', 'completed'] as $status) {
            $this->membership('membership', ['status' => $status]);
            $this->membership('pt', ['status' => $status]);
        }
        $withoutEndDate = $this->membership('pt', ['pt_id' => $coach->id]);
        $withoutCoach = $this->membership('pt', ['pt_end_date' => '2026-10-01']);
        $this->membership('pt', ['pt_id' => $coach->id, 'pt_end_date' => '2026-10-01']);
        $this->assertSame([$gym->id], Livewire::test($this->queueComponent('membership'))->get('memberships')->pluck('id')->all());
        $this->assertSame([$withoutCoach->id, $withoutEndDate->id, $withPackage->id, $activePt->id, $pt->id], Livewire::test($this->queueComponent('pt'))->get('memberships')->pluck('id')->all());
    }

    public function test_pt_with_only_coach_or_end_date_can_be_activated(): void
    {
        $coach = User::factory()->create(['role' => 'pt', 'is_active' => true]);

        foreach ([['pt_id' => $coach->id], ['pt_end_date' => '2026-10-21']] as $attributes) {
            $membership = $this->membership('pt', $attributes);

            $page = Livewire::test($this->queueComponent('pt'))
                ->call('openModal', $membership->id)
                ->assertSet('showModal', true)
                ->set('selectedCoachId', $coach->id)
                ->set('startDate', '2026-09-21')
                ->set('endDate', '2026-10-21')
                ->call('aktivatekan')
                ->assertHasNoErrors()
                ->assertSet('showModal', false);

            $membership->refresh();
            $this->assertSame($coach->id, $membership->pt_id);
            $this->assertSame('2026-10-21', $membership->pt_end_date->toDateString());
            $this->assertSame(0, $page->get('memberships')->total());
        }
    }

    #[DataProvider('types')]
    public function test_activation_saves_only_relevant_fields(string $type): void
    {
        $coach = User::factory()->create(['role' => 'pt', 'is_active' => true]);
        $membership = $this->membership($type, $type === 'pt' ? ['membership_end_date' => '2027-01-01'] : ['pt_end_date' => '2027-02-01']);
        $page = Livewire::test($this->queueComponent($type))->call('openModal', $membership->id)
            ->set('startDate', '2026-09-21')->set('endDate', '2026-09-21');
        if ($type === 'pt') {
            $page->set('selectedCoachId', $coach->id);
        } else {
            $page->assertDontSee('Pilih Coach');
        }
        $page->call('aktivatekan')->assertHasNoErrors()->assertSet('showModal', false)->assertSet('selectedMembershipId', null);
        $this->assertSame(0, $page->get('memberships')->total());
        $membership->refresh();
        $this->assertTrue($membership->is_active);
        $this->assertSame('active', $membership->status);
        $this->assertSame('2026-09-21', $membership->start_date->toDateString());
        $this->assertSame($type === 'pt' ? $coach->id : null, $membership->pt_id);
        $this->assertSame($type === 'pt' ? '2026-09-21' : '2027-02-01', $membership->pt_end_date->toDateString());
        $this->assertSame($type === 'pt' ? '2027-01-01' : '2026-09-21', $membership->membership_end_date->toDateString());
    }

    #[DataProvider('types')]
    public function test_validation_and_cancel_do_not_change_data(string $type): void
    {
        $membership = $this->membership($type);
        $before = $membership->fresh()->getAttributes();
        $page = Livewire::test($this->queueComponent($type))->call('openModal', $membership->id);
        $page->set('startDate', '')->call('aktivatekan')->assertHasErrors('startDate');
        if ($type === 'pt') {
            $page->assertHasErrors('selectedCoachId');
        }
        $page->set('startDate', 'bad-date')->set('endDate', 'bad-date')->call('aktivatekan')->assertHasErrors('startDate');
        $page->call('closeModal')->assertSet('showModal', false)->assertSet('selectedMembershipId', null)
            ->assertSet('startDate', '')->assertSet('endDate', '')->assertHasNoErrors();
        $this->assertSame($before, $membership->fresh()->getAttributes());
    }

    public function test_pt_rejects_invalid_or_deactivated_coach(): void
    {
        $membership = $this->membership('pt');
        $inactive = User::factory()->create(['role' => 'pt', 'is_active' => false]);
        $nonPt = User::factory()->create(['role' => 'member']);
        $coach = User::factory()->create(['role' => 'pt', 'is_active' => true]);
        $page = Livewire::test($this->queueComponent('pt'))->call('openModal', $membership->id)
            ->set('startDate', '2026-09-21')->set('endDate', '2026-10-21');
        $this->assertSame([$coach->id], $page->get('trainers')->pluck('id')->all());
        foreach ([null, 9999999, $inactive->id, $nonPt->id] as $id) {
            $page->set('selectedCoachId', $id)->call('aktivatekan')->assertHasErrors('selectedCoachId');
        }
        $page->set('selectedCoachId', $coach->id);
        $coach->update(['is_active' => false]);
        $page->call('aktivatekan')->assertHasErrors('selectedCoachId');
        $this->assertNull($membership->fresh()->pt_id);
        $this->assertNull($membership->fresh()->pt_end_date);
        $this->assertFalse($membership->fresh()->is_active);
    }

    #[DataProvider('types')]
    public function test_foreign_queue_record_cannot_be_opened(string $type): void
    {
        $wrong = $this->membership($type === 'pt' ? 'membership' : 'pt');
        Livewire::test($this->queueComponent($type))->call('openModal', $wrong->id)->assertNotFound();
    }

    #[DataProvider('types')]
    public function test_stale_records_cannot_be_overwritten(string $type): void
    {
        $coach = User::factory()->create(['role' => 'pt', 'is_active' => true]);
        foreach (['status', 'type', 'delete', 'eligibility'] as $change) {
            $membership = $this->membership($type);
            $page = Livewire::test($this->queueComponent($type))->call('openModal', $membership->id)
                ->set('startDate', '2026-09-21')->set('endDate', '2026-10-21');
            if ($type === 'pt') {
                $page->set('selectedCoachId', $coach->id);
            }
            match ($change) {
                'status' => $membership->update(['status' => 'rejected']),
                'type' => $membership->update(['type' => 'visit']),
                'delete' => $membership->delete(),
                'eligibility' => $membership->update($type === 'pt' ? ['pt_id' => $coach->id, 'pt_end_date' => '2027-01-01'] : ['is_active' => true]),
            };
            $before = $membership->fresh()?->getAttributes();
            $page->call('aktivatekan')->assertHasErrors('membership');
            $this->assertSame($before, $membership->fresh()?->getAttributes());
        }
    }

    #[DataProvider('types')]
    public function test_search_and_pagination_preserve_filters(string $type): void
    {
        $first = $this->membership($type, ['notes' => 'unique-search-note']);
        for ($i = 0; $i < 10; $i++) {
            $this->membership($type);
        }
        $this->membership($type === 'pt' ? 'membership' : 'pt', ['user_id' => $first->user_id, 'notes' => 'unique-search-note']);
        $page = Livewire::test($this->queueComponent($type));
        $this->assertCount(10, $page->get('memberships')->items());
        $page->call('gotoPage', 2);
        $this->assertSame([$first->id], $page->get('memberships')->pluck('id')->all());
        foreach ([$first->user->name, $first->user->email, 'unique-search-note'] as $term) {
            $page->set('search', $term)->assertSet('paginators.page', 1);
            $this->assertSame([$first->id], $page->get('memberships')->pluck('id')->all());
        }
        $page->set('search', 'no-matching-record');
        $this->assertSame(0, $page->get('memberships')->total());
    }

    public function test_access_roles(): void
    {
        $routes = ['admin.membership.gabung', 'admin.pt-booking.index'];
        foreach ([User::factory()->create(['role' => 'admin']), User::factory()->create(['role' => 'kasir_gym']), User::factory()->headCoach()->create()] as $user) {
            $this->actingAs($user);
            foreach ($routes as $route) {
                $this->get(route($route))->assertOk();
            }
        }
        foreach (['member', 'pt', 'kasir_minum', 'head_coach'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach ($routes as $route) {
                $this->get(route($route))->assertRedirect(route('home'));
            }
        }
        auth()->logout();
        foreach ($routes as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
    }

    public function test_livewire_rejects_unauthorized_roles(): void
    {
        foreach (['member', 'pt', 'kasir_minum', 'head_coach'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach (['membership', 'pt'] as $type) {
                Livewire::test($this->queueComponent($type))->assertForbidden();
            }
        }
    }

    public function test_selected_membership_is_locked(): void
    {
        $membership = $this->membership('pt');
        $page = Livewire::test($this->queueComponent('pt'))->call('openModal', $membership->id);
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $page->set('selectedMembershipId', $membership->id + 1);
    }

    #[DataProvider('types')]
    public function test_activation_dates_follow_master_and_save_edited_end_date(string $type): void
    {
        $membership = $this->membership($type);
        $package = $type === 'pt' ? $membership->ptPackage : $membership->gymPackage;
        $package->update(['duration_months' => 1, 'duration_weeks' => 2, 'duration_days' => 1]);
        $page = Livewire::test($this->queueComponent($type))->call('openModal', $membership->id)
            ->assertSet('startDate', today()->toDateString())
            ->assertSee('wire:model="endDate"', false)
            ->set('startDate', '2026-12-20')->assertSet('endDate', '2027-02-02')
            ->set('startDate', '2028-02-01')->assertSet('endDate', '2028-03-16')
            ->set('startDate', '')->assertSet('endDate', '')
            ->set('startDate', '2026-10-01')->assertSet('endDate', '2026-11-14');
        $package->update(['duration_months' => 0, 'duration_weeks' => 0, 'duration_days' => 7]);
        if ($type === 'pt') {
            $page->set('selectedCoachId', User::factory()->create(['role' => 'pt', 'is_active' => true])->id);
        }
        $page->set('endDate', '2026-09-30')->call('aktivatekan')->assertHasErrors('endDate');
        $page->set('endDate', '2099-01-01')->call('aktivatekan')->assertHasNoErrors();
        $membership->refresh();
        $this->assertSame('2099-01-01', ($type === 'pt' ? $membership->pt_end_date : $membership->membership_end_date)->toDateString());
    }

    #[DataProvider('types')]
    public function test_activation_requires_master_duration(string $type): void
    {
        $membership = $this->membership($type);
        $package = $type === 'pt' ? $membership->ptPackage : $membership->gymPackage;
        $package->update(['duration_months' => 0]);
        $page = Livewire::test($this->queueComponent($type))->call('openModal', $membership->id)
            ->assertHasErrors('membership')->assertSet('endDate', '');
        if ($type === 'pt') {
            $page->set('selectedCoachId', User::factory()->create(['role' => 'pt', 'is_active' => true])->id);
        }
        $page->call('aktivatekan')->assertHasErrors('endDate');
        $this->assertFalse($membership->fresh()->is_active);
    }

    public static function types(): array
    {
        return [['membership'], ['pt']];
    }

    private function queueComponent(string $type): string
    {
        return $type === 'pt' ? 'pages::dashboard.admin.pt-booking.index' : 'pages::dashboard.admin.membership.gabung';
    }

    /** @param array<string, mixed> $attributes */
    private function membership(string $type, array $attributes = []): Membership
    {
        $package = GymPackage::create(['type' => $type === 'pt' ? 'pt' : 'gym', 'name' => 'Activation package', 'category' => 'single', 'price' => 300000, 'duration_months' => 1]);

        return Membership::create([
            ($type === 'pt' ? 'pt_package_id' : 'gym_package_id') => $package->id,
            'user_id' => $attributes['user_id'] ?? User::factory()->create(['role' => 'member'])->id,
            'type' => $type, 'base_price' => 300000, 'price_paid' => 300000, 'total_paid' => 300000,
            'payment_status' => 'paid', 'status' => 'active', 'is_active' => false,
            'total_sessions' => 10, 'remaining_sessions' => 10,
            ...$attributes,
        ]);
    }
}
