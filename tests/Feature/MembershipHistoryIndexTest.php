<?php

namespace Tests\Feature;

use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class MembershipHistoryIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_displays_the_latest_membership_for_payers_and_shared_members(): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $payer = $this->createUser(['name' => 'Pembayar Utama']);
        $sharedMember = $this->createUser(['name' => 'Anggota Couple']);
        $trainer = $this->createUser(['name' => 'Coach Terpilih', 'role' => 'pt']);
        $followUp = $this->createUser(['name' => 'Follow Up Pertama', 'role' => 'sales']);
        $followUpTwo = $this->createUser(['name' => 'Follow Up Kedua', 'role' => 'sales']);
        $oldPackage = $this->createPackage('Paket Lama');
        $gymPackage = $this->createPackage('Paket Gym Couple', ['category' => 'couple']);
        $ptPackage = $this->createPackage('Paket PT Couple', [
            'type' => 'pt',
            'category' => 'couple',
            'pt_sessions' => 5,
        ]);

        $this->createMembership($sharedMember, [
            'gym_package_id' => $oldPackage->id,
            'created_at' => '2026-01-01 08:00:00',
        ]);

        $latestMembership = $this->createMembership($payer, [
            'type' => 'bundle_pt_membership',
            'gym_package_id' => $gymPackage->id,
            'pt_package_id' => $ptPackage->id,
            'pt_id' => $trainer->id,
            'follow_up_id' => $followUp->id,
            'follow_up_id_two' => $followUpTwo->id,
            'total_sessions' => 5,
            'remaining_sessions' => 4,
            'created_at' => '2026-02-01 08:00:00',
        ], [$payer, $sharedMember]);

        Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.riwayat.index')
            ->assertSee('Pembayar Utama')
            ->assertSee('Anggota Couple')
            ->assertSee('Paket Gym Couple')
            ->assertSee('Paket PT Couple')
            ->assertSee('Coach Terpilih')
            ->assertSee('Follow Up Pertama')
            ->assertSee('Follow Up Kedua')
            ->assertSeeHtml('href="'.route('admin.riwayat.membership.invoice', $latestMembership).'"')
            ->assertDontSee('Paket Lama');
    }

    public function test_history_search_and_date_filter_keep_displaying_the_latest_membership(): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $matchedMember = $this->createUser([
            'name' => 'Anggota Dicari',
            'email' => 'anggota.dicari@example.test',
        ]);
        $outsideMember = $this->createUser(['name' => 'Anggota Di Luar']);
        $insidePackage = $this->createPackage('Paket Dalam Rentang');
        $latestPackage = $this->createPackage('Paket Terbaru Ditampilkan');
        $outsidePackage = $this->createPackage('Paket Luar Rentang');

        $this->createMembership($matchedMember, [
            'gym_package_id' => $insidePackage->id,
            'created_at' => '2026-05-10 08:00:00',
        ]);
        $this->createMembership($matchedMember, [
            'gym_package_id' => $latestPackage->id,
            'created_at' => '2026-07-10 08:00:00',
        ]);
        $this->createMembership($outsideMember, [
            'gym_package_id' => $outsidePackage->id,
            'created_at' => '2026-07-11 08:00:00',
        ]);

        Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.riwayat.index')
            ->set('search', 'anggota.dicari@example.test')
            ->assertSee('Anggota Dicari')
            ->assertDontSee('Anggota Di Luar')
            ->set('search', '')
            ->call('setDateRange', '2026-05-01 to 2026-05-31')
            ->assertSee('Anggota Dicari')
            ->assertSee('Paket Terbaru Ditampilkan')
            ->assertDontSee('Paket Dalam Rentang')
            ->assertDontSee('Anggota Di Luar');
    }

    public function test_history_sorts_by_latest_membership_date_and_membership_id(): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $oldestMember = $this->createUser(['name' => 'Urutan Paling Lama']);
        $firstTiedMember = $this->createUser(['name' => 'Urutan Tie Pertama']);
        $secondTiedMember = $this->createUser(['name' => 'Urutan Tie Kedua']);

        $this->createMembership($oldestMember, ['created_at' => '2026-01-01 08:00:00']);
        $this->createMembership($firstTiedMember, ['created_at' => '2026-02-01 08:00:00']);
        $this->createMembership($secondTiedMember, ['created_at' => '2026-02-01 08:00:00']);

        Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.riwayat.index')
            ->assertSeeInOrder([
                'Urutan Tie Kedua',
                'Urutan Tie Pertama',
                'Urutan Paling Lama',
            ])
            ->call('sort', 'latest_membership_date')
            ->assertSeeInOrder([
                'Urutan Paling Lama',
                'Urutan Tie Pertama',
                'Urutan Tie Kedua',
            ]);
    }

    public function test_history_date_filter_includes_members_who_access_membership_through_the_pivot(): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $payer = $this->createUser(['name' => 'Pembayar Membership Bersama']);
        $sharedMember = $this->createUser(['name' => 'Anggota Membership Bersama']);
        $outsideMember = $this->createUser(['name' => 'Anggota Pivot Di Luar']);

        $this->createMembership($payer, [
            'created_at' => '2026-05-10 08:00:00',
        ], [$payer, $sharedMember]);
        $this->createMembership($outsideMember, [
            'created_at' => '2026-07-10 08:00:00',
        ], [$outsideMember]);

        Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.riwayat.index')
            ->call('setDateRange', '2026-05-01 to 2026-05-31')
            ->assertSee('Pembayar Membership Bersama')
            ->assertSee('Anggota Membership Bersama')
            ->assertDontSee('Anggota Pivot Di Luar');
    }

    public function test_history_keeps_ten_members_per_page(): void
    {
        $admin = $this->createUser(['role' => 'admin']);

        foreach (range(1, 11) as $number) {
            $member = $this->createUser(['name' => sprintf('Riwayat-%02d', $number)]);

            $this->createMembership($member, [
                'created_at' => Carbon::parse('2026-01-01 08:00:00')->addDays($number),
            ]);
        }

        Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.riwayat.index')
            ->assertSee('Riwayat-11')
            ->assertSee('Riwayat-02')
            ->assertDontSee('Riwayat-01')
            ->call('nextPage')
            ->assertSee('Riwayat-01')
            ->assertDontSee('Riwayat-11');
    }

    public function test_history_query_count_does_not_grow_with_each_rendered_member(): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $trainer = $this->createUser(['role' => 'pt']);
        $followUp = $this->createUser(['role' => 'sales']);
        $followUpTwo = $this->createUser(['role' => 'sales']);
        $gymPackage = $this->createPackage('Paket Query Gym');
        $ptPackage = $this->createPackage('Paket Query PT', ['type' => 'pt', 'pt_sessions' => 5]);

        $this->createMembershipWithDisplayRelations(
            $this->createUser(['name' => 'Query Member 01']),
            $gymPackage,
            $ptPackage,
            $trainer,
            $followUp,
            $followUpTwo,
            '2026-01-01 08:00:00',
        );

        $singleMemberQueryCount = $this->countHistorySelectQueries($admin);

        foreach (range(2, 10) as $number) {
            $this->createMembershipWithDisplayRelations(
                $this->createUser(['name' => sprintf('Query Member %02d', $number)]),
                $gymPackage,
                $ptPackage,
                $trainer,
                $followUp,
                $followUpTwo,
                Carbon::parse('2026-01-01 08:00:00')->addDays($number)->toDateTimeString(),
            );
        }

        $tenMemberQueryCount = $this->countHistorySelectQueries($admin);

        $this->assertLessThanOrEqual(10, $tenMemberQueryCount);
        $this->assertSame($singleMemberQueryCount, $tenMemberQueryCount);
    }

    private function countHistorySelectQueries(User $admin): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.riwayat.index');

        $queryCount = collect(DB::getQueryLog())
            ->filter(static fn (array $query): bool => str_starts_with(strtolower(ltrim($query['query'])), 'select'))
            ->count();

        DB::disableQueryLog();

        return $queryCount;
    }

    private function createMembershipWithDisplayRelations(
        User $member,
        GymPackage $gymPackage,
        GymPackage $ptPackage,
        User $trainer,
        User $followUp,
        User $followUpTwo,
        string $createdAt,
    ): Membership {
        return $this->createMembership($member, [
            'type' => 'bundle_pt_membership',
            'gym_package_id' => $gymPackage->id,
            'pt_package_id' => $ptPackage->id,
            'pt_id' => $trainer->id,
            'follow_up_id' => $followUp->id,
            'follow_up_id_two' => $followUpTwo->id,
            'total_sessions' => 5,
            'remaining_sessions' => 5,
            'created_at' => $createdAt,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, User>  $members
     */
    private function createMembership(User $payer, array $attributes = [], array $members = []): Membership
    {
        $createdAt = Carbon::parse($attributes['created_at'] ?? now());
        unset($attributes['created_at']);

        $membership = Membership::create([
            'user_id' => $payer->id,
            'type' => 'membership',
            'base_price' => 500000,
            'price_paid' => 500000,
            'total_paid' => 500000,
            'payment_status' => 'paid',
            'start_date' => $createdAt->toDateString(),
            'membership_end_date' => $createdAt->copy()->addMonth()->toDateString(),
            'status' => 'active',
            ...$attributes,
        ]);

        $membership->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->saveQuietly();

        if ($members !== []) {
            $membership->members()->attach(collect($members)->pluck('id')->unique()->all());
        }

        return $membership;
    }

    /** @param array<string, mixed> $attributes */
    private function createPackage(string $name, array $attributes = []): GymPackage
    {
        return GymPackage::create([
            'type' => 'gym',
            'name' => $name,
            'category' => 'single',
            'max_members' => 1,
            'price' => 500000,
            'is_active' => true,
            ...$attributes,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function createUser(array $attributes = []): User
    {
        return User::factory()->create([
            'role' => 'member',
            'age' => 30,
            'gender' => 'Laki-laki',
            'phone' => fake()->unique()->numerify('08##########'),
            ...$attributes,
        ]);
    }
}
