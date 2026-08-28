<?php

namespace Tests\Feature;

use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\MembershipTransaction;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MembershipPaymentProofTest extends TestCase
{
    use RefreshDatabase;

    public function test_package_cash_payment_succeeds_without_a_proof(): void
    {
        Storage::fake('public');
        [$member, $cashier, $package] = $this->packageActors();

        $this->packageForm($member, $cashier, $package)
            ->set('payment_method', 'cash')
            ->call('save')
            ->assertHasNoErrors();

        $transaction = MembershipTransaction::query()->sole();

        $this->assertSame('cash', $transaction->payment_method);
        $this->assertNull($transaction->payment_proof_path);
        $this->assertSame([], Storage::disk('public')->allFiles('membership-payment-proofs'));
    }

    #[DataProvider('nonCashPaymentMethods')]
    public function test_package_non_cash_payment_requires_a_proof(string $paymentMethod): void
    {
        Storage::fake('public');
        [$member, $cashier, $package] = $this->packageActors();

        $this->packageForm($member, $cashier, $package)
            ->set('payment_method', $paymentMethod)
            ->call('save')
            ->assertHasErrors(['payment_proof' => 'required']);

        $this->assertDatabaseCount('membership_transactions', 0);
    }

    public function test_package_valid_non_cash_proof_is_compressed_to_webp_and_recorded(): void
    {
        Storage::fake('public');
        [$member, $cashier, $package] = $this->packageActors();

        $this->packageForm($member, $cashier, $package)
            ->set('payment_method', 'transfer')
            ->set('payment_proof', UploadedFile::fake()->image('transfer-proof.jpg', 2400, 1800))
            ->call('save')
            ->assertHasNoErrors();

        $transaction = MembershipTransaction::query()->sole();

        $this->assertSame('transfer', $transaction->payment_method);
        $this->assertNotNull($transaction->payment_proof_path);
        $this->assertStringStartsWith('membership-payment-proofs/'.now()->format('Y/m').'/', $transaction->payment_proof_path);
        $this->assertStringEndsWith('.webp', $transaction->payment_proof_path);
        Storage::disk('public')->assertExists($transaction->payment_proof_path);

        $imageInfo = getimagesize(Storage::disk('public')->path($transaction->payment_proof_path));

        $this->assertIsArray($imageInfo);
        $this->assertLessThanOrEqual(1600, $imageInfo[0]);
        $this->assertLessThanOrEqual(1600, $imageInfo[1]);
        $this->assertSame(IMAGETYPE_WEBP, $imageInfo[2]);
    }

    public function test_payment_proof_rejects_non_images_forbidden_extensions_and_files_over_ten_megabytes(): void
    {
        Storage::fake('public');
        [$member, $cashier, $package] = $this->packageActors();

        $this->packageForm($member, $cashier, $package)
            ->set('payment_method', 'transfer')
            ->set('payment_proof', UploadedFile::fake()->createWithContent('proof.pdf', 'not-an-image'))
            ->call('save')
            ->assertHasErrors(['payment_proof' => 'image']);

        $jpeg = UploadedFile::fake()->image('source.jpg');
        $jpegContents = file_get_contents($jpeg->getRealPath());
        $this->assertIsString($jpegContents);

        $this->packageForm($member, $cashier, $package)
            ->set('payment_method', 'qris')
            ->set('payment_proof', UploadedFile::fake()->createWithContent('proof.gif', $jpegContents))
            ->call('save')
            ->assertHasErrors(['payment_proof' => 'extensions']);

        $this->packageForm($member, $cashier, $package)
            ->set('payment_method', 'debit')
            ->set('payment_proof', UploadedFile::fake()->image('oversized.jpg')->size(10241))
            ->call('save')
            ->assertHasErrors(['payment_proof' => 'max']);

        $this->assertDatabaseCount('membership_transactions', 0);
    }

    public function test_package_cash_only_split_does_not_require_a_proof(): void
    {
        Storage::fake('public');
        [$member, $cashier, $package] = $this->packageActors();

        $this->packageForm($member, $cashier, $package)
            ->set('is_split_payment', true)
            ->set('split_cash', 300000)
            ->call('save')
            ->assertHasNoErrors();

        $transaction = MembershipTransaction::query()->sole();

        $this->assertSame('cash', $transaction->payment_method);
        $this->assertNull($transaction->payment_proof_path);
    }

    public function test_package_split_requires_and_maps_each_positive_non_cash_proof(): void
    {
        Storage::fake('public');
        [$member, $cashier, $package] = $this->packageActors();

        $component = $this->packageForm($member, $cashier, $package)
            ->set('is_split_payment', true)
            ->set('split_cash', 100000)
            ->set('split_transfer', 100000)
            ->set('split_qris', 100000)
            ->set('split_payment_proofs.transfer', UploadedFile::fake()->image('transfer.jpg'));

        $component->call('save')
            ->assertHasErrors(['split_payment_proofs.qris' => 'required']);

        $this->assertDatabaseCount('membership_transactions', 0);

        $component->set('split_payment_proofs.qris', UploadedFile::fake()->image('qris.png'))
            ->call('save')
            ->assertHasNoErrors();

        $transactions = MembershipTransaction::query()->get()->keyBy('payment_method');

        $this->assertCount(3, $transactions);
        $this->assertNull($transactions->get('cash')->payment_proof_path);
        $this->assertNotNull($transactions->get('transfer')->payment_proof_path);
        $this->assertNotNull($transactions->get('qris')->payment_proof_path);
        $this->assertNotSame(
            $transactions->get('transfer')->payment_proof_path,
            $transactions->get('qris')->payment_proof_path,
        );
        Storage::disk('public')->assertExists($transactions->get('transfer')->payment_proof_path);
        Storage::disk('public')->assertExists($transactions->get('qris')->payment_proof_path);
    }

    public function test_renewal_cash_and_non_cash_proof_rules_are_enforced(): void
    {
        Storage::fake('public');
        $cashier = $this->createCashier();

        $cashMembership = $this->createMembership($cashier);
        $this->renewalForm($cashMembership, $cashier)
            ->set('payment_method', 'cash')
            ->call('save')
            ->assertHasNoErrors();

        $cashTransaction = MembershipTransaction::query()
            ->where('membership_id', '!=', $cashMembership->id)
            ->sole();
        $this->assertNull($cashTransaction->payment_proof_path);

        foreach (self::nonCashPaymentMethodValues() as $paymentMethod) {
            $membership = $this->createMembership($cashier);

            $this->renewalForm($membership, $cashier)
                ->set('payment_method', $paymentMethod)
                ->call('save')
                ->assertHasErrors(['payment_proof' => 'required']);
        }

        $validMembership = $this->createMembership($cashier);
        $this->renewalForm($validMembership, $cashier)
            ->set('payment_method', 'debit')
            ->set('payment_proof', UploadedFile::fake()->image('debit-proof.webp'))
            ->call('save')
            ->assertHasNoErrors();

        $debitTransaction = MembershipTransaction::query()
            ->where('payment_method', 'debit')
            ->latest('id')
            ->firstOrFail();
        Storage::disk('public')->assertExists($debitTransaction->payment_proof_path);
    }

    public function test_installment_cash_and_non_cash_proof_rules_are_enforced(): void
    {
        Storage::fake('public');
        $cashier = $this->createCashier();
        $this->actingAs($cashier);

        $cashMembership = $this->createMembership($cashier, totalPaid: 100000, paymentStatus: 'partial');
        $this->installmentForm($cashMembership)
            ->set('payment_method', 'cash')
            ->call('save')
            ->assertHasNoErrors();

        $cashTransaction = $cashMembership->transactions()->sole();
        $this->assertNull($cashTransaction->payment_proof_path);

        foreach (self::nonCashPaymentMethodValues() as $paymentMethod) {
            $membership = $this->createMembership($cashier, totalPaid: 100000, paymentStatus: 'partial');

            $this->installmentForm($membership)
                ->set('payment_method', $paymentMethod)
                ->call('save')
                ->assertHasErrors(['payment_proof' => 'required']);
        }

        $validMembership = $this->createMembership($cashier, totalPaid: 100000, paymentStatus: 'partial');
        $this->installmentForm($validMembership)
            ->set('payment_method', 'qris')
            ->set('payment_proof', UploadedFile::fake()->image('installment.png'))
            ->call('save')
            ->assertHasNoErrors();

        $qrisTransaction = $validMembership->transactions()->sole();
        Storage::disk('public')->assertExists($qrisTransaction->payment_proof_path);
    }

    public function test_installment_split_requires_and_maps_non_cash_proofs(): void
    {
        Storage::fake('public');
        $cashier = $this->createCashier();
        $this->actingAs($cashier);
        $membership = $this->createMembership($cashier, totalPaid: 100000, paymentStatus: 'partial');

        $component = $this->installmentForm($membership)
            ->set('is_split_payment', true)
            ->set('split_cash', 50000)
            ->set('split_transfer', 75000)
            ->set('split_debit', 75000)
            ->set('split_payment_proofs.transfer', UploadedFile::fake()->image('transfer.jpg'));

        $component->call('save')
            ->assertHasErrors(['split_payment_proofs.debit' => 'required']);

        $component->set('split_payment_proofs.debit', UploadedFile::fake()->image('debit.jpg'))
            ->call('save')
            ->assertHasNoErrors();

        $transactions = $membership->transactions()->get()->keyBy('payment_method');

        $this->assertCount(3, $transactions);
        $this->assertNull($transactions->get('cash')->payment_proof_path);
        Storage::disk('public')->assertExists($transactions->get('transfer')->payment_proof_path);
        Storage::disk('public')->assertExists($transactions->get('debit')->payment_proof_path);
    }

    public function test_other_income_cash_and_non_cash_proof_rules_are_enforced(): void
    {
        Storage::fake('public');
        $cashier = $this->createCashier();
        $member = $this->createMember();
        $this->actingAs($cashier);

        $this->incomeForm($member, $cashier)
            ->set('incomePaymentMethod', 'cash')
            ->call('saveIncome')
            ->assertHasNoErrors();

        $cashTransaction = MembershipTransaction::query()->sole();
        $this->assertNull($cashTransaction->payment_proof_path);

        foreach (self::nonCashPaymentMethodValues() as $paymentMethod) {
            $this->incomeForm($member, $cashier)
                ->set('incomePaymentMethod', $paymentMethod)
                ->call('saveIncome')
                ->assertHasErrors(['incomePaymentProof' => 'required']);
        }

        $this->incomeForm($member, $cashier)
            ->set('incomePaymentMethod', 'transfer')
            ->set('incomePaymentProof', UploadedFile::fake()->image('income.jpg'))
            ->call('saveIncome')
            ->assertHasNoErrors();

        $transferTransaction = MembershipTransaction::query()
            ->where('payment_method', 'transfer')
            ->sole();
        Storage::disk('public')->assertExists($transferTransaction->payment_proof_path);
    }

    public function test_edit_allows_legacy_non_cash_transaction_without_proof_when_method_is_unchanged(): void
    {
        Storage::fake('public');
        $cashier = $this->createCashier();
        $membership = $this->createMembership($cashier);
        $transaction = $this->createTransaction($membership, $cashier, 'transfer');

        Livewire::test('pages::dashboard.admin.membership.edit', ['id' => $membership->id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($transaction->fresh()->payment_proof_path);
    }

    public function test_edit_requires_a_new_proof_when_non_cash_method_changes_and_can_replace_existing_proof(): void
    {
        Storage::fake('public');
        $cashier = $this->createCashier();
        $membership = $this->createMembership($cashier);
        $transaction = $this->createTransaction($membership, $cashier, 'cash');

        $component = Livewire::test('pages::dashboard.admin.membership.edit', ['id' => $membership->id])
            ->set('transactions.0.payment_method', 'qris');

        $component->call('save')
            ->assertHasErrors(['transaction_payment_proofs.0' => 'required']);

        $component->set('transaction_payment_proofs.0', UploadedFile::fake()->image('qris.png'))
            ->call('save')
            ->assertHasNoErrors();

        $firstProofPath = $transaction->fresh()->payment_proof_path;
        $this->assertNotNull($firstProofPath);
        Storage::disk('public')->assertExists($firstProofPath);

        Livewire::test('pages::dashboard.admin.membership.edit', ['id' => $membership->id])
            ->set('transaction_payment_proofs.0', UploadedFile::fake()->image('replacement.jpg'))
            ->call('save')
            ->assertHasNoErrors();

        $replacementPath = $transaction->fresh()->payment_proof_path;
        $this->assertNotSame($firstProofPath, $replacementPath);
        Storage::disk('public')->assertMissing($firstProofPath);
        Storage::disk('public')->assertExists($replacementPath);
    }

    public function test_editing_a_transaction_to_cash_clears_and_deletes_the_old_proof_after_commit(): void
    {
        Storage::fake('public');
        $cashier = $this->createCashier();
        $membership = $this->createMembership($cashier);
        $oldProofPath = 'membership-payment-proofs/legacy/old-proof.jpg';
        Storage::disk('public')->put($oldProofPath, 'legacy-proof');
        $transaction = $this->createTransaction($membership, $cashier, 'debit', $oldProofPath);

        Livewire::test('pages::dashboard.admin.membership.edit', ['id' => $membership->id])
            ->set('transactions.0.payment_method', 'cash')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($transaction->fresh()->payment_proof_path);
        Storage::disk('public')->assertMissing($oldProofPath);
    }

    public function test_edit_removes_a_new_file_and_preserves_the_old_file_when_database_update_fails(): void
    {
        Storage::fake('public');
        $cashier = $this->createCashier();
        $membership = $this->createMembership($cashier);
        $oldProofPath = 'membership-payment-proofs/legacy/original.jpg';
        Storage::disk('public')->put($oldProofPath, 'original-proof');
        $transaction = $this->createTransaction($membership, $cashier, 'qris', $oldProofPath);

        $component = Livewire::test('pages::dashboard.admin.membership.edit', ['id' => $membership->id])
            ->set('transaction_payment_proofs.0', UploadedFile::fake()->image('new-proof.jpg'));
        $shouldFail = true;
        DB::listen(function (QueryExecuted $query) use (&$shouldFail): void {
            if (
                $shouldFail
                && str_starts_with(strtolower(ltrim($query->sql)), 'update')
                && str_contains($query->sql, 'membership_transactions')
            ) {
                $shouldFail = false;

                throw new \RuntimeException('Forced transaction failure.');
            }
        });

        $component->call('save');

        $this->assertFalse($shouldFail);
        $component->assertSee('Forced transaction failure.');

        $this->assertSame($oldProofPath, $transaction->fresh()->payment_proof_path);
        Storage::disk('public')->assertExists($oldProofPath);
        $this->assertSame([$oldProofPath], Storage::disk('public')->allFiles('membership-payment-proofs'));
    }

    public function test_sales_table_displays_a_safe_public_proof_link_and_dash_without_a_proof(): void
    {
        Storage::fake('public');
        $cashier = $this->createCashier();
        $member = $this->createMember();
        $this->actingAs($cashier);
        $proofPath = 'membership-payment-proofs/'.now()->format('Y/m').'/table-proof.jpg';

        $proofTransaction = MembershipTransaction::create([
            'invoice_number' => 'INV-TABLE-PROOF',
            'membership_id' => null,
            'user_id' => $member->id,
            'admin_id' => $cashier->id,
            'shift' => $cashier->shift,
            'transaction_type' => 'Pemasukan Lain',
            'package_name' => 'Merchandise',
            'amount' => 50000,
            'payment_method' => 'transfer',
            'payment_proof_path' => $proofPath,
            'payment_date' => now(),
            'notes' => 'Ada bukti',
        ]);
        MembershipTransaction::create([
            'invoice_number' => 'INV-TABLE-CASH',
            'membership_id' => null,
            'user_id' => $member->id,
            'admin_id' => $cashier->id,
            'shift' => $cashier->shift,
            'transaction_type' => 'Pemasukan Lain',
            'package_name' => 'Cash Item',
            'amount' => 25000,
            'payment_method' => 'cash',
            'payment_proof_path' => null,
            'payment_date' => now(),
            'notes' => 'Tanpa bukti',
        ]);

        Livewire::test('pages::dashboard.admin.penjualan.index')
            ->assertSee('Aksi')
            ->assertSee(asset('storage/'.$proofPath), escape: false)
            ->assertSeeHtml('rel="noopener noreferrer"')
            ->assertSeeHtml('data-testid="sales-payment-proof-link-'.$proofTransaction->id.'"')
            ->assertSeeHtml('title="Lihat bukti pembayaran INV-TABLE-PROOF"')
            ->assertSeeHtml('aria-label="Lihat bukti pembayaran INV-TABLE-PROOF"')
            ->assertSee('Cash Item');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonCashPaymentMethods(): array
    {
        return [
            'transfer' => ['transfer'],
            'qris' => ['qris'],
            'debit' => ['debit'],
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function nonCashPaymentMethodValues(): array
    {
        return array_map(
            static fn (array $parameters): string => $parameters[0],
            array_values(self::nonCashPaymentMethods()),
        );
    }

    /**
     * @return array{User, User, GymPackage}
     */
    private function packageActors(): array
    {
        $member = $this->createMember();
        $cashier = $this->createCashier();
        $this->actingAs($cashier);

        return [$member, $cashier, $this->createGymPackage()];
    }

    private function packageForm(User $member, User $cashier, GymPackage $package): Testable
    {
        return Livewire::withQueryParams(['users' => [$member->id]])
            ->test('pages::dashboard.admin.membership.paket')
            ->set('registration_type', 'membership')
            ->set('gym_package_id', $package->id)
            ->set('admin_id', $cashier->id)
            ->set('follow_up_id', $cashier->id)
            ->set('follow_up_id_two', $cashier->id)
            ->set('transaction_type', 'MEMBERSHIP BARU')
            ->set('package_name', 'PAKET GYM')
            ->set('notes', 'Catatan bukti pembayaran');
    }

    private function renewalForm(Membership $membership, User $cashier): Testable
    {
        return Livewire::test('pages::dashboard.admin.renew.create', ['id' => $membership->id])
            ->set('admin_id', $cashier->id)
            ->set('follow_up_id', $cashier->id)
            ->set('follow_up_id_two', $cashier->id)
            ->set('transaction_type', 'RENEW MEMBERSHIP')
            ->set('package_name', 'PAKET GYM')
            ->set('notes', 'Catatan renewal');
    }

    private function installmentForm(Membership $membership): Testable
    {
        return Livewire::test('pages::dashboard.admin.cicilan.pay', ['membership' => $membership])
            ->set('amount_paid', 200000)
            ->set('transaction_type', 'PELUNASAN')
            ->set('notes', 'Catatan cicilan');
    }

    private function incomeForm(User $member, User $cashier): Testable
    {
        return Livewire::test('pages::dashboard.admin.penjualan.index')
            ->set('selectedUserId', $member->id)
            ->set('adminId', $cashier->id)
            ->set('incomeCategory', 'Merchandise')
            ->set('incomeAmount', 50000);
    }

    private function createMembership(
        User $cashier,
        int $totalPaid = 300000,
        string $paymentStatus = 'paid',
    ): Membership {
        $member = $this->createMember();
        $package = $this->createGymPackage();
        $membership = Membership::create([
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
            'total_paid' => $totalPaid,
            'payment_status' => $paymentStatus,
            'start_date' => now()->toDateString(),
            'membership_end_date' => now()->addMonth()->toDateString(),
            'status' => $paymentStatus === 'paid' ? 'active' : 'pending',
            'is_active' => $paymentStatus === 'paid',
            'notes' => 'Membership test',
            'transaction_type' => 'MEMBERSHIP BARU',
            'package_name' => 'PAKET GYM',
        ]);
        $membership->members()->attach($member);

        return $membership;
    }

    private function createTransaction(
        Membership $membership,
        User $cashier,
        string $paymentMethod,
        ?string $paymentProofPath = null,
    ): MembershipTransaction {
        return MembershipTransaction::create([
            'invoice_number' => 'INV-'.Str::upper(Str::random(16)),
            'membership_id' => $membership->id,
            'user_id' => $membership->user_id,
            'admin_id' => $cashier->id,
            'shift' => $cashier->shift,
            'follow_up_id' => $cashier->id,
            'follow_up_id_two' => $cashier->id,
            'transaction_type' => 'MEMBERSHIP BARU',
            'package_name' => 'PAKET GYM',
            'amount' => 300000,
            'payment_method' => $paymentMethod,
            'payment_proof_path' => $paymentProofPath,
            'payment_date' => now(),
            'start_date' => now(),
            'end_date' => now()->addMonth(),
            'notes' => 'Catatan transaksi',
        ]);
    }

    private function createGymPackage(): GymPackage
    {
        return GymPackage::create([
            'type' => 'gym',
            'name' => 'Paket Gym '.Str::random(6),
            'category' => 'single',
            'max_members' => 1,
            'price' => 300000,
            'normal_price' => 300000,
            'net_price' => 300000,
            'unrecommended_price' => 300000,
            'discount' => 0,
            'is_active' => true,
        ]);
    }

    private function createCashier(): User
    {
        return User::factory()->create([
            'role' => 'kasir_gym',
            'shift' => 'Pagi',
            'is_active' => true,
            'age' => 30,
            'gender' => 'Laki-laki',
            'phone' => fake()->unique()->numerify('08##########'),
        ]);
    }

    private function createMember(): User
    {
        return User::factory()->create([
            'role' => 'member',
            'is_active' => true,
            'photo' => 'profile-photos/existing.webp',
            'age' => 25,
            'gender' => 'Perempuan',
            'phone' => fake()->unique()->numerify('08##########'),
        ]);
    }
}
