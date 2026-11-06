<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\PtBooking;
use App\Models\PtPaymentBatch;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Exceptions\PublicPropertyNotFoundException;
use Livewire\Livewire;
use Tests\TestCase;

class PtSessionSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_period_handles_cutoff_and_year_boundaries(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'pt']));
        foreach ([
            ['2026-11-06', '2026-10-16', '2026-11-15'],
            ['2026-11-15', '2026-10-16', '2026-11-15'],
            ['2026-11-16', '2026-11-16', '2026-12-15'],
            ['2027-01-01', '2026-12-16', '2027-01-15'],
            ['2028-03-01', '2028-02-16', '2028-03-15'],
        ] as [$today, $start, $end]) {
            $this->travelTo(Carbon::parse($today));
            Livewire::test('pages::dashboard.pt.total-sesi')
                ->assertSet('period', ['start' => $start, 'end' => $end]);
        }
    }

    public function test_submission_only_includes_own_eligible_sessions_and_cannot_be_duplicated(): void
    {
        $this->travelTo(Carbon::parse('2026-11-06'));
        $pt = User::factory()->create(['role' => 'pt']);
        $otherPt = User::factory()->create(['role' => 'pt']);
        $first = $this->booking($pt, ['booking_date' => '2026-10-16']);
        $last = $this->booking($pt, ['booking_date' => '2026-11-15']);
        $this->booking($pt, ['booking_date' => '2026-10-15']);
        $this->booking($pt, ['booking_date' => '2026-11-16']);
        $this->booking($pt, ['is_free' => true]);
        $this->booking($pt, ['is_paid' => true]);
        $this->booking($pt, ['attendance' => 'noshow']);
        $this->booking($pt, ['attendance' => 'not_yet']);
        $other = $this->booking($otherPt);

        Livewire::actingAs($pt)->test('pages::dashboard.pt.total-sesi')
            ->set('search', 'Nama tidak ditemukan')
            ->assertSee('Tidak ada data sesi pada periode ini.')
            ->call('submitSessions')->assertSee('Menunggu persetujuan')
            ->call('submitSessions')->assertSee('Tidak ada sesi baru');

        $batch = PtPaymentBatch::sole();
        $this->assertSame('pending', $batch->status);
        $this->assertSame($pt->id, $batch->requested_by);
        $this->assertNull($batch->paid_by);
        $this->assertEqualsCanonicalizing([$first->id, $last->id], $batch->items->pluck('pt_booking_id')->all());
        $this->assertFalse($first->fresh()->is_paid);
        $this->assertFalse($other->fresh()->is_paid);
    }

    public function test_admin_sees_and_approves_request_once_and_pt_sees_approved_status(): void
    {
        $pt = User::factory()->create(['role' => 'pt']);
        $booking = $this->booking($pt);
        Livewire::actingAs($pt)->test('pages::dashboard.pt.total-sesi')->call('submitSessions');
        $batch = PtPaymentBatch::sole();
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)->test('pages::dashboard.admin.sesi-pt.detail', ['user' => $pt])
            ->assertSee('Menunggu persetujuan')->assertSee('Approve')
            ->call('paySessions');
        $this->assertSame(1, PtPaymentBatch::count());
        $this->assertFalse($booking->fresh()->is_paid);

        $component = Livewire::test('pages::dashboard.admin.sesi-pt.detail', ['user' => $pt])
            ->assertSeeHtml('wire:click="openApprovalPreview('.$batch->id.')"')
            ->call('openApprovalPreview', $batch->id)
            ->assertSet('showPaymentPreviewModal', true)
            ->assertSet('approvingPaymentBatchId', $batch->id)
            ->assertSee('Preview Pembayaran Sesi PT')
            ->assertSee('Konfirmasi Approve');
        $this->assertSame('pending', $batch->fresh()->status);
        $this->assertFalse($booking->fresh()->is_paid);

        $component->call('confirmPayment')->assertSee('Approved')
            ->assertSet('showPaymentPreviewModal', false)
            ->assertSet('approvingPaymentBatchId', null);
        $approvedAt = $batch->fresh()->approved_at;
        $this->travel(1)->hours();
        $component->call('approvePaymentBatch', $batch->id);
        $this->assertTrue($booking->fresh()->is_paid);
        $this->assertSame($admin->id, $batch->fresh()->paid_by);
        $this->assertTrue($approvedAt->equalTo($batch->fresh()->approved_at));
        Livewire::actingAs($pt)->test('pages::dashboard.pt.total-sesi')
            ->assertSee('Approved')->call('submitSessions');
        $this->assertSame(1, PtPaymentBatch::count());
    }

    public function test_pt_page_excludes_money_and_other_trainers_data(): void
    {
        $pt = User::factory()->create(['role' => 'pt']);
        $booking = $this->booking($pt);
        $other = $this->booking(User::factory()->create(['role' => 'pt']));
        $response = $this->actingAs($pt)->get(route('pt.total-sesi.index'));
        $response->assertOk()->assertSee($booking->member->name)
            ->assertDontSee($other->member->name)
            ->assertDontSee('Rp ')->assertDontSee('Nominal')->assertDontSee('Harga')
            ->assertDontSee('987654')->assertDontSee('price_paid')
            ->assertDontSee('Pilih Rentang Tanggal');
        $this->get(route('admin.sesi-pt.detail', $pt))->assertRedirect(route('home'));
    }

    public function test_approval_preview_uses_only_requested_sessions_and_cancel_keeps_them_pending(): void
    {
        $pt = User::factory()->create(['role' => 'pt']);
        $booking = $this->booking($pt);
        Livewire::actingAs($pt)->test('pages::dashboard.pt.total-sesi')->call('submitSessions');
        $batch = PtPaymentBatch::sole();
        $this->booking($pt);

        Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test('pages::dashboard.admin.sesi-pt.detail', ['user' => $pt])
            ->set('dateStart', '2000-01-01')->set('dateEnd', '2000-01-01')
            ->call('openApprovalPreview', $batch->id)
            ->assertSet('paymentPreviewTotalSessions', 1)
            ->assertCount('paymentPreviewRows', 1)
            ->assertSet('paymentPreviewRows.0.membership_id', $booking->membership_id)
            ->call('closePaymentPreview')
            ->assertSet('showPaymentPreviewModal', false)
            ->assertSet('approvingPaymentBatchId', null)
            ->assertCount('paymentPreviewRows', 0)
            ->call('confirmPayment');

        $this->assertSame('pending', $batch->fresh()->status);
        $this->assertFalse($booking->fresh()->is_paid);
        $this->assertSame(1, PtPaymentBatch::count());
    }

    public function test_non_admin_cannot_open_approval_preview(): void
    {
        $pt = User::factory()->create(['role' => 'pt']);
        Livewire::actingAs(User::factory()->create(['role' => 'head_coach']))
            ->test('pages::dashboard.admin.sesi-pt.detail', ['user' => $pt])
            ->call('openApprovalPreview', 1)->assertForbidden();
    }

    public function test_non_pt_cannot_access_total_sessions(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'member']))
            ->get(route('pt.total-sesi.index'))->assertRedirect();
    }

    public function test_substitute_sessions_can_be_submitted_without_exposing_primary_pt_sessions(): void
    {
        $primary = User::factory()->create(['role' => 'pt']);
        $substitute = User::factory()->create(['role' => 'pt']);
        $primaryBooking = $this->booking($primary);
        $substituteBooking = PtBooking::create([
            'membership_id' => $primaryBooking->membership_id,
            'member_id' => $primaryBooking->member_id,
            'pt_id' => $substitute->id,
            'booking_date' => today(), 'booking_time' => '10:00:00',
            'status' => 'approved', 'attendance' => 'attended', 'is_free' => false, 'is_paid' => false,
        ]);
        Livewire::actingAs($substitute)->test('pages::dashboard.pt.total-sesi')
            ->assertSee('Pengganti/Cadangan')->assertSee('Total Sesi: 1')->call('submitSessions');
        $this->assertSame([$substituteBooking->id], PtPaymentBatch::sole()->items->pluck('pt_booking_id')->all());
    }

    public function test_period_cannot_be_changed_from_client(): void
    {
        $this->expectException(PublicPropertyNotFoundException::class);
        Livewire::actingAs(User::factory()->create(['role' => 'pt']))
            ->test('pages::dashboard.pt.total-sesi')->set('dateStart', '2000-01-01');
    }

    public function test_non_admin_cannot_approve_submission(): void
    {
        $pt = User::factory()->create(['role' => 'pt']);
        $booking = $this->booking($pt);
        Livewire::actingAs($pt)->test('pages::dashboard.pt.total-sesi')->call('submitSessions');
        Livewire::actingAs(User::factory()->create(['role' => 'head_coach']))
            ->test('pages::dashboard.admin.sesi-pt.detail', ['user' => $pt])
            ->call('approvePaymentBatch', PtPaymentBatch::sole()->id)->assertForbidden();
        $this->assertFalse($booking->fresh()->is_paid);
    }

    public function test_admin_cannot_approve_batch_from_another_pt_detail(): void
    {
        $pt = User::factory()->create(['role' => 'pt']);
        $this->booking($pt);
        Livewire::actingAs($pt)->test('pages::dashboard.pt.total-sesi')->call('submitSessions');
        $this->expectException(ModelNotFoundException::class);
        Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test('pages::dashboard.admin.sesi-pt.detail', ['user' => User::factory()->create(['role' => 'pt'])])
            ->call('approvePaymentBatch', PtPaymentBatch::sole()->id)->assertNotFound();
    }

    private function booking(User $pt, array $attributes = []): PtBooking
    {
        $member = User::factory()->create(['role' => 'member']);
        $membership = Membership::create([
            'user_id' => $member->id, 'pt_id' => $pt->id, 'type' => 'pt',
            'base_price' => 987654, 'price_paid' => 987654, 'total_paid' => 987654,
            'payment_status' => 'paid', 'start_date' => today()->subMonth(),
            'pt_end_date' => today()->addMonth(), 'status' => 'active', 'total_sessions' => 10,
        ]);

        return PtBooking::create([
            'membership_id' => $membership->id, 'member_id' => $member->id, 'pt_id' => $pt->id,
            'booking_date' => today(), 'booking_time' => '09:00:00',
            'status' => 'approved', 'attendance' => 'attended', 'is_free' => false, 'is_paid' => false,
            ...$attributes,
        ]);
    }
}
