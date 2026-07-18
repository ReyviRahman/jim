<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CheckExpiredMembershipsTest extends TestCase
{
    public function test_expiring_a_pt_membership_does_not_change_its_session_values(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'age' => 30,
            'gender' => 'Laki-laki',
            'phone' => fake()->unique()->numerify('08##########'),
        ]);
        $membership = Membership::create([
            'user_id' => $user->id,
            'type' => 'pt',
            'base_price' => 500000,
            'price_paid' => 500000,
            'total_paid' => 500000,
            'payment_status' => 'paid',
            'start_date' => now()->subMonth()->toDateString(),
            'pt_end_date' => now()->subDay()->toDateString(),
            'total_sessions' => 10,
            'remaining_sessions' => 5,
            'sesi_hangus' => 2,
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->artisan('memberships:check-expired')
            ->assertSuccessful();

        $membership->refresh();

        $this->assertSame('completed', $membership->status);
        $this->assertFalse($membership->is_active);
        $this->assertSame(5, $membership->remaining_sessions);
        $this->assertSame(2, $membership->sesi_hangus);
        Mail::assertNothingOutgoing();
    }
}
