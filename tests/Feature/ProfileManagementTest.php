<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_route_is_removed_for_guests_and_authenticated_users(): void
    {
        $this->assertFalse(Route::has('profile.edit'));
        $this->get('/dashboard/profile')->assertNotFound();

        $user = $this->createProfileUser();

        $this->actingAs($user)
            ->get('/dashboard/profile')
            ->assertNotFound();
    }

    public function test_profile_component_remains_renderable_for_internal_coverage(): void
    {
        foreach (['member', 'pt', 'admin', 'kasir_gym', 'kasir_minum'] as $role) {
            $user = $this->createProfileUser(['role' => $role]);

            Livewire::actingAs($user)
                ->test('pages::dashboard.profile')
                ->assertSee('Profil Saya');
        }

        $headCoach = $this->createProfileUser([
            'email' => User::HEAD_COACH_EMAIL,
            'role' => 'pt',
        ]);

        Livewire::actingAs($headCoach)
            ->test('pages::dashboard.profile')
            ->assertSee('Profil Saya');
    }

    public function test_both_navbars_hide_the_removed_profile_link_for_every_role(): void
    {
        foreach (['member', 'pt', 'admin', 'kasir_gym', 'kasir_minum', 'sales', 'head_coach', 'cleaning_service'] as $role) {
            $user = $this->createProfileUser([
                'name' => 'Pengguna '.$role,
                'photo' => null,
                'role' => $role,
            ]);

            Livewire::actingAs($user)
                ->test('dashboard.navbar')
                ->assertDontSee('Profil')
                ->assertDontSee('/dashboard/profile', false);

            Livewire::actingAs($user)
                ->test('navbar')
                ->assertDontSee('Profil')
                ->assertDontSee('/dashboard/profile', false)
                ->assertSee('ui-avatars.com/api/?name=', false);
        }
    }

    public function test_dashboard_navbar_refreshes_after_profile_updated_event(): void
    {
        $user = $this->createProfileUser(['name' => 'Nama Lama']);
        $navbar = Livewire::actingAs($user)
            ->test('dashboard.navbar')
            ->assertSee('Nama Lama');

        User::query()->whereKey($user->id)->update(['name' => 'Nama Baru']);

        $navbar
            ->dispatch('profile-updated')
            ->assertSee('Nama Baru')
            ->assertDontSee('Nama Lama');
    }

    public function test_user_can_update_basic_profile_fields_without_redirect_or_current_password(): void
    {
        Storage::fake('public');
        $user = $this->createProfileUser([
            'role' => 'kasir_gym',
            'shift' => 'Pagi',
            'is_active' => true,
            'joined_at' => '2025-01-01',
            'address' => 'Alamat lama',
        ]);
        $otherUser = $this->createProfileUser(['name' => 'Pengguna Lain']);
        $oldPasswordHash = $user->password;

        $this->profileComponent($user)
            ->set('name', 'Nama Diperbarui')
            ->set('occupation', 'Pengusaha')
            ->set('age', 35)
            ->set('gender', 'Perempuan')
            ->set('phone', '081298765432')
            ->set('medical_history', 'Tidak ada')
            ->call('updateProfile')
            ->assertHasNoErrors()
            ->assertNoRedirect()
            ->assertDispatched('profile-updated')
            ->assertSee('Profil berhasil diperbarui.');

        $user->refresh();

        $this->assertSame('Nama Diperbarui', $user->name);
        $this->assertSame('Pengusaha', $user->occupation);
        $this->assertSame(35, $user->age);
        $this->assertSame('Perempuan', $user->gender);
        $this->assertSame('081298765432', $user->phone);
        $this->assertSame('Tidak ada', $user->medical_history);
        $this->assertSame('kasir_gym', $user->role);
        $this->assertSame('Pagi', $user->shift);
        $this->assertSame(1, (int) $user->is_active);
        $this->assertSame('2025-01-01', $user->joined_at?->format('Y-m-d'));
        $this->assertSame('Alamat lama', $user->address);
        $this->assertSame($oldPasswordHash, $user->password);
        $this->assertSame('Pengguna Lain', $otherUser->refresh()->name);
    }

    public function test_email_change_requires_the_current_password(): void
    {
        $user = $this->createProfileUser();

        $this->profileComponent($user)
            ->set('email', 'profil.baru@example.com')
            ->call('updateProfile')
            ->assertHasErrors(['current_password' => 'required'])
            ->assertNoRedirect();

        $this->assertNotSame('profil.baru@example.com', $user->refresh()->email);
    }

    public function test_incorrect_current_password_rejects_credential_changes(): void
    {
        $user = $this->createProfileUser();

        $this->profileComponent($user)
            ->set('email', 'profil.baru@example.com')
            ->set('current_password', 'password-salah')
            ->call('updateProfile')
            ->assertHasErrors(['current_password' => 'current_password']);

        $this->assertNotSame('profil.baru@example.com', $user->refresh()->email);
    }

    public function test_new_password_must_be_confirmed(): void
    {
        $user = $this->createProfileUser();
        $oldPasswordHash = $user->password;

        $this->profileComponent($user)
            ->set('password', 'password-baru')
            ->set('password_confirmation', 'tidak-sama')
            ->set('current_password', 'password')
            ->call('updateProfile')
            ->assertHasErrors(['password' => 'confirmed']);

        $this->assertSame($oldPasswordHash, $user->refresh()->password);
    }

    public function test_correct_current_password_allows_email_and_password_changes(): void
    {
        $user = $this->createProfileUser();

        $this->profileComponent($user)
            ->set('email', 'profil.baru@example.com')
            ->set('password', 'password-baru')
            ->set('password_confirmation', 'password-baru')
            ->set('current_password', 'password')
            ->call('updateProfile')
            ->assertHasNoErrors()
            ->assertNoRedirect()
            ->assertSet('password', '')
            ->assertSet('password_confirmation', '')
            ->assertSet('current_password', '');

        $user->refresh();

        $this->assertSame('profil.baru@example.com', $user->email);
        $this->assertTrue(Hash::check('password-baru', $user->password));
    }

    public function test_email_and_phone_must_be_unique_except_for_the_current_user(): void
    {
        $user = $this->createProfileUser();
        $otherUser = $this->createProfileUser();

        $this->profileComponent($user)
            ->set('email', $otherUser->email)
            ->set('phone', $otherUser->phone)
            ->set('current_password', 'password')
            ->call('updateProfile')
            ->assertHasErrors([
                'email' => 'unique',
                'phone' => 'unique',
            ]);

        $this->assertNotSame($otherUser->email, $user->refresh()->email);
        $this->assertNotSame($otherUser->phone, $user->phone);
    }

    public function test_photo_is_required_for_every_supported_role_when_missing(): void
    {
        foreach (['member', 'pt', 'admin', 'kasir_gym', 'kasir_minum'] as $role) {
            $user = $this->createProfileUser([
                'role' => $role,
                'photo' => null,
            ]);

            $this->profileComponent($user)
                ->assertSeeHtml('data-focus-on-invalid')
                ->call('updateProfile')
                ->assertHasErrors(['photo' => 'required']);
        }
    }

    public function test_existing_photo_allows_saving_without_uploading_a_replacement(): void
    {
        Storage::fake('public');
        $oldPhotoPath = 'profile-photos/existing.webp';
        Storage::disk('public')->put($oldPhotoPath, 'existing-photo');
        $user = $this->createProfileUser(['photo' => $oldPhotoPath]);

        $this->profileComponent($user)
            ->set('name', 'Profil Dengan Foto')
            ->call('updateProfile')
            ->assertHasNoErrors()
            ->assertNoRedirect();

        $this->assertSame($oldPhotoPath, $user->refresh()->photo);
        Storage::disk('public')->assertExists($oldPhotoPath);
    }

    public function test_replacing_photo_stores_webp_and_deletes_the_old_file(): void
    {
        Storage::fake('public');
        $oldPhotoPath = 'profile-photos/existing.webp';
        Storage::disk('public')->put($oldPhotoPath, 'existing-photo');
        $user = $this->createProfileUser(['photo' => $oldPhotoPath]);

        $this->profileComponent($user)
            ->set('photo', UploadedFile::fake()->image('replacement.jpg', 1200, 900))
            ->call('updateProfile')
            ->assertHasNoErrors()
            ->assertNoRedirect()
            ->assertDispatched('profile-updated');

        $user->refresh();

        $this->assertNotNull($user->photo);
        $this->assertNotSame($oldPhotoPath, $user->photo);
        $this->assertStringEndsWith('.webp', $user->photo);
        Storage::disk('public')->assertExists($user->photo);
        Storage::disk('public')->assertMissing($oldPhotoPath);

        $imageInfo = getimagesize(Storage::disk('public')->path($user->photo));

        $this->assertIsArray($imageInfo);
        $this->assertLessThanOrEqual(800, $imageInfo[0]);
        $this->assertLessThanOrEqual(800, $imageInfo[1]);
        $this->assertSame(IMAGETYPE_WEBP, $imageInfo[2]);
    }

    public function test_database_failure_removes_new_photo_and_preserves_old_photo(): void
    {
        Storage::fake('public');
        $oldPhotoPath = 'profile-photos/existing.webp';
        Storage::disk('public')->put($oldPhotoPath, 'existing-photo');
        $user = $this->createProfileUser(['photo' => $oldPhotoPath]);
        User::updating(static function (): void {
            throw new \RuntimeException('Simulated database failure.');
        });

        try {
            $this->profileComponent($user)
                ->set('photo', UploadedFile::fake()->image('replacement.jpg', 1200, 900))
                ->call('updateProfile');

            $this->fail('Database failure was not thrown.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated database failure.', $exception->getMessage());
        }

        $this->assertSame($oldPhotoPath, $user->refresh()->photo);
        Storage::disk('public')->assertExists($oldPhotoPath);
        Storage::disk('public')->assertCount('profile-photos', 1);
    }

    private function profileComponent(User $user): Testable
    {
        return Livewire::actingAs($user)
            ->test('pages::dashboard.profile');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createProfileUser(array $attributes = []): User
    {
        return User::factory()->create([
            'name' => 'Pengguna Profil',
            'occupation' => 'Karyawan',
            'age' => 30,
            'gender' => 'Laki-laki',
            'phone' => fake()->unique()->numerify('08##########'),
            'medical_history' => null,
            'password' => Hash::make('password'),
            'role' => 'member',
            'is_active' => true,
            'photo' => 'profile-photos/existing.webp',
            ...$attributes,
        ]);
    }
}
