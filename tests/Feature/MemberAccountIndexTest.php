<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MemberAccountIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_account_index_displays_members_with_pending_memberships(): void
    {
        $pendingMember = $this->createUser([
            'name' => 'Member Menunggu Pembayaran',
            'role' => 'member',
        ]);
        $memberWithoutMembership = $this->createUser([
            'name' => 'Member Tanpa Paket',
            'role' => 'member',
        ]);
        $admin = $this->createUser([
            'name' => 'Admin Tidak Ditampilkan',
            'role' => 'admin',
        ]);

        $membership = Membership::create([
            'user_id' => $pendingMember->id,
            'type' => 'membership',
            'base_price' => 300000,
            'price_paid' => 0,
            'total_paid' => 0,
            'start_date' => now()->toDateString(),
            'status' => 'pending',
        ]);
        $membership->members()->attach($pendingMember);

        Livewire::test('pages::dashboard.admin.akun.member.index')
            ->assertSee($pendingMember->name)
            ->assertSee($memberWithoutMembership->name)
            ->assertDontSee($admin->name);
    }

    private function createUser(array $attributes): User
    {
        return User::factory()->create([
            'age' => 30,
            'gender' => 'Laki-laki',
            'phone' => fake()->unique()->numerify('08##########'),
            ...$attributes,
        ]);
    }
}
