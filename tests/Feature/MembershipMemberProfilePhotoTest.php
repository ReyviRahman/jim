<?php

namespace Tests\Feature;

use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MembershipMemberProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_package_form_requires_and_focuses_the_profile_photo_for_a_member_without_one(): void
    {
        Storage::fake('public');
        $cashier = $this->createCashier();
        $member = $this->createMember();
        $this->actingAs($cashier);

        Livewire::withQueryParams(['users' => [$member->id]])
            ->test('pages::dashboard.admin.membership.paket')
            ->assertSeeHtml('id="member-photo-'.$member->id.'"')
            ->assertSeeHtml('data-focus-on-invalid')
            ->assertSeeHtml('data-required-message="Foto profil wajib di upload."')
            ->assertSeeHtml('<form wire:submit="save"')
            ->assertSeeHtml('type="submit"')
            ->assertDontSeeHtml('wire:click="save"')
            ->call('save')
            ->assertHasErrors(['memberPhotos.'.$member->id => 'required'])
            ->assertSee('Foto profil wajib di upload.');

        $this->assertNull($member->refresh()->photo);
        $this->assertDatabaseCount('memberships', 0);
    }

    public function test_installment_form_requires_the_profile_photo_for_every_member_without_one(): void
    {
        Storage::fake('public');
        $cashier = $this->createCashier();
        $member = $this->createMember();
        $membership = $this->createMembership($member, $cashier);
        $this->actingAs($cashier);

        Livewire::test('pages::dashboard.admin.cicilan.pay', ['membership' => $membership])
            ->assertSeeHtml('id="member-photo-'.$member->id.'"')
            ->assertSeeHtml('data-focus-on-invalid')
            ->call('save')
            ->assertHasErrors(['memberPhotos.'.$member->id => 'required'])
            ->assertSee('Foto profil wajib di upload.');

        $this->assertNull($member->refresh()->photo);
        $this->assertDatabaseCount('membership_transactions', 0);
    }

    public function test_renewal_form_requires_the_profile_photo_for_every_member_without_one(): void
    {
        Storage::fake('public');
        $cashier = $this->createCashier();
        $member = $this->createMember();
        $membership = $this->createMembership($member, $cashier, paymentStatus: 'paid');
        $this->actingAs($cashier);

        Livewire::test('pages::dashboard.admin.renew.create', ['id' => $membership->id])
            ->assertSeeHtml('id="member-photo-'.$member->id.'"')
            ->assertSeeHtml('data-focus-on-invalid')
            ->call('save')
            ->assertHasErrors(['memberPhotos.'.$member->id => 'required'])
            ->assertSee('Foto profil wajib di upload.');

        $this->assertNull($member->refresh()->photo);
        $this->assertDatabaseCount('membership_transactions', 0);
    }

    public function test_all_three_forms_display_an_existing_member_profile_photo_without_a_required_input(): void
    {
        $cashier = $this->createCashier();
        $photoPath = 'profile-photos/existing.webp';
        $member = $this->createMember(['photo' => $photoPath]);
        $membership = $this->createMembership($member, $cashier);
        $this->actingAs($cashier);
        $photoUrl = asset('storage/'.$photoPath);

        Livewire::withQueryParams(['users' => [$member->id]])
            ->test('pages::dashboard.admin.membership.paket')
            ->assertSee($photoUrl, escape: false)
            ->assertDontSeeHtml('id="member-photo-'.$member->id.'"');

        Livewire::test('pages::dashboard.admin.cicilan.pay', ['membership' => $membership])
            ->assertSee($photoUrl, escape: false)
            ->assertDontSeeHtml('id="member-photo-'.$member->id.'"');

        Livewire::test('pages::dashboard.admin.renew.create', ['id' => $membership->id])
            ->assertSee($photoUrl, escape: false)
            ->assertDontSeeHtml('id="member-photo-'.$member->id.'"');
    }

    public function test_installment_upload_compresses_and_saves_the_missing_profile_photo_before_payment(): void
    {
        Storage::fake('public');
        $cashier = $this->createCashier();
        $member = $this->createMember();
        $membership = $this->createMembership($member, $cashier);
        $this->actingAs($cashier);

        Livewire::test('pages::dashboard.admin.cicilan.pay', ['membership' => $membership])
            ->set('memberPhotos.'.$member->id, UploadedFile::fake()->image('profile.jpg', 1200, 900))
            ->set('amount_paid', 50000)
            ->set('payment_method', 'cash')
            ->set('transaction_type', 'CICILAN MEMBERSHIP')
            ->set('notes', 'Pembayaran dengan foto profil')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.penjualan.index'));

        $photoPath = $member->refresh()->photo;

        $this->assertNotNull($photoPath);
        $this->assertStringEndsWith('.webp', $photoPath);
        Storage::disk('public')->assertExists($photoPath);
        $this->assertDatabaseCount('membership_transactions', 1);
        $this->assertSame('partial', $membership->refresh()->payment_status);
    }

    private function createCashier(): User
    {
        return User::factory()->create([
            'role' => 'kasir_gym',
            'shift' => 'Pagi',
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createMember(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'member',
            'is_active' => true,
            'photo' => null,
        ], $attributes));
    }

    private function createMembership(User $member, User $cashier, string $paymentStatus = 'partial'): Membership
    {
        $package = GymPackage::query()->create([
            'type' => 'gym',
            'name' => 'Paket Foto Profil',
            'category' => 'single',
            'max_members' => 1,
            'price' => 300000,
            'normal_price' => 300000,
            'net_price' => 300000,
            'unrecommended_price' => 300000,
            'discount' => 0,
            'is_active' => true,
        ]);

        $membership = Membership::query()->create([
            'user_id' => $member->id,
            'type' => 'membership',
            'admin_id' => $cashier->id,
            'follow_up_id' => $cashier->id,
            'follow_up_id_two' => $cashier->id,
            'gym_package_id' => $package->id,
            'base_price' => 300000,
            'discount_applied' => 0,
            'admin_fee' => 0,
            'price_paid' => 300000,
            'normal_price' => 300000,
            'net_price' => 300000,
            'unrecommended_price' => 300000,
            'total_paid' => $paymentStatus === 'paid' ? 300000 : 100000,
            'payment_status' => $paymentStatus,
            'start_date' => now()->toDateString(),
            'membership_end_date' => now()->addMonth()->toDateString(),
            'status' => $paymentStatus === 'paid' ? 'active' : 'pending',
            'is_active' => $paymentStatus === 'paid',
            'notes' => 'Membership foto profil',
            'transaction_type' => 'MEMBERSHIP BARU',
            'package_name' => 'PAKET GYM',
        ]);

        $membership->members()->attach($member);

        return $membership;
    }
}
