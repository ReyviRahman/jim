<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PtMembershipInitialSessionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_sessions_can_be_increased_without_changing_added_sessions_or_status(): void
    {
        $membership = $this->createMembership([
            'total_sessions' => 10,
            'remaining_sessions' => 4,
            'sesi_ditambahkan' => 3,
            'status' => 'completed',
        ]);

        Livewire::test('pages::dashboard.admin.sesi-pt.membership-detail', ['membership' => $membership])
            ->call('openInitialSessionsModal')
            ->assertSet('showInitialSessionsModal', true)
            ->assertSet('initialSessions', '10')
            ->set('initialSessions', '15')
            ->call('saveInitialSessions')
            ->assertSet('showInitialSessionsModal', false);

        $membership->refresh();

        $this->assertSame(15, $membership->total_sessions);
        $this->assertSame(9, $membership->remaining_sessions);
        $this->assertSame(3, $membership->sesi_ditambahkan);
        $this->assertSame('completed', $membership->status);
    }

    public function test_initial_sessions_can_be_decreased_when_remaining_sessions_stay_non_negative(): void
    {
        $membership = $this->createMembership([
            'total_sessions' => 10,
            'remaining_sessions' => 6,
        ]);

        Livewire::test('pages::dashboard.admin.sesi-pt.membership-detail', ['membership' => $membership])
            ->set('initialSessions', '7')
            ->call('saveInitialSessions');

        $membership->refresh();

        $this->assertSame(7, $membership->total_sessions);
        $this->assertSame(3, $membership->remaining_sessions);
    }

    public function test_initial_sessions_cannot_make_remaining_sessions_negative(): void
    {
        $membership = $this->createMembership([
            'total_sessions' => 10,
            'remaining_sessions' => 2,
        ]);

        Livewire::test('pages::dashboard.admin.sesi-pt.membership-detail', ['membership' => $membership])
            ->set('initialSessions', '7')
            ->call('saveInitialSessions')
            ->assertHasErrors('initialSessions');

        $membership->refresh();

        $this->assertSame(10, $membership->total_sessions);
        $this->assertSame(2, $membership->remaining_sessions);
    }

    public function test_initial_sessions_must_be_a_non_negative_integer(): void
    {
        $membership = $this->createMembership();

        Livewire::test('pages::dashboard.admin.sesi-pt.membership-detail', ['membership' => $membership])
            ->set('initialSessions', '')
            ->call('saveInitialSessions')
            ->assertHasErrors(['initialSessions' => 'required'])
            ->set('initialSessions', '-1')
            ->call('saveInitialSessions')
            ->assertHasErrors(['initialSessions' => 'min'])
            ->set('initialSessions', '2.5')
            ->call('saveInitialSessions')
            ->assertHasErrors(['initialSessions' => 'integer']);
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
