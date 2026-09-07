<?php

namespace Tests\Feature;

use App\Models\AttendanceShift;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AttendanceShiftTest extends TestCase
{
    use RefreshDatabase;

    private const COMPONENT = 'pages::dashboard.admin.shift-absen.index';

    public function test_admin_can_create_edit_and_delete_shifts_with_duplicate_codes(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get(route('admin.shift-absen.index'))->assertOk()->assertSee('Belum ada shift absen');
        $existing = AttendanceShift::factory()->create();
        $component = Livewire::test(self::COMPONENT)
            ->call('openModal')->assertSet('showModal', true)
            ->set('code', ' ADM-PAGI ')->set('name', ' Admin pagi ')
            ->set('start_time', '07:00')->set('end_time', '15:00')
            ->call('save')->assertHasNoErrors()->assertSet('showModal', false);
        $shift = AttendanceShift::orderByDesc('id')->firstOrFail();
        $this->assertSame('ADM-PAGI', $shift->code);
        $this->assertSame('Admin pagi', $shift->name);
        $this->assertSame(2, AttendanceShift::count());
        $component->call('edit', $shift->id)
            ->assertSet('start_time', '07:00')->assertSet('end_time', '15:00')
            ->set('code', 'ADM-SIANG')->set('name', 'Admin siang')
            ->set('start_time', '14:00')->set('end_time', '22:00')
            ->call('save')->assertHasNoErrors()->assertSee('14:00')->assertDontSee('14:00:00')
            ->assertSeeInOrder(['Admin siang', 'Admin pagi'])
            ->assertSee('wire:confirm=', false);
        $this->assertSame('ADM-SIANG', $shift->fresh()->code);
        $component->call('delete', $shift->id)->assertDontSee('Admin siang');
        $this->assertModelMissing($shift);
        $this->assertModelExists($existing);
    }

    #[DataProvider('invalidFields')]
    public function test_invalid_input_is_rejected(string $field, string $value): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Livewire::test(self::COMPONENT)->call('openModal')
            ->set('code', 'ADM')->set('name', 'Admin pagi')
            ->set('start_time', '07:00')->set('end_time', '15:00')
            ->set($field, $value)->call('save')->assertHasErrors([$field]);
        $this->assertSame(0, AttendanceShift::count());
    }

    public static function invalidFields(): array
    {
        return [
            ['code', '   '], ['code', str_repeat('x', 256)],
            ['name', '   '], ['name', str_repeat('x', 256)],
            ['start_time', ''], ['end_time', ''],
            ['start_time', '24:00'], ['start_time', '07.00'],
            ['end_time', '25:00'], ['end_time', '15:00:30'],
            ['end_time', '07:00'], ['end_time', '06:00'],
        ];
    }

    public function test_closing_modal_resets_input_errors_and_editing_state(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $shift = AttendanceShift::factory()->create();
        Livewire::test(self::COMPONENT)->call('edit', $shift->id)
            ->set('code', '')->call('save')->assertHasErrors('code')
            ->call('closeModal')->assertHasNoErrors()
            ->assertSet('editingId', null)->assertSet('showModal', false)
            ->assertSet('code', '')->assertSet('name', '')
            ->assertSet('start_time', '')->assertSet('end_time', '')
            ->call('openModal')->assertSet('editingId', null);
        $this->assertSame('ADM-PAGI', $shift->fresh()->code);
    }

    #[DataProvider('deniedRoles')]
    public function test_guests_and_non_admins_cannot_access_page_or_component(string $role): void
    {
        $this->get(route('admin.shift-absen.index'))->assertRedirect(route('login'));
        $user = $role === 'head_coach'
            ? User::factory()->headCoach()->create()
            : User::factory()->create(['role' => $role]);
        $this->actingAs($user);
        $this->get(route('admin.shift-absen.index'))->assertRedirect(route('home'));
        Livewire::test(self::COMPONENT)->assertForbidden();
    }

    public static function deniedRoles(): array
    {
        return array_map(fn (string $role): array => [$role], ['member', 'pt', 'kasir_gym', 'kasir_minum', 'sales', 'head_coach']);
    }

    public function test_non_admin_cannot_invoke_actions_after_page_was_loaded(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $shift = AttendanceShift::factory()->create();
        foreach (['openModal', 'edit', 'save', 'delete'] as $action) {
            $this->actingAs($admin);
            $component = Livewire::test(self::COMPONENT)->call('edit', $shift->id);
            $this->actingAs(User::factory()->create(['role' => 'member']));
            $component->call($action, $shift->id)->assertForbidden();
        }
        $this->assertModelExists($shift);
        $this->assertSame(1, AttendanceShift::count());
    }

    public function test_sidebar_link_is_visible_only_to_admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get(route('admin.shift-absen.index'))->assertSee('href="'.route('admin.shift-absen.index').'"', false);
        $this->actingAs(User::factory()->create(['role' => 'kasir_gym']));
        $this->get(route('admin.absensi-karyawan.index'))->assertOk()->assertDontSee('Shift Absen');
    }

    #[DataProvider('recordActions')]
    public function test_missing_records_return_not_found(string $action): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->expectException(ModelNotFoundException::class);
        Livewire::test(self::COMPONENT)->call($action, 999999);
    }

    public static function recordActions(): array
    {
        return [['edit'], ['delete']];
    }
}
