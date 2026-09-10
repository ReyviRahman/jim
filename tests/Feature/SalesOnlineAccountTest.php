<?php

namespace Tests\Feature;

use App\Models\AttendanceEmployee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SalesOnlineAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_online_option_only_appears_for_sales_in_create_and_edit_forms(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $sales = User::factory()->create(['role' => 'sales']);
        $this->actingAs($admin);

        foreach ([
            Livewire::test('pages::dashboard.admin.akun.admin.create'),
            Livewire::test('pages::dashboard.admin.akun.admin.edit', ['user' => $sales]),
        ] as $component) {
            $component->set('role', 'sales')->assertSeeHtml('id="is_sales_online"');
            foreach (['kasir_gym', 'kasir_minum', 'pt', 'cleaning_service'] as $role) {
                $component->set('role', $role)->assertDontSeeHtml('id="is_sales_online"');
            }
            $component->set('role', 'sales')->assertSeeHtml('id="is_sales_online"');
        }

        Livewire::test('pages::dashboard.admin.akun.admin.edit', ['user' => $admin])
            ->assertDontSeeHtml('id="is_sales_online"');
    }

    public function test_account_creation_and_edit_persist_sales_online_boolean(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->assertFalse($admin->refresh()->is_sales_online);
        Livewire::actingAs($admin)->test('pages::dashboard.admin.akun.admin.create')
            ->assertSet('is_sales_online', false)
            ->set('name', 'Sales Online Baru')->set('age', 25)->set('phone', '081234567890')
            ->set('alamat', 'Alamat sales')->set('email', 'online@example.com')->set('role', 'sales')
            ->set('is_sales_online', true)->call('store')->assertHasNoErrors();
        $user = User::where('email', 'online@example.com')->firstOrFail();
        $this->assertTrue($user->is_sales_online);
        $component = Livewire::test('pages::dashboard.admin.akun.admin.edit', ['user' => $user])
            ->assertSet('is_sales_online', true)
            ->set('is_sales_online', 'invalid')->call('update')->assertHasErrors('is_sales_online');
        $this->assertTrue($user->refresh()->is_sales_online);
        $component->set('is_sales_online', false)->call('update')->assertHasNoErrors();
        $this->assertFalse($user->refresh()->is_sales_online);
        $component->set('is_sales_online', true)->call('update')->assertHasNoErrors();
        $this->assertTrue($user->refresh()->is_sales_online);
    }

    public function test_sales_online_users_are_hidden_from_monthly_rows_search_and_totals(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $online = User::factory()->create(['name' => 'Online Tersembunyi', 'role' => 'sales', 'is_active' => true, 'is_sales_online' => true]);
        $offline = User::factory()->create(['name' => 'Sales Kantor', 'role' => 'sales', 'is_active' => true]);
        AttendanceEmployee::factory()->create(['user_id' => $online->id]);
        $component = Livewire::test('pages::dashboard.admin.absensi.index', ['employeesOnly' => true])
            ->assertSee('Sales Kantor')->assertDontSee('Online Tersembunyi');
        $this->assertSame([$offline->id], $component->instance()->with()['totals']->keys()->all());
        $component->set('search', 'Online Tersembunyi')->call('previousMonth')
            ->assertDontSee('Online Tersembunyi');
        $this->assertSame(0, $component->instance()->with()['employeeCount']);
        $online->update(['is_sales_online' => false]);
        $component->call('currentMonth')->assertSee('Online Tersembunyi');
        $this->assertDatabaseHas('attendance_employee', ['user_id' => $online->id]);
    }
}
