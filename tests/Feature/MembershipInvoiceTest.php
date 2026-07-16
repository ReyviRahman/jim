<?php

namespace Tests\Feature;

use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\MembershipTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_download_membership_invoice_with_payment_history(): void
    {
        $membership = $this->createMembershipWithInstallments();
        $admin = $this->createUser('admin');

        $response = $this->actingAs($admin)->get(route('admin.riwayat.membership.invoice', $membership));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertHeader('content-disposition', 'attachment; filename=Invoice_Membership_'.$membership->id.'_'.str($membership->user->name)->slug('_').'.pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_invoice_hides_admin_fee_but_keeps_the_total_bill(): void
    {
        $membership = $this->createMembershipWithInstallments()->load([
            'user',
            'admin',
            'gymPackage',
            'ptPackage',
            'transactions',
        ]);

        $this->view('pages.dashboard.admin.riwayat.invoice-pdf', [
            'membership' => $membership,
            'members' => collect([$membership->user]),
            'remainingBalance' => 120000,
        ])
            ->assertDontSee('Biaya Admin')
            ->assertSee('Total Tagihan')
            ->assertSee('Rp 320.000');
    }

    public function test_cashier_can_download_membership_invoice(): void
    {
        $membership = $this->createMembershipWithInstallments();
        $cashier = $this->createUser('kasir_gym');

        $this->actingAs($cashier)
            ->get(route('admin.riwayat.membership.invoice', $membership))
            ->assertOk();
    }

    public function test_member_cannot_download_membership_invoice(): void
    {
        $membership = $this->createMembershipWithInstallments();
        $member = $this->createUser('member');

        $this->actingAs($member)
            ->get(route('admin.riwayat.membership.invoice', $membership))
            ->assertRedirect(route('home'));
    }

    private function createMembershipWithInstallments(): Membership
    {
        $member = $this->createUser('member');
        $admin = $this->createUser('admin');
        $package = GymPackage::create([
            'type' => 'gym',
            'name' => 'Paket Gym Bulanan',
            'category' => 'single',
            'max_members' => 1,
            'price' => 300000,
            'discount' => 0,
            'is_active' => true,
        ]);

        $membership = Membership::create([
            'user_id' => $member->id,
            'admin_id' => $admin->id,
            'type' => 'membership',
            'gym_package_id' => $package->id,
            'base_price' => 300000,
            'discount_applied' => 0,
            'admin_fee' => 20000,
            'price_paid' => 320000,
            'total_paid' => 200000,
            'payment_status' => 'partial',
            'start_date' => now()->toDateString(),
            'membership_end_date' => now()->addMonth()->toDateString(),
            'status' => 'pending',
        ]);

        $membership->members()->attach($member);

        foreach ([100000, 100000] as $index => $amount) {
            MembershipTransaction::create([
                'invoice_number' => 'INV-TEST-'.$membership->id.'-'.($index + 1),
                'membership_id' => $membership->id,
                'user_id' => $member->id,
                'admin_id' => $admin->id,
                'transaction_type' => 'Cicilan '.($index + 1),
                'package_name' => $package->name,
                'amount' => $amount,
                'payment_method' => 'transfer',
                'payment_date' => now()->addDays($index)->toDateString(),
            ]);
        }

        return $membership;
    }

    private function createUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'age' => 30,
            'gender' => 'Laki-laki',
            'phone' => fake()->unique()->numerify('08##########'),
        ]);
    }
}
