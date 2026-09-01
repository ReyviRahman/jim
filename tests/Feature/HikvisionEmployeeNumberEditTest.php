<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

    public function test_member_index_displays_hikvision_employee_number_under_id_header(): void
    {
        $member = $this->createMember([
            'id' => 9101,
            'hikvision_employee_no' => 'HIK-MEMBER-TABLE',
        ]);

        $component = Livewire::test('pages::dashboard.admin.akun.member.index')
            ->assertSeeHtml('<th scope="col" class="px-6 py-3 font-medium">ID</th>');

        $this->assertMatchesRegularExpression(
            '/<td class="px-6 py-4 font-medium text-heading">\s*'.preg_quote($member->hikvision_employee_no, '/').'\s*<\/td>/',
            $component->html(),
        );
    }

    public function test_admin_index_displays_hikvision_employee_number_under_id_header(): void
    {
        $staff = $this->createStaff([
            'id' => 9102,
            'hikvision_employee_no' => 'HIK-STAFF-TABLE',
        ]);

        $component = Livewire::test('pages::dashboard.admin.akun.admin.index')
            ->assertSeeHtml('<th scope="col" class="px-6 py-3 font-medium">ID</th>');

        $this->assertMatchesRegularExpression(
            '/<td class="px-6 py-4 font-medium text-heading">\s*'.preg_quote($staff->hikvision_employee_no, '/').'\s*<\/td>/',
            $component->html(),
        );
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

    public function test_member_index_modal_can_update_hikvision_employee_number(): void
    {
        $member = $this->createMember(['hikvision_employee_no' => 'HIK-OLD']);
        Http::preventStrayRequests();

        Livewire::test('pages::dashboard.admin.akun.member.index')
            ->assertSee('Edit Hikvision Employee ID')
            ->call('openHikvisionEmployeeModal', $member->id)
            ->assertSet('showHikvisionEmployeeModal', true)
            ->assertSet('editingHikvisionMemberName', $member->name)
            ->assertSet('hikvisionEmployeeNo', 'HIK-OLD')
            ->set('hikvisionEmployeeNo', '  HIK-NEW  ')
            ->call('updateHikvisionEmployeeNo')
            ->assertHasNoErrors()
            ->assertSet('showHikvisionEmployeeModal', false)
            ->assertSee("Hikvision Employee ID untuk {$member->name} berhasil diperbarui.");

        $this->assertSame('HIK-NEW', $member->refresh()->hikvision_employee_no);
        Http::assertNothingSent();
    }

    public function test_member_index_modal_rejects_a_duplicate_hikvision_employee_number(): void
    {
        User::factory()->create(['hikvision_employee_no' => 'HIK-DUPLICATE']);
        $member = $this->createMember();

        Livewire::test('pages::dashboard.admin.akun.member.index')
            ->call('openHikvisionEmployeeModal', $member->id)
            ->set('hikvisionEmployeeNo', 'HIK-DUPLICATE')
            ->call('updateHikvisionEmployeeNo')
            ->assertHasErrors(['hikvisionEmployeeNo' => 'unique'])
            ->assertSet('showHikvisionEmployeeModal', true);

        $this->assertNull($member->refresh()->hikvision_employee_no);
    }

    public function test_member_index_modal_can_clear_hikvision_employee_number(): void
    {
        $member = $this->createMember(['hikvision_employee_no' => 'HIK-EXISTING']);

        Livewire::test('pages::dashboard.admin.akun.member.index')
            ->call('openHikvisionEmployeeModal', $member->id)
            ->set('hikvisionEmployeeNo', '   ')
            ->call('updateHikvisionEmployeeNo')
            ->assertHasNoErrors()
            ->assertSet('showHikvisionEmployeeModal', false);

        $this->assertNull($member->refresh()->hikvision_employee_no);
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
