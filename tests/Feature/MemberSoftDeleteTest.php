<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MemberSoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_member_without_losing_history_or_photo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('profile-photos/old.webp', 'photo');
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['photo' => 'profile-photos/old.webp']);
        $membership = Membership::create([
            'user_id' => $member->id, 'type' => 'membership', 'base_price' => 300000,
            'price_paid' => 0, 'total_paid' => 0, 'start_date' => today(), 'status' => 'pending',
        ]);
        $membership->members()->attach($member);

        Livewire::actingAs($admin)->test('pages::dashboard.admin.akun.member.index')
            ->set('selectedUsers', [(string) $member->id])
            ->call('deleteMember', $member->id)
            ->assertSet('selectedUsers', [])
            ->assertDontSee($member->email);

        $this->assertSoftDeleted($member);
        $this->assertModelExists($membership);
        $this->assertSame($member->id, $membership->fresh()->user->id);
        $this->assertDatabaseHas('membership_users', ['user_id' => $member->id, 'membership_id' => $membership->id]);
        Storage::disk('public')->assertExists($member->photo);
    }

    public function test_deleted_identifiers_can_be_reused_by_a_new_member(): void
    {
        Storage::fake('public');
        $old = User::factory()->create(['hikvision_employee_no' => 'legacy-member']);
        $old->delete();

        Livewire::test('pages::dashboard.admin.akun.member.create')
            ->set('name', $old->name)->set('age', $old->age)->set('gender', $old->gender)
            ->set('email', $old->email)->set('phone', $old->phone)->set('password', 'new-password')
            ->set('photo', UploadedFile::fake()->image('profile.jpg'))
            ->call('store')->assertHasNoErrors();

        $new = User::where('email', $old->email)->sole();
        $this->assertNotSame($old->id, $new->id);
        $this->assertSame(0, $new->memberships()->count());
        Livewire::test('pages::dashboard.admin.akun.member.edit', ['user' => $new])
            ->set('hikvision_employee_no', $old->hikvision_employee_no)
            ->call('update')->assertHasNoErrors();
        $this->assertSame($old->email, $old->fresh()->email);
        $this->assertSame($old->phone, $old->fresh()->phone);
        $this->assertFalse(Auth::attempt(['email' => $old->email, 'password' => 'password']));
        $this->assertTrue(Auth::attempt(['email' => $old->email, 'password' => 'new-password']));
        $this->assertAuthenticatedAs($new);
    }

    public function test_existing_member_identifiers_are_rejected_by_validation(): void
    {
        $member = User::factory()->create();
        Livewire::test('pages::dashboard.admin.akun.member.create')
            ->set('email', $member->email)->set('phone', $member->phone)
            ->call('store')->assertHasErrors(['email' => 'unique', 'phone' => 'unique']);
    }

    #[DataProvider('uniqueColumns')]
    public function test_database_rejects_duplicate_active_identifiers(string $column): void
    {
        $member = User::factory()->create(['hikvision_employee_no' => 'unique-member']);
        $this->expectException(UniqueConstraintViolationException::class);
        User::factory()->create([$column => $member->$column]);
    }

    public static function uniqueColumns(): array
    {
        return [['email'], ['phone'], ['hikvision_employee_no']];
    }

    public function test_deleted_member_cannot_authenticate_or_be_loaded_from_session(): void
    {
        $member = User::factory()->create();
        $member->delete();
        $this->assertFalse(Auth::attempt(['email' => $member->email, 'password' => 'password']));
        $this->assertNull(Auth::getProvider()->retrieveById($member->id));
    }

    public function test_non_admin_cannot_delete_a_member(): void
    {
        $member = User::factory()->create();
        Livewire::actingAs(User::factory()->create(['role' => 'kasir_gym']))
            ->test('pages::dashboard.admin.akun.member.index')
            ->call('deleteMember', $member->id)->assertForbidden();
        $this->assertNotSoftDeleted($member);
    }

    public function test_admin_cannot_delete_non_member_from_member_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->expectException(ModelNotFoundException::class);
        try {
            Livewire::actingAs($admin)->test('pages::dashboard.admin.akun.member.index')
                ->call('deleteMember', $admin->id);
        } finally {
            $this->assertNotSoftDeleted($admin);
        }
    }
}
