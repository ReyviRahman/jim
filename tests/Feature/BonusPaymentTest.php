<?php

namespace Tests\Feature;

use App\Models\BonusPayment;
use App\Models\Membership;
use App\Models\SalesKonsultan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BonusPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_sees_button_below_bonus_and_can_open_payment_modal(): void
    {
        $admin = $this->createUser('admin');
        $headCoach = User::factory()->headCoach()->create();
        $cashier = $this->createUser('kasir_gym');
        $sales = $this->createUser('sales');
        $member = $this->createUser('member', 'Member Admin');
        $membership = $this->createMembership($member, $admin, $sales, 1_000_000);
        $this->createTransaction($membership, $member, $admin, $sales, '2026-08-10');
        $this->createBonusRange();

        $this->actingAs($admin);

        Livewire::test('pages::dashboard.admin.rekap-bonus.detail', ['user' => $sales])
            ->call('setDateRange', '2026-08-01 to 2026-08-31')
            ->assertSee('Rp 20.000')
            ->assertSeeHtml('<span wire:loading.remove wire:target="openBonusPaymentModal">Bayar</span>')
            ->assertSeeHtml('<span class="block whitespace-nowrap text-[10px] text-gray-400">')
            ->assertSeeHtml('colspan="5" class="block px-3 pt-3 text-left text-gray-600')
            ->assertSeeHtml('colspan="3" class="block break-words px-3 pb-3 text-left text-blue-700')
            ->assertSeeHtml('colspan="3" class="hidden px-1.5 py-2 xl:table-cell"')
            ->assertSeeHtml('inline-flex w-full items-center justify-center whitespace-nowrap')
            ->assertSeeHtml('rounded-md bg-emerald-600 px-10 py-2')
            ->call('openBonusPaymentModal')
            ->assertSet('showBonusPaymentModal', true)
            ->assertSee('Pembayaran Bonus '.$sales->name);

        foreach ([$headCoach, $cashier] as $unauthorizedUser) {
            $this->actingAs($unauthorizedUser);

            Livewire::test('pages::dashboard.admin.rekap-bonus.detail', ['user' => $sales])
                ->call('setDateRange', '2026-08-01 to 2026-08-31')
                ->assertDontSeeHtml('wire:click="openBonusPaymentModal"');

            Livewire::test('pages::dashboard.admin.rekap-bonus.detail', ['user' => $sales])
                ->call('openBonusPaymentModal')
                ->assertForbidden();

            Livewire::test('pages::dashboard.admin.rekap-bonus.detail', ['user' => $sales])
                ->call('confirmBonusPayment')
                ->assertForbidden();
        }

        $this->assertDatabaseCount('bonus_payments', 0);
    }

    public function test_modal_uses_active_date_and_page_search_filters_and_has_required_columns(): void
    {
        $admin = $this->createUser('admin');
        $sales = $this->createUser('sales');
        $alpha = $this->createUser('member', 'Alpha Member');
        $beta = $this->createUser('member', 'Beta Member');
        $augustAlpha = $this->createUser('member', 'Alpha Agustus');

        $alphaMembership = $this->createMembership($alpha, $admin, $sales, 1_000_000, 'Paket Alpha');
        $betaMembership = $this->createMembership($beta, $admin, $sales, 2_000_000, 'Paket Beta');
        $augustMembership = $this->createMembership($augustAlpha, $admin, $sales, 3_000_000, 'Paket Agustus');
        $this->createTransaction($alphaMembership, $alpha, $admin, $sales, '2026-07-10');
        $this->createTransaction($betaMembership, $beta, $admin, $sales, '2026-07-11');
        $this->createTransaction($augustMembership, $augustAlpha, $admin, $sales, '2026-08-10');
        $this->createBonusRange();
        $this->actingAs($admin);

        $component = Livewire::test('pages::dashboard.admin.rekap-bonus.detail', ['user' => $sales])
            ->call('setDateRange', '2026-07-01 to 2026-07-31')
            ->set('search', 'Alpha')
            ->call('openBonusPaymentModal')
            ->assertSet('showBonusPaymentModal', true)
            ->assertSet('bonusPaymentDateStart', '2026-07-01')
            ->assertSet('bonusPaymentDateEnd', '2026-07-31')
            ->assertSet('bonusPaymentPageSearch', 'Alpha')
            ->assertSeeInOrder([
                'Nama Member',
                'Paket Membership',
                'Nominal',
                'Nominal Akhir',
                'Tanggal Bayar',
            ])
            ->assertSee('Alpha Member')
            ->assertSee('Paket Alpha')
            ->assertSee('10 July 2026')
            ->assertDontSee('Beta Member')
            ->assertDontSee('Alpha Agustus');

        $this->assertCount(1, $component->get('bonusPaymentRows'));
        $this->assertSame($alphaMembership->id, $component->get('bonusPaymentRows')[0]['membership_id']);
    }

    public function test_modal_search_only_changes_visible_rows_not_snapshot_or_totals(): void
    {
        [$admin, $sales, $firstMembership, $secondMembership] = $this->createTwoMembershipScenario();

        $component = Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.rekap-bonus.detail', ['user' => $sales])
            ->call('setDateRange', '2026-08-01 to 2026-08-31')
            ->call('openBonusPaymentModal');

        $this->assertCount(2, $component->get('bonusPaymentRows'));
        $this->assertSame(3_000_000.0, $component->get('bonusPaymentTotalNominalAkhir'));

        $component
            ->set('bonusPaymentSearch', 'Pertama')
            ->assertSee('Member Pertama')
            ->assertSet('bonusPaymentTotalNominalAkhir', 3_000_000.0);

        $filteredRows = $component->instance()->filteredBonusPaymentRows();
        $this->assertCount(1, $filteredRows);
        $this->assertSame('Member Pertama', $filteredRows[0]['member_name']);

        $component->call('confirmBonusPayment')
            ->assertSet('showBonusPaymentModal', false);

        $payment = BonusPayment::query()->sole();
        $this->assertCount(2, $payment->items);
        $this->assertEqualsCanonicalizing(
            [$firstMembership->id, $secondMembership->id],
            $payment->items->pluck('membership_id')->all()
        );
    }

    public function test_deduction_validation_net_amount_and_terbilang_are_reactive(): void
    {
        [$admin, $sales] = $this->createTwoMembershipScenario();

        $component = Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.rekap-bonus.detail', ['user' => $sales])
            ->call('setDateRange', '2026-08-01 to 2026-08-31')
            ->call('openBonusPaymentModal')
            ->assertSee('Total Keseluruhan:')
            ->assertSee('Rp 3.000.000')
            ->assertSee('Bonus (2%)')
            ->assertSee('Rp 60.000')
            ->set('nominalPotongan', 'bukan-angka')
            ->call('confirmBonusPayment')
            ->assertHasErrors(['nominalPotongan' => 'numeric'])
            ->set('nominalPotongan', '10000')
            ->set('keteranganPotongan', '')
            ->assertSee('BERSIH DITERIMA: Rp 50.000')
            ->assertSee('Terbilang: lima puluh ribu rupiah')
            ->call('confirmBonusPayment')
            ->assertHasErrors(['keteranganPotongan' => 'required'])
            ->set('nominalPotongan', '60001')
            ->set('keteranganPotongan', 'Potongan')
            ->call('confirmBonusPayment')
            ->assertHasErrors(['nominalPotongan' => 'max']);

        $this->assertDatabaseCount('bonus_payments', 0);
    }

    public function test_confirmation_persists_header_and_item_snapshots_atomically(): void
    {
        [$admin, $sales, $firstMembership, $secondMembership] = $this->createTwoMembershipScenario();

        Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.rekap-bonus.detail', ['user' => $sales])
            ->call('setDateRange', '2026-08-01 to 2026-08-31')
            ->set('search', 'Member')
            ->call('setPage', 2, 'bonus-payments-page')
            ->assertSet('paginators.bonus-payments-page', 2)
            ->call('openBonusPaymentModal')
            ->set('nominalPotongan', '10000')
            ->set('keteranganPotongan', 'Keterlambatan laporan')
            ->call('confirmBonusPayment')
            ->assertHasNoErrors()
            ->assertSet('showBonusPaymentModal', false)
            ->assertSet('paginators.bonus-payments-page', 1)
            ->assertSee('Pembayaran bonus #')
            ->assertSee('berhasil disimpan.');

        $payment = BonusPayment::query()->with('items')->sole();

        $this->assertSame($sales->id, $payment->staff_user_id);
        $this->assertSame($admin->id, $payment->paid_by);
        $this->assertSame('2026-08-01', $payment->date_start->toDateString());
        $this->assertSame('2026-08-31', $payment->date_end->toDateString());
        $this->assertSame('Member', $payment->search_filter);
        $this->assertSame('3000000.00', $payment->total_nominal_akhir);
        $this->assertSame('2.00', $payment->bonus_percentage);
        $this->assertSame('60000.00', $payment->bonus_amount);
        $this->assertSame('10000.00', $payment->potongan);
        $this->assertSame('50000.00', $payment->net_amount);
        $this->assertSame('Keterlambatan laporan', $payment->keterangan_potongan);
        $this->assertNotNull($payment->paid_at);
        $this->assertCount(2, $payment->items);

        $firstItem = $payment->items->firstWhere('membership_id', $firstMembership->id);
        $this->assertSame('Member Pertama', $firstItem->member_name);
        $this->assertSame('MEMBERSHIP Paket Pertama', $firstItem->package_name);
        $this->assertSame('1000000.00', $firstItem->nominal);
        $this->assertSame('1000000.00', $firstItem->nominal_akhir);
        $this->assertSame('2026-08-10', $firstItem->payment_date->toDateString());
        $this->assertNotNull($payment->items->firstWhere('membership_id', $secondMembership->id));
    }

    public function test_repeated_confirmation_intentionally_creates_new_payment_batch(): void
    {
        [$admin, $sales] = $this->createTwoMembershipScenario();

        $component = Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.rekap-bonus.detail', ['user' => $sales])
            ->call('setDateRange', '2026-08-01 to 2026-08-31');

        $component->call('openBonusPaymentModal')->call('confirmBonusPayment');
        $component->call('openBonusPaymentModal')->call('confirmBonusPayment');

        $this->assertDatabaseCount('bonus_payments', 2);
        $this->assertDatabaseCount('bonus_payment_items', 4);
    }

    public function test_bonus_payment_history_is_visible_to_admin_and_head_coach_only(): void
    {
        $admin = $this->createUser('admin', 'Admin Pembayar');
        $headCoach = User::factory()->headCoach()->create();
        $cashier = $this->createUser('kasir_gym');
        $sales = $this->createUser('sales');
        $payment = $this->createBonusPayment($sales, $admin, [
            'total_nominal_akhir' => 1_500_000,
            'bonus_amount' => 30_000,
            'potongan' => 5_000,
            'net_amount' => 25_000,
        ]);

        Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.rekap-bonus.detail', ['user' => $sales])
            ->assertSee('Riwayat Pembayaran Bonus')
            ->assertSee('#'.$payment->id)
            ->assertSee('Rp 30.000')
            ->assertSee('Rp 5.000')
            ->assertSee('Rp 25.000')
            ->assertSee('Admin Pembayar')
            ->assertSee('Download PDF')
            ->assertSee('Hapus')
            ->call('openBonusPaymentDetail', $payment->id)
            ->assertSet('showBonusPaymentDetailModal', true)
            ->assertSee('Simpan Potongan');

        Livewire::actingAs($headCoach)
            ->test('pages::dashboard.admin.rekap-bonus.detail', ['user' => $sales])
            ->assertSee('Riwayat Pembayaran Bonus')
            ->call('openBonusPaymentDetail', $payment->id)
            ->assertSet('showBonusPaymentDetailModal', true)
            ->assertDontSee('Simpan Potongan')
            ->assertSee('Download PDF')
            ->assertDontSee('Hapus')
            ->assertSee('Keterangan Potongan');

        Livewire::actingAs($cashier)
            ->test('pages::dashboard.admin.rekap-bonus.detail', ['user' => $sales])
            ->assertDontSee('Riwayat Pembayaran Bonus');

        Livewire::actingAs($cashier)
            ->test('pages::dashboard.admin.rekap-bonus.detail', ['user' => $sales])
            ->call('openBonusPaymentDetail', $payment->id)
            ->assertForbidden();
    }

    public function test_history_and_payment_item_tables_fit_without_horizontal_scrolling(): void
    {
        $admin = $this->createUser('admin');
        $sales = $this->createUser('sales');
        $payment = $this->createBonusPayment($sales, $admin, itemCount: 2);

        Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.rekap-bonus.detail', ['user' => $sales])
            ->assertDontSeeHtml('overflow-x-auto')
            ->assertDontSeeHtml('min-w-[')
            ->assertSeeHtml('wire:confirm="Pembayaran bonus #'.$payment->id.' akan dihapus permanen beserta seluruh detailnya. Lanjutkan?"')
            ->assertDontSeeHtml('wire:confirm.prompt')
            ->call('openBonusPaymentDetail', $payment->id)
            ->assertDontSeeHtml('overflow-x-auto')
            ->assertDontSeeHtml('min-w-[')
            ->assertSee('Nama Member')
            ->assertSee('Paket Membership');
    }

    public function test_admin_and_head_coach_can_download_scoped_payment_pdf(): void
    {
        $admin = $this->createUser('admin');
        $headCoach = User::factory()->headCoach()->create();
        $cashier = $this->createUser('kasir_gym');
        $sales = $this->createUser('sales', 'Sales Bonus');
        $otherSales = $this->createUser('sales');
        $payment = $this->createBonusPayment($sales, $admin, [
            'search_filter' => 'Member Snapshot',
            'potongan' => 5_000,
            'keterangan_potongan' => 'Potongan administrasi',
            'net_amount' => 15_000,
        ], 26);
        $otherPayment = $this->createBonusPayment($otherSales, $admin);
        $fileName = 'Pembayaran_Bonus_sales_bonus_Batch_'.$payment->id.'.pdf';

        foreach ([$admin, $headCoach] as $viewer) {
            $response = $this->actingAs($viewer)
                ->get(route('admin.rekap-bonus.payment.pdf', [
                    'user' => $sales,
                    'paymentId' => $payment->id,
                ]));

            $response->assertOk()
                ->assertDownload($fileName)
                ->assertHeader('content-type', 'application/pdf')
                ->assertHeader('cache-control');
            $this->assertStringStartsWith('%PDF', $response->getContent());
            $this->assertStringContainsString('no-store', (string) $response->headers->get('cache-control'));
        }

        $this->actingAs($cashier)
            ->get(route('admin.rekap-bonus.payment.pdf', [
                'user' => $sales,
                'paymentId' => $payment->id,
            ]))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.rekap-bonus.payment.pdf', [
                'user' => $sales,
                'paymentId' => $otherPayment->id,
            ]))
            ->assertNotFound();

        auth()->logout();
        $this->get(route('admin.rekap-bonus.payment.pdf', [
            'user' => $sales,
            'paymentId' => $payment->id,
        ]))->assertRedirect(route('login'));

        $html = view('pages.dashboard.admin.rekap-bonus.payment-pdf', [
            'payment' => $payment->load(['staffUser', 'paidBy', 'items']),
            'terbilang' => 'lima belas ribu',
        ])->render();

        $this->assertStringContainsString('DETAIL PEMBAYARAN BONUS #'.$payment->id, $html);
        $this->assertStringContainsString('Snapshot Member 26', $html);
        $this->assertStringContainsString('Member Snapshot', $html);
        $this->assertStringContainsString('Potongan administrasi', $html);
        $this->assertStringContainsString('BERSIH DITERIMA', $html);
        $this->assertStringContainsString('Rp 15.000', $html);
        $this->assertStringContainsString('Terbilang: lima belas ribu rupiah', $html);
    }

    public function test_admin_can_delete_scoped_payment_and_items_permanently(): void
    {
        $admin = $this->createUser('admin');
        $sales = $this->createUser('sales');
        $otherSales = $this->createUser('sales');
        $payment = $this->createBonusPayment($sales, $admin, itemCount: 3);
        $otherPayment = $this->createBonusPayment($otherSales, $admin, itemCount: 2);
        $itemIds = $payment->items()->pluck('id')->all();

        Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.rekap-bonus.detail', ['user' => $sales])
            ->call('setPage', 2, 'bonus-payments-page')
            ->call('openBonusPaymentDetail', $payment->id)
            ->assertSet('showBonusPaymentDetailModal', true)
            ->call('deleteBonusPayment', $payment->id)
            ->assertSet('showBonusPaymentDetailModal', false)
            ->assertSet('selectedBonusPaymentId', null)
            ->assertSet('paginators.bonus-payments-page', 1)
            ->assertSee('Pembayaran bonus #'.$payment->id.' beserta detailnya berhasil dihapus permanen.');

        $this->assertDatabaseMissing('bonus_payments', ['id' => $payment->id]);
        foreach ($itemIds as $itemId) {
            $this->assertDatabaseMissing('bonus_payment_items', ['id' => $itemId]);
        }
        $this->assertDatabaseHas('bonus_payments', ['id' => $otherPayment->id]);
        $this->assertDatabaseCount('bonus_payment_items', 2);

        $this->actingAs($admin)
            ->get(route('admin.rekap-bonus.payment.pdf', [
                'user' => $sales,
                'paymentId' => $payment->id,
            ]))
            ->assertNotFound();
    }

    public function test_delete_rejects_non_admin_and_cross_staff_payment_ids(): void
    {
        $admin = $this->createUser('admin');
        $headCoach = User::factory()->headCoach()->create();
        $cashier = $this->createUser('kasir_gym');
        $sales = $this->createUser('sales');
        $otherSales = $this->createUser('sales');
        $payment = $this->createBonusPayment($sales, $admin);
        $otherPayment = $this->createBonusPayment($otherSales, $admin);

        foreach ([$headCoach, $cashier] as $unauthorizedUser) {
            Livewire::actingAs($unauthorizedUser)
                ->test('pages::dashboard.admin.rekap-bonus.detail', ['user' => $sales])
                ->call('deleteBonusPayment', $payment->id)
                ->assertForbidden();
        }

        Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.rekap-bonus.detail', ['user' => $sales])
            ->call('deleteBonusPayment', $otherPayment->id)
            ->assertNotFound();

        $this->assertDatabaseHas('bonus_payments', ['id' => $payment->id]);
        $this->assertDatabaseHas('bonus_payments', ['id' => $otherPayment->id]);
    }

    public function test_history_is_scoped_ordered_paginated_and_independent_from_main_filters(): void
    {
        $admin = $this->createUser('admin');
        $sales = $this->createUser('sales');
        $otherSales = $this->createUser('sales');
        $payments = collect();

        for ($index = 0; $index < 12; $index++) {
            $payments->push($this->createBonusPayment($sales, $admin, [
                'paid_at' => Carbon::parse('2026-08-22 12:00:00')->subDays($index),
            ]));
        }

        $otherPayment = $this->createBonusPayment($otherSales, $admin, [
            'paid_at' => '2026-08-23 12:00:00',
        ]);

        $component = Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.rekap-bonus.detail', ['user' => $sales])
            ->call('setDateRange', '2025-01-01 to 2025-01-31')
            ->set('search', 'Tidak Ada Member')
            ->assertSee('#'.$payments->first()->id)
            ->assertDontSee('#'.$otherPayment->id);

        $history = $component->instance()->bonusPaymentHistory();
        $this->assertSame(12, $history->total());
        $this->assertCount(10, $history->items());
        $this->assertSame($payments->first()->id, $history->items()[0]->id);

        $component
            ->call('setPage', 2, 'bonus-payments-page')
            ->assertSet('paginators.bonus-payments-page', 2)
            ->set('search', 'Filter Utama Berubah')
            ->assertSet('paginators.bonus-payments-page', 2)
            ->assertSet('paginators.page', 1);
    }

    public function test_detail_modal_uses_paginated_searchable_snapshots_and_blocks_cross_staff_ids(): void
    {
        $admin = $this->createUser('admin');
        $sales = $this->createUser('sales');
        $otherSales = $this->createUser('sales');
        $payment = $this->createBonusPayment($sales, $admin, [
            'search_filter' => 'Member Agustus',
            'total_nominal_akhir' => 2_000_000,
            'bonus_amount' => 40_000,
            'potongan' => 10_000,
            'keterangan_potongan' => 'Potongan seragam',
            'net_amount' => 30_000,
        ], 26);
        $otherPayment = $this->createBonusPayment($otherSales, $admin);

        $component = Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.rekap-bonus.detail', ['user' => $sales])
            ->call('openBonusPaymentDetail', $payment->id)
            ->assertSet('showBonusPaymentDetailModal', true)
            ->assertSee('Detail Pembayaran Bonus #'.$payment->id)
            ->assertSee('Member Agustus')
            ->assertSee('Snapshot Member 01')
            ->assertSee('Rp 2.000.000')
            ->assertSee('Rp 40.000')
            ->assertSee('Rp 30.000')
            ->assertSee('tiga puluh ribu rupiah');

        $detailItems = $component->instance()->bonusPaymentDetailItems();
        $this->assertSame(26, $detailItems->total());
        $this->assertCount(25, $detailItems->items());

        $component->set('bonusPaymentDetailSearch', 'Snapshot Member 26');
        $filteredItems = $component->instance()->bonusPaymentDetailItems();
        $this->assertSame(1, $filteredItems->total());
        $component
            ->assertSee('Snapshot Member 26')
            ->assertSee('Rp 2.000.000')
            ->assertSee('Rp 30.000');

        Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.rekap-bonus.detail', ['user' => $sales])
            ->call('openBonusPaymentDetail', $otherPayment->id)
            ->assertNotFound();
    }

    public function test_admin_can_edit_deduction_without_changing_payment_identity_or_items(): void
    {
        Carbon::setTestNow('2026-08-22 10:00:00');
        $admin = $this->createUser('admin');
        $sales = $this->createUser('sales');
        $payment = $this->createBonusPayment($sales, $admin, [
            'bonus_amount' => 50_000,
            'potongan' => 5_000,
            'keterangan_potongan' => 'Potongan awal',
            'net_amount' => 45_000,
            'paid_at' => '2026-08-20 09:00:00',
        ], 2);
        $originalPaidAt = $payment->paid_at->toDateTimeString();
        $originalPaidBy = $payment->paid_by;
        $originalItemIds = $payment->items()->pluck('id')->all();

        Carbon::setTestNow('2026-08-22 11:00:00');

        Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.rekap-bonus.detail', ['user' => $sales])
            ->call('openBonusPaymentDetail', $payment->id)
            ->set('detailNominalPotongan', '12000')
            ->set('detailKeteranganPotongan', 'Potongan diperbarui')
            ->call('updateBonusPaymentDeduction')
            ->assertHasNoErrors()
            ->assertSet('showBonusPaymentDetailModal', true)
            ->assertSee('Potongan pembayaran bonus #'.$payment->id.' berhasil diperbarui.')
            ->assertSee('Rp 38.000')
            ->assertSee('tiga puluh delapan ribu rupiah');

        $payment->refresh();
        $this->assertSame('12000.00', $payment->potongan);
        $this->assertSame('Potongan diperbarui', $payment->keterangan_potongan);
        $this->assertSame('38000.00', $payment->net_amount);
        $this->assertSame($originalPaidAt, $payment->paid_at->toDateTimeString());
        $this->assertSame($originalPaidBy, $payment->paid_by);
        $this->assertSame($originalItemIds, $payment->items()->pluck('id')->all());
        $this->assertSame('2026-08-22 11:00:00', $payment->updated_at->toDateTimeString());

        Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.rekap-bonus.detail', ['user' => $sales])
            ->call('openBonusPaymentDetail', $payment->id)
            ->set('detailNominalPotongan', '0')
            ->set('detailKeteranganPotongan', 'Harus dikosongkan')
            ->call('updateBonusPaymentDeduction')
            ->assertHasNoErrors();

        $payment->refresh();
        $this->assertSame('0.00', $payment->potongan);
        $this->assertNull($payment->keterangan_potongan);
        $this->assertSame('50000.00', $payment->net_amount);

        Carbon::setTestNow();
    }

    public function test_deduction_edit_validation_and_head_coach_authorization(): void
    {
        $admin = $this->createUser('admin');
        $headCoach = User::factory()->headCoach()->create();
        $sales = $this->createUser('sales');
        $payment = $this->createBonusPayment($sales, $admin, ['bonus_amount' => 20_000]);

        $component = Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.rekap-bonus.detail', ['user' => $sales])
            ->call('openBonusPaymentDetail', $payment->id)
            ->set('detailNominalPotongan', '-1')
            ->call('updateBonusPaymentDeduction')
            ->assertHasErrors(['detailNominalPotongan' => 'min'])
            ->set('detailNominalPotongan', 'bukan-angka')
            ->call('updateBonusPaymentDeduction')
            ->assertHasErrors(['detailNominalPotongan' => 'numeric'])
            ->set('detailNominalPotongan', '20001')
            ->call('updateBonusPaymentDeduction')
            ->assertHasErrors(['detailNominalPotongan' => 'max'])
            ->set('detailNominalPotongan', '1000')
            ->set('detailKeteranganPotongan', '   ')
            ->call('updateBonusPaymentDeduction')
            ->assertHasErrors(['detailKeteranganPotongan' => 'required']);

        $this->assertSame('0.00', $payment->fresh()->potongan);

        Livewire::actingAs($headCoach)
            ->test('pages::dashboard.admin.rekap-bonus.detail', ['user' => $sales])
            ->call('openBonusPaymentDetail', $payment->id)
            ->call('updateBonusPaymentDeduction')
            ->assertForbidden();
    }

    public function test_empty_or_zero_bonus_snapshot_cannot_be_confirmed(): void
    {
        $admin = $this->createUser('admin');
        $sales = $this->createUser('sales');
        $this->actingAs($admin);

        Livewire::test('pages::dashboard.admin.rekap-bonus.detail', ['user' => $sales])
            ->call('setDateRange', '2026-08-01 to 2026-08-31')
            ->call('openBonusPaymentModal')
            ->assertSet('showBonusPaymentModal', false)
            ->assertSee('Tidak ada bonus yang dapat dibayar untuk filter ini.');

        Livewire::test('pages::dashboard.admin.rekap-bonus.detail', ['user' => $sales])
            ->call('confirmBonusPayment')
            ->assertStatus(422);

        $this->assertDatabaseCount('bonus_payments', 0);
    }

    /** @return array{User, User, Membership, Membership} */
    private function createTwoMembershipScenario(): array
    {
        $admin = $this->createUser('admin');
        $sales = $this->createUser('sales');
        $firstMember = $this->createUser('member', 'Member Pertama');
        $secondMember = $this->createUser('member', 'Member Kedua');
        $firstMembership = $this->createMembership($firstMember, $admin, $sales, 1_000_000, 'Paket Pertama');
        $secondMembership = $this->createMembership($secondMember, $admin, $sales, 2_000_000, 'Paket Kedua');
        $this->createTransaction($firstMembership, $firstMember, $admin, $sales, '2026-08-10');
        $this->createTransaction($secondMembership, $secondMember, $admin, $sales, '2026-08-11');
        $this->createBonusRange();

        return [$admin, $sales, $firstMembership, $secondMembership];
    }

    private function createMembership(
        User $member,
        User $admin,
        User $sales,
        int $amount,
        string $packageName = 'Paket Membership'
    ): Membership {
        return Membership::create([
            'user_id' => $member->id,
            'type' => 'membership',
            'admin_id' => $admin->id,
            'follow_up_id' => $sales->id,
            'base_price' => $amount,
            'normal_price' => $amount,
            'net_price' => $amount,
            'price_paid' => $amount,
            'total_paid' => $amount,
            'payment_status' => 'paid',
            'start_date' => '2026-08-01',
            'status' => 'active',
            'transaction_type' => 'MEMBERSHIP',
            'package_name' => $packageName,
        ]);
    }

    private function createTransaction(
        Membership $membership,
        User $member,
        User $admin,
        User $sales,
        string $paymentDate
    ): void {
        $membership->transactions()->create([
            'invoice_number' => 'INV-BONUS-'.$membership->id.'-'.$paymentDate,
            'user_id' => $member->id,
            'admin_id' => $admin->id,
            'follow_up_id' => $sales->id,
            'transaction_type' => 'MEMBERSHIP',
            'package_name' => $membership->package_name,
            'amount' => $membership->total_paid,
            'payment_method' => 'cash',
            'payment_date' => $paymentDate,
        ]);
    }

    private function createBonusRange(): void
    {
        SalesKonsultan::create([
            'rentang_satu' => '0',
            'rentang_dua' => '60000000',
            'persen' => 2,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function createBonusPayment(User $staff, User $admin, array $overrides = [], int $itemCount = 1): BonusPayment
    {
        $payment = BonusPayment::create([
            'staff_user_id' => $staff->id,
            'date_start' => '2026-08-01',
            'date_end' => '2026-08-31',
            'search_filter' => null,
            'total_nominal_akhir' => 1_000_000,
            'bonus_percentage' => 2,
            'range_start' => '0',
            'range_end' => '60000000',
            'bonus_amount' => 20_000,
            'potongan' => 0,
            'keterangan_potongan' => null,
            'net_amount' => 20_000,
            'paid_by' => $admin->id,
            'paid_at' => '2026-08-22 09:00:00',
            ...$overrides,
        ]);

        for ($index = 1; $index <= $itemCount; $index++) {
            $payment->items()->create([
                'membership_id' => null,
                'member_name' => 'Snapshot Member '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'package_name' => 'Paket Snapshot '.$index,
                'nominal' => 100_000,
                'nominal_akhir' => 100_000,
                'payment_date' => '2026-08-'.str_pad((string) min($index, 28), 2, '0', STR_PAD_LEFT),
            ]);
        }

        return $payment;
    }

    private function createUser(string $role, ?string $name = null): User
    {
        return User::factory()->create([
            'name' => $name ?? fake()->name(),
            'age' => 30,
            'gender' => 'Laki-laki',
            'phone' => fake()->unique()->numerify('08##########'),
            'role' => $role,
        ]);
    }
}
