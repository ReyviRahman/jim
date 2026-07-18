<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PtMembershipRemainingSessionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_remaining_sessions_can_be_edited_without_changing_total_sessions(): void
    {
        $this->actingAs($this->createUser(['role' => 'admin']));
        $membership = $this->createMembership([
            'total_sessions' => 10,
            'remaining_sessions' => 4,
        ]);

        Livewire::test('pages::dashboard.admin.sesi-pt.membership-detail', ['membership' => $membership])
            ->call('openRemainingSessionsModal')
            ->assertSet('showRemainingSessionsModal', true)
            ->assertSet('remainingSessions', '4')
            ->set('remainingSessions', '7')
            ->call('saveRemainingSessions')
            ->assertSet('showRemainingSessionsModal', false)
            ->assertSee('Sisa sesi berhasil diperbarui.');

        $membership->refresh();

        $this->assertSame(10, $membership->total_sessions);
        $this->assertSame(7, $membership->remaining_sessions);
    }

    public function test_remaining_sessions_can_be_zero(): void
    {
        $this->actingAs($this->createUser(['role' => 'head_coach']));
        $membership = $this->createMembership();

        Livewire::test('pages::dashboard.admin.sesi-pt.membership-detail', ['membership' => $membership])
            ->set('remainingSessions', '0')
            ->call('saveRemainingSessions')
            ->assertHasNoErrors();

        $this->assertSame(0, $membership->fresh()->remaining_sessions);
    }

    public function test_remaining_sessions_cannot_exceed_total_sessions(): void
    {
        $this->actingAs($this->createUser(['role' => 'admin']));
        $membership = $this->createMembership([
            'total_sessions' => 10,
            'remaining_sessions' => 4,
        ]);

        Livewire::test('pages::dashboard.admin.sesi-pt.membership-detail', ['membership' => $membership])
            ->set('remainingSessions', '11')
            ->call('saveRemainingSessions')
            ->assertHasErrors(['remainingSessions' => 'max']);

        $this->assertSame(4, $membership->fresh()->remaining_sessions);
    }

    public function test_remaining_sessions_must_be_a_non_negative_integer(): void
    {
        $this->actingAs($this->createUser(['role' => 'admin']));
        $membership = $this->createMembership();

        Livewire::test('pages::dashboard.admin.sesi-pt.membership-detail', ['membership' => $membership])
            ->set('remainingSessions', '')
            ->call('saveRemainingSessions')
            ->assertHasErrors(['remainingSessions' => 'required'])
            ->set('remainingSessions', '-1')
            ->call('saveRemainingSessions')
            ->assertHasErrors(['remainingSessions' => 'min'])
            ->set('remainingSessions', '2.5')
            ->call('saveRemainingSessions')
            ->assertHasErrors(['remainingSessions' => 'integer']);
    }

    public function test_other_roles_cannot_edit_remaining_sessions(): void
    {
        $this->actingAs($this->createUser(['role' => 'member']));
        $membership = $this->createMembership(['remaining_sessions' => 4]);

        Livewire::test('pages::dashboard.admin.sesi-pt.membership-detail', ['membership' => $membership])
            ->set('remainingSessions', '7')
            ->call('saveRemainingSessions')
            ->assertForbidden();

        $this->assertSame(4, $membership->fresh()->remaining_sessions);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createMembership(array $attributes = []): Membership
    {
        $member = $this->createUser();
        $personalTrainer = $this->createUser(['role' => 'pt']);

        return Membership::create([
            'user_id' => $member->id,
            'pt_id' => $personalTrainer->id,
            'type' => 'pt',
            'base_price' => 100000,
            'price_paid' => 100000,
            'total_paid' => 100000,
            'payment_status' => 'paid',
            'start_date' => today(),
            'pt_end_date' => today()->addMonth(),
            'total_sessions' => 10,
            'remaining_sessions' => 10,
            'sesi_ditambahkan' => 0,
            'status' => 'active',
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createUser(array $attributes = []): User
    {
        return User::factory()->create([
            'age' => 30,
            'gender' => 'Laki-laki',
            'phone' => fake()->unique()->numerify('08##########'),
            ...$attributes,
        ]);
    }
}
