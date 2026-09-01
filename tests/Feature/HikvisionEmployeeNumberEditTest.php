<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HikvisionEmployeeNumberEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_edit_can_update_hikvision_employee_number(): void
    {
        $member = $this->createMember();

        Livewire::test('pages::dashboard.admin.akun.member.edit', ['user' => $member])
            ->assertSee('Hikvision Employee ID')
            ->set('hikvision_employee_no', '  HIK-MEMBER-1403  ')
            ->call('update')
            ->assertHasNoErrors();

        $this->assertSame('HIK-MEMBER-1403', $member->refresh()->hikvision_employee_no);
    }

    public function test_admin_edit_can_update_hikvision_employee_number(): void
    {
        $staff = $this->createStaff();

        Livewire::test('pages::dashboard.admin.akun.admin.edit', ['user' => $staff])
            ->assertSee('Hikvision Employee ID')
            ->set('hikvision_employee_no', 'HIK-STAFF-1117')
            ->call('update')
            ->assertHasNoErrors();

        $this->assertSame('HIK-STAFF-1117', $staff->refresh()->hikvision_employee_no);
    }

    public function test_hikvision_employee_number_must_be_unique(): void
    {
        User::factory()->create(['hikvision_employee_no' => 'HIK-DUPLICATE']);
        $member = $this->createMember();

        Livewire::test('pages::dashboard.admin.akun.member.edit', ['user' => $member])
            ->set('hikvision_employee_no', 'HIK-DUPLICATE')
            ->call('update')
            ->assertHasErrors(['hikvision_employee_no' => 'unique']);

        $this->assertNull($member->refresh()->hikvision_employee_no);
    }

    public function test_hikvision_employee_number_can_be_cleared(): void
    {
        $staff = $this->createStaff(['hikvision_employee_no' => 'HIK-EXISTING']);

        Livewire::test('pages::dashboard.admin.akun.admin.edit', ['user' => $staff])
            ->set('hikvision_employee_no', '   ')
            ->call('update')
            ->assertHasNoErrors();

        $this->assertNull($staff->refresh()->hikvision_employee_no);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createMember(array $attributes = []): User
    {
        return User::factory()->create([
            'role' => 'member',
            'occupation' => 'Karyawan',
            'medical_history' => null,
            'photo' => 'profile-photos/member.webp',
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createStaff(array $attributes = []): User
    {
        return User::factory()->create([
            'role' => 'kasir_gym',
            'shift' => 'Pagi',
            'address' => 'Alamat staf',
            ...$attributes,
        ]);
    }
}
