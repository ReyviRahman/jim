<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class CleaningServiceAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_cleaning_service_account_without_a_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.akun.admin.create')
            ->assertSee('Cleaning Service')
            ->set('name', 'Tim Cleaning Service')
            ->set('gender', 'Laki-laki')
            ->set('age', 28)
            ->set('phone', '081234567890')
            ->set('joined_at', '2026-09-02')
            ->set('alamat', 'Jl. Cleaning Service No. 1')
            ->set('email', 'cleaning-service@example.com')
            ->set('role', 'cleaning_service')
            ->set('password', '')
            ->call('store')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.akun.admin.index'));

        $cleaningService = User::where('email', 'cleaning-service@example.com')->firstOrFail();

        $this->assertSame('cleaning_service', $cleaningService->role);
        $this->assertNull($cleaningService->shift);
        $this->assertNotSame('', $cleaningService->password);
        $this->assertFalse(Hash::check('12345678', $cleaningService->password));
    }

    public function test_cleaning_service_account_can_be_listed_edited_and_toggled(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cleaningService = $this->createCleaningServiceUser([
            'name' => 'Cleaning Service Lama',
            'email' => 'cleaning-service-lama@example.com',
        ]);
        $originalPassword = $cleaningService->password;

        Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.akun.admin.edit', ['user' => $cleaningService])
            ->assertSet('role', 'cleaning_service')
            ->assertSee('Cleaning Service')
            ->set('name', 'Cleaning Service Baru')
            ->set('password', '')
            ->call('update')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.akun.admin.index'));

        $cleaningService->refresh();

        $this->assertSame('Cleaning Service Baru', $cleaningService->name);
        $this->assertSame($originalPassword, $cleaningService->password);

        Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.akun.admin.index')
            ->set('search', 'Cleaning Service Baru')
            ->assertSee('Cleaning Service Baru')
            ->assertSee('Cleaning Service')
            ->call('toggleStatus', $cleaningService->id)
            ->assertSee('berhasil dinonaktifkan');

        $this->assertFalse((bool) $cleaningService->refresh()->is_active);
    }

    public function test_converting_a_login_enabled_account_to_cleaning_service_clears_shift_and_rotates_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cashier = User::factory()->create([
            'role' => 'kasir_gym',
            'shift' => 'Pagi',
            'address' => 'Jl. Kasir No. 1',
            'password' => Hash::make('cashier-secret'),
        ]);
        $originalPassword = $cashier->password;

        Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.akun.admin.edit', ['user' => $cashier])
            ->set('role', 'cleaning_service')
            ->set('password', '')
            ->call('update')
            ->assertHasNoErrors();

        $cashier->refresh();

        $this->assertSame('cleaning_service', $cashier->role);
        $this->assertNull($cashier->shift);
        $this->assertNotSame($originalPassword, $cashier->password);
        $this->assertFalse(Hash::check('cashier-secret', $cashier->password));
    }

    public function test_cleaning_service_and_sales_accounts_cannot_log_in_with_valid_credentials(): void
    {
        foreach (['cleaning_service', 'sales'] as $role) {
            $user = User::factory()->create([
                'email' => "{$role}@example.com",
                'role' => $role,
                'password' => Hash::make('known-password'),
            ]);

            Livewire::test('pages::login')
                ->set('email', $user->email)
                ->set('password', 'known-password')
                ->call('login')
                ->assertNoRedirect();

            $this->assertGuest();
        }
    }

    public function test_cleaning_service_account_cannot_access_master_account_routes(): void
    {
        $cleaningService = $this->createCleaningServiceUser();

        $this->actingAs($cleaningService)
            ->get(route('admin.akun.admin.index'))
            ->assertRedirect(route('home'));

        $this->get(route('admin.akun.admin.create'))
            ->assertRedirect(route('home'));

        $this->get(route('admin.akun.admin.edit', $cleaningService))
            ->assertRedirect(route('home'));
    }

    private function createCleaningServiceUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'cleaning_service',
            'shift' => null,
            'address' => 'Jl. Cleaning Service No. 1',
            'joined_at' => '2026-09-02',
            'is_active' => true,
        ], $attributes));
    }
}
