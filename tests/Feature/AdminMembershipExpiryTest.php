<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminMembershipExpiryTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('expiryCases')]
    public function test_package_expiry_includes_the_entire_end_date(string $type, string $startDate, string $time, bool $expired): void
    {
        $this->travelTo(Carbon::parse($time));
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create();

        Membership::create([
            'user_id' => $member->id,
            'type' => $type,
            'base_price' => 300000,
            'price_paid' => 300000,
            'total_paid' => 300000,
            'payment_status' => 'paid',
            'start_date' => $startDate,
            'membership_end_date' => '2026-09-21',
            'pt_end_date' => '2026-09-21',
            'status' => 'active',
            'is_active' => true,
        ]);

        $component = Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.membership.index');

        if ($type === 'pt') {
            $this->assertSame(0, $component->get('memberships')->total());

            return;
        }

        $component->assertSee('Berlaku sampai 21 Sep 2026');

        if ($expired) {
            $component->assertSee('Expired');
        } else {
            $component->assertDontSee('Expired')->assertSee('Sisa 0 bulan');
        }
    }

    public static function expiryCases(): iterable
    {
        foreach (['membership', 'pt', 'bundle_pt_membership'] as $type) {
            foreach (['2026-08-21', '2026-09-21'] as $startDate) {
                foreach (['2026-09-20 12:00:00', '2026-09-21 00:00:00', '2026-09-21 12:00:00', '2026-09-21 23:59:59', '2026-09-22 00:00:00'] as $time) {
                    yield "$type $startDate $time" => [$type, $startDate, $time, $time === '2026-09-22 00:00:00'];
                }
            }
        }
    }

    public function test_membership_list_excludes_only_pt_type_even_when_searching_and_filtering(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['name' => 'Member Filter Test']);
        $expected = [];

        foreach (['membership', 'bundle_pt_membership', 'visit', 'pt'] as $type) {
            $membership = Membership::create([
                'user_id' => $member->id,
                'type' => $type,
                'base_price' => 300000,
                'price_paid' => 300000,
                'total_paid' => 300000,
                'payment_status' => 'paid',
                'start_date' => today(),
                'membership_end_date' => today()->addMonth(),
                'status' => 'active',
                'is_active' => true,
            ]);

            if ($type !== 'pt') {
                $expected[] = $membership->id;
            }
        }

        $page = Livewire::actingAs($admin)->test('pages::dashboard.admin.membership.index');
        $this->assertEqualsCanonicalizing($expected, $page->get('memberships')->pluck('id')->all());
        $page->set('search', $member->name)->set('filterTime', 'today');
        $this->assertEqualsCanonicalizing($expected, $page->get('memberships')->pluck('id')->all());
    }
}
