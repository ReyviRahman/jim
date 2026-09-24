<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MemberHistoryDesignTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('packageDurations')]
    public function test_monthly_price_uses_discounted_purchase_price(string $packageName, ?string $monthlyPrice): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['role' => 'member', 'photo' => null]);
        $membership = Membership::create([
            'user_id' => $member->id, 'admin_id' => $admin->id, 'type' => 'membership',
            'package_name' => $packageName, 'price_paid' => 450000, 'discount_applied' => 300000,
            'base_price' => 750000, 'total_paid' => 450000, 'payment_status' => 'paid',
            'status' => 'active', 'is_active' => true, 'start_date' => today(),
            'membership_end_date' => today()->addMonths(3),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.riwayat.detail', $member))
            ->assertOk()->assertSee($member->name)->assertSee('Rp 750.000')->assertSee('-40%')
            ->assertSee('Diskon Rp 300.000')->assertSee('Rp 450.000')
            ->assertSeeHtml(route('admin.riwayat.membership.invoice', $membership))
            ->assertSeeHtml(route('admin.akun.member.edit', $member));

        if ($monthlyPrice === null) {
            $response->assertDontSee('Harga per Bulan');
        } else {
            $response->assertSee('Harga per Bulan')->assertSee($monthlyPrice);
        }
    }

    public static function packageDurations(): array
    {
        return [
            'monthly' => ['Membership 3 Monthly Pass', 'Rp 150.000'],
            'yearly' => ['Membership Yearly Pass', 'Rp 37.500'],
            'bonus months' => ['Membership 2 Bulan Plus 1 Bulan', 'Rp 150.000'],
            'unknown' => ['Paket Khusus', null],
            'zero months' => ['Membership 0 Monthly Pass', null],
        ];
    }

    public function test_member_without_memberships_has_an_empty_state(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.riwayat.detail', User::factory()->create(['role' => 'member'])))
            ->assertOk()->assertSee('Belum ada riwayat membership untuk user ini.');
    }
}
