<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class MemberProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_requires_a_profile_photo(): void
    {
        Storage::fake('public');

        $this->validCreateComponent()
            ->assertSeeHtml('data-focus-on-invalid')
            ->call('store')
            ->assertHasErrors(['photo' => 'required']);

        $this->assertNull(User::query()->where('email', 'member.photo@example.com')->first());
    }

    public function test_create_rejects_a_non_image_file(): void
    {
        Storage::fake('public');

        $this->validCreateComponent()
            ->set('photo', UploadedFile::fake()->create('profile.pdf', 100, 'application/pdf'))
            ->call('store')
            ->assertHasErrors(['photo' => 'image']);

        Storage::disk('public')->assertDirectoryEmpty('profile-photos');
    }

    public function test_create_rejects_an_unsupported_extension(): void
    {
        Storage::fake('public');

        $this->validCreateComponent()
            ->set('photo', UploadedFile::fake()->image('profile.txt', 100, 100))
            ->call('store')
            ->assertHasErrors(['photo' => 'extensions']);

        Storage::disk('public')->assertDirectoryEmpty('profile-photos');
    }

    public function test_create_rejects_a_photo_larger_than_ten_megabytes(): void
    {
        Storage::fake('public');

        $this->validCreateComponent()
            ->set('photo', UploadedFile::fake()->image('profile.jpg')->size(10241))
            ->call('store')
            ->assertHasErrors(['photo' => 'max']);

        Storage::disk('public')->assertDirectoryEmpty('profile-photos');
    }

    public function test_create_compresses_and_resizes_the_profile_photo_to_webp(): void
    {
        Storage::fake('public');
        $photo = UploadedFile::fake()->image('profile.jpg', 1600, 1200);
        $sourceSize = (int) $photo->getSize();

        $this->validCreateComponent()
            ->set('photo', $photo)
            ->call('store')
            ->assertHasNoErrors();

        $member = User::query()->where('email', 'member.photo@example.com')->sole();

        $this->assertNotNull($member->photo);
        $this->assertStringStartsWith('profile-photos/', $member->photo);
        $this->assertStringEndsWith('.webp', $member->photo);
        Storage::disk('public')->assertExists($member->photo);

        $storedPath = Storage::disk('public')->path($member->photo);
        $imageInfo = getimagesize($storedPath);

        $this->assertIsArray($imageInfo);
        $this->assertLessThanOrEqual(800, $imageInfo[0]);
        $this->assertLessThanOrEqual(800, $imageInfo[1]);
        $this->assertSame(IMAGETYPE_WEBP, $imageInfo[2]);
        $this->assertLessThan($sourceSize, Storage::disk('public')->size($member->photo));
    }

    public function test_create_removes_the_compressed_photo_when_database_creation_fails(): void
    {
        Storage::fake('public');
        User::creating(static function (): void {
            throw new \RuntimeException('Simulated database failure.');
        });

        try {
            $this->validCreateComponent()
                ->set('photo', UploadedFile::fake()->image('profile.jpg', 1200, 900))
                ->call('store');

            $this->fail('Database failure was not thrown.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated database failure.', $exception->getMessage());
        }

        Storage::disk('public')->assertDirectoryEmpty('profile-photos');
    }

    public function test_edit_requires_a_photo_when_the_member_does_not_have_one(): void
    {
        Storage::fake('public');
        $member = $this->createMember(['photo' => null]);

        Livewire::test('pages::dashboard.admin.akun.member.edit', ['user' => $member])
            ->assertSeeHtml('data-focus-on-invalid')
            ->call('update')
            ->assertHasErrors(['photo' => 'required']);

        $this->assertNull($member->refresh()->photo);
    }

    public function test_edit_keeps_the_existing_photo_when_no_replacement_is_uploaded(): void
    {
        Storage::fake('public');
        $oldPhotoPath = 'profile-photos/existing.webp';
        Storage::disk('public')->put($oldPhotoPath, 'existing-photo');
        $member = $this->createMember(['photo' => $oldPhotoPath]);

        Livewire::test('pages::dashboard.admin.akun.member.edit', ['user' => $member])
            ->set('name', 'Member Diperbarui')
            ->call('update')
            ->assertHasNoErrors();

        $this->assertSame('Member Diperbarui', $member->refresh()->name);
        $this->assertSame($oldPhotoPath, $member->photo);
        Storage::disk('public')->assertExists($oldPhotoPath);
    }

    public function test_edit_replaces_the_photo_and_deletes_the_old_file(): void
    {
        Storage::fake('public');
        $oldPhotoPath = 'profile-photos/existing.webp';
        Storage::disk('public')->put($oldPhotoPath, 'existing-photo');
        $member = $this->createMember(['photo' => $oldPhotoPath]);

        Livewire::test('pages::dashboard.admin.akun.member.edit', ['user' => $member])
            ->set('photo', UploadedFile::fake()->image('replacement.png', 1200, 900))
            ->call('update')
            ->assertHasNoErrors();

        $member->refresh();

        $this->assertNotNull($member->photo);
        $this->assertNotSame($oldPhotoPath, $member->photo);
        $this->assertStringEndsWith('.webp', $member->photo);
        Storage::disk('public')->assertExists($member->photo);
        Storage::disk('public')->assertMissing($oldPhotoPath);
    }

    public function test_edit_validation_failure_preserves_the_existing_photo(): void
    {
        Storage::fake('public');
        $oldPhotoPath = 'profile-photos/existing.webp';
        Storage::disk('public')->put($oldPhotoPath, 'existing-photo');
        $member = $this->createMember(['photo' => $oldPhotoPath]);

        Livewire::test('pages::dashboard.admin.akun.member.edit', ['user' => $member])
            ->set('photo', UploadedFile::fake()->create('profile.pdf', 100, 'application/pdf'))
            ->call('update')
            ->assertHasErrors(['photo' => 'image']);

        $this->assertSame($oldPhotoPath, $member->refresh()->photo);
        Storage::disk('public')->assertExists($oldPhotoPath);
        Storage::disk('public')->assertCount('profile-photos', 1);
    }

    public function test_edit_database_failure_removes_the_new_photo_and_preserves_the_old_one(): void
    {
        Storage::fake('public');
        $oldPhotoPath = 'profile-photos/existing.webp';
        Storage::disk('public')->put($oldPhotoPath, 'existing-photo');
        $member = $this->createMember(['photo' => $oldPhotoPath]);
        User::updating(static function (): void {
            throw new \RuntimeException('Simulated database failure.');
        });

        try {
            Livewire::test('pages::dashboard.admin.akun.member.edit', ['user' => $member])
                ->set('photo', UploadedFile::fake()->image('replacement.png', 1200, 900))
                ->call('update');

            $this->fail('Database failure was not thrown.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated database failure.', $exception->getMessage());
        }

        $this->assertSame($oldPhotoPath, $member->refresh()->photo);
        Storage::disk('public')->assertExists($oldPhotoPath);
        Storage::disk('public')->assertCount('profile-photos', 1);
    }

    private function validCreateComponent(): Testable
    {
        return Livewire::test('pages::dashboard.admin.akun.member.create')
            ->set('name', 'Member Photo')
            ->set('occupation', 'Karyawan')
            ->set('age', 30)
            ->set('gender', 'Laki-laki')
            ->set('phone', '081234567890')
            ->set('medical_history', null)
            ->set('email', 'member.photo@example.com')
            ->set('password', 'password');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createMember(array $attributes = []): User
    {
        return User::factory()->create([
            'name' => 'Member Photo',
            'occupation' => 'Karyawan',
            'age' => 30,
            'gender' => 'Laki-laki',
            'phone' => fake()->unique()->numerify('08##########'),
            'medical_history' => null,
            'role' => 'member',
            ...$attributes,
        ]);
    }
}
