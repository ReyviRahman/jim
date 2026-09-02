<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class OperationalAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_an_operational_account_without_a_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.akun.admin.create')
            ->assertSee('Operasional')
            ->set('name', 'Tim Operasional')
            ->set('gender', 'Laki-laki')
            ->set('age', 28)
            ->set('phone', '081234567890')
            ->set('joined_at', '2026-09-02')
            ->set('alamat', 'Jl. Operasional No. 1')
            ->set('email', 'operasional@example.com')
            ->set('role', 'operasional')
            ->set('password', '')
            ->call('store')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.akun.admin.index'));

        $operational = User::where('email', 'operasional@example.com')->firstOrFail();

        $this->assertSame('operasional', $operational->role);
        $this->assertNull($operational->shift);
        $this->assertNotSame('', $operational->password);
        $this->assertFalse(Hash::check('12345678', $operational->password));
    }

    public function test_operational_account_can_be_listed_edited_and_toggled(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $operational = $this->createOperationalUser([
            'name' => 'Operasional Lama',
            'email' => 'operasional-lama@example.com',
        ]);
        $originalPassword = $operational->password;

        Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.akun.admin.edit', ['user' => $operational])
            ->assertSet('role', 'operasional')
            ->assertSee('Operasional')
            ->set('name', 'Operasional Baru')
            ->set('password', '')
            ->call('update')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.akun.admin.index'));

        $operational->refresh();

        $this->assertSame('Operasional Baru', $operational->name);
        $this->assertSame($originalPassword, $operational->password);

        Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.akun.admin.index')
            ->set('search', 'Operasional Baru')
            ->assertSee('Operasional Baru')
            ->call('toggleStatus', $operational->id)
            ->assertSee('berhasil dinonaktifkan');

        $this->assertFalse((bool) $operational->refresh()->is_active);
    }

    public function test_converting_a_login_enabled_account_to_operational_clears_shift_and_rotates_password(): void
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
            ->set('role', 'operasional')
            ->set('password', '')
            ->call('update')
            ->assertHasNoErrors();

        $cashier->refresh();

        $this->assertSame('operasional', $cashier->role);
        $this->assertNull($cashier->shift);
        $this->assertNotSame($originalPassword, $cashier->password);
        $this->assertFalse(Hash::check('cashier-secret', $cashier->password));
    }

    public function test_operational_and_sales_accounts_cannot_log_in_with_valid_credentials(): void
    {
        foreach (['operasional', 'sales'] as $role) {
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

    public function test_operational_account_cannot_access_master_account_routes(): void
    {
        $operational = $this->createOperationalUser();

        $this->actingAs($operational)
            ->get(route('admin.akun.admin.index'))
            ->assertRedirect(route('home'));

        $this->get(route('admin.akun.admin.create'))
            ->assertRedirect(route('home'));

        $this->get(route('admin.akun.admin.edit', $operational))
            ->assertRedirect(route('home'));
    }

    private function createOperationalUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'operasional',
            'shift' => null,
            'address' => 'Jl. Operasional No. 1',
            'joined_at' => '2026-09-02',
            'is_active' => true,
        ], $attributes));
    }
}
