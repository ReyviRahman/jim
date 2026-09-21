<?php

namespace Tests\Feature;

use App\Actions\BuildMembershipInvoiceData;
use App\Actions\BuildMembershipTransactionInvoiceData;
use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MembershipPtTrialInterestTest extends TestCase
{
    use RefreshDatabase;

    public static function choices(): array
    {
        $cases = [];
        foreach ([false, true] as $renew) {
            foreach (['yes' => 'Iya', 'no' => 'Tidak', 'later' => 'Mungkin nanti'] as $value => $label) {
                $cases[($renew ? 'renew ' : 'new ').$label] = [$renew, $value, $label];
            }
        }

        return $cases;
    }

    #[DataProvider('choices')]
    public function test_forms_save_choice_and_both_invoices_display_it(bool $renew, string $choice, string $label): void
    {
        [$form, $old] = $this->form($renew);
        $form->assertSet('pt_trial_interest', null)
            ->assertSee('Apakah ingin mencoba program personal trainer atau trial?')
            ->assertSee('Iya')->assertSee('Tidak')->assertSee('Mungkin nanti')
            ->set('pt_trial_interest', $choice)->call('save')->assertHasNoErrors()->assertRedirect();
        $membership = Membership::query()->latest('id')->firstOrFail();
        $this->assertSame($choice, $membership->pt_trial_interest);
        $this->assertSame($label, $membership->ptTrialInterestLabel());
        $this->view('pages.dashboard.admin.riwayat.invoice-pdf', app(BuildMembershipInvoiceData::class)->execute($membership))
            ->assertSee('Minat program PT / trial')->assertSeeHtml('<td class="info-value">'.$label.'</td>');
        $transaction = $membership->transactions()->sole();
        $this->view('pages.dashboard.admin.penjualan.invoice-pdf', app(BuildMembershipTransactionInvoiceData::class)->execute($transaction))
            ->assertSee('Minat program PT / trial')->assertSeeHtml('<td class="info-value">'.$label.'</td>');
        if ($old !== null) {
            $this->assertNotSame($old->id, $membership->id);
            $this->assertSame('later', $old->fresh()->pt_trial_interest);
        }
    }

    public function test_invalid_choice_is_rejected_on_both_forms(): void
    {
        foreach ([false, true] as $renew) {
            [$form] = $this->form($renew);
            $count = Membership::query()->count();
            $form->call('save')->assertHasErrors(['pt_trial_interest' => 'required']);
            $form->set('pt_trial_interest', 'invalid')->call('save')->assertHasErrors('pt_trial_interest');
            $this->assertDatabaseCount('memberships', $count);
        }
    }

    /** @return array{Testable, Membership|null} */
    private function form(bool $renew): array
    {
        Storage::fake('local');
        $shift = Shift::factory()->create(['role' => 'kasir_gym']);
        $admin = User::factory()->create(['role' => 'kasir_gym', 'shift' => $shift->id]);
        $this->actingAs($admin);
        $member = User::factory()->create(['role' => 'member', 'photo' => 'profile-photos/existing.webp']);
        $package = GymPackage::create([
            'type' => 'gym', 'name' => 'Paket Gym', 'category' => 'single', 'max_members' => 1,
            'price' => 300000, 'discount' => 0, 'is_active' => true,
        ]);
        $old = null;
        if ($renew) {
            $old = Membership::create([
                'user_id' => $member->id, 'type' => 'membership', 'gym_package_id' => $package->id,
                'base_price' => 300000, 'price_paid' => 300000, 'total_paid' => 300000, 'payment_status' => 'paid',
                'start_date' => today()->subMonth(), 'membership_end_date' => today(), 'status' => 'active',
                'is_active' => true, 'pt_trial_interest' => 'later',
            ]);
            $old->members()->attach($member->id);
            $form = Livewire::test('pages::dashboard.admin.renew.create', ['id' => $old->id]);
        } else {
            $form = Livewire::withQueryParams(['users' => [$member->id]])->test('pages::dashboard.admin.membership.paket');
        }

        $image = imagecreatetruecolor(400, 160);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imageline($image, 25, 100, 360, 50, imagecolorallocate($image, 0, 0, 0));
        ob_start();
        imagepng($image);
        $signature = 'data:image/png;base64,'.base64_encode(ob_get_clean());
        imagedestroy($image);

        $form->set('registration_type', 'membership')->set('gym_package_id', $package->id)
            ->set('admin_id', $admin->id)->set('follow_up_id', $admin->id)->set('follow_up_id_two', $admin->id)
            ->set('transaction_type', 'MEMBERSHIP')->set('package_name', 'Paket Gym')->set('notes', 'Test minat PT')
            ->set('payment_method', 'cash')->set('payment_type', 'paid')
            ->set('waivers.'.$member->id.'.accepted', true)->set('waivers.'.$member->id.'.signature', $signature);

        return [$form, $old];
    }
}
