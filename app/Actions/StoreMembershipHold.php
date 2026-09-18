<?php

namespace App\Actions;

use App\Models\Membership;
use App\Models\MembershipHold;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class StoreMembershipHold
{
    public function __construct(private StoreCompressedPaymentProof $storeProof) {}

    /**
     * @param array{months: mixed, total_amount: mixed, payment_date: mixed, admin_id: mixed, notes: mixed,
     *     is_split_payment: mixed, payment_method: mixed, amounts: array<string, mixed>,
     *     proofs: array<string, mixed>} $input
     */
    public function execute(Membership $membership, User $actor, string $token, string $expectedEndDate, array $input): MembershipHold
    {
        abort_unless($actor->role === 'admin', 403);
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($membership, $actor, $token, $expectedEndDate, $input, &$storedPaths): MembershipHold {
                $membership = Membership::query()->lockForUpdate()->findOrFail($membership->id);
                $existing = $membership->holds()->where('submission_token', $token)->first();

                if ($existing) {
                    abort_unless($existing->created_by === $actor->id, 403);

                    return $existing;
                }

                if ($reason = $membership->holdIneligibilityReason()) {
                    throw ValidationException::withMessages(['membership' => $reason]);
                }

                if ($membership->pt_end_date->toDateString() !== $expectedEndDate) {
                    throw ValidationException::withMessages([
                        'expectedEndDate' => 'Tanggal akhir PT telah berubah. Periksa tanggal dan biaya terbaru, lalu simpan kembali.',
                    ]);
                }

                $maxMonths = (9999 - $membership->pt_end_date->year) * 12 + 12 - $membership->pt_end_date->month;
                $rules = [
                    'months' => ['required', 'integer', 'min:1', 'max:'.$maxMonths],
                    'total_amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
                    'payment_date' => ['required', 'date_format:Y-m-d'],
                    'admin_id' => ['required', Rule::exists('users', 'id')->where('role', 'kasir_gym')->where('is_active', true)],
                    'notes' => ['nullable', 'string', 'max:2000'],
                    'is_split_payment' => ['required', 'boolean'],
                    'payment_method' => ['required', Rule::in(['cash', 'transfer', 'qris', 'debit'])],
                ];

                foreach (['cash', 'transfer', 'qris', 'debit'] as $method) {
                    if ($input['is_split_payment']) {
                        $rules['amounts.'.$method] = ['nullable', 'integer', 'min:0', 'max:999999999999'];
                    }

                    $requiresProof = $input['is_split_payment']
                        ? (is_numeric($input['amounts'][$method] ?? null) && $input['amounts'][$method] > 0)
                        : $input['payment_method'] === $method;

                    if ($method !== 'cash' && $requiresProof) {
                        $rules['proofs.'.$method] = ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:10240'];
                    }
                }

                $data = Validator::make($input, $rules, [
                    'proofs.*.required' => 'Bukti pembayaran wajib diunggah untuk setiap metode noncash.',
                ])->validate();
                $total = (int) $data['total_amount'];
                $payments = $data['is_split_payment']
                    ? collect($data['amounts'])->map(fn ($amount): int => (int) $amount)->filter(fn (int $amount): bool => $amount > 0)->all()
                    : [$data['payment_method'] => $total];

                if (array_sum($payments) !== $total) {
                    throw ValidationException::withMessages(['amounts' => 'Total split payment harus sama dengan biaya hold.']);
                }

                $cashier = User::findOrFail($data['admin_id']);
                $newEndDate = $membership->pt_end_date->copy()->addMonthsNoOverflow((int) $data['months']);
                $hold = $membership->holds()->create([
                    'created_by' => $actor->id,
                    'submission_token' => $token,
                    'months' => $data['months'],
                    'monthly_price' => null,
                    'total_amount' => $total,
                    'previous_end_date' => $membership->pt_end_date,
                    'new_end_date' => $newEndDate,
                ]);

                foreach ($payments as $method => $amount) {
                    $proofPath = null;

                    if ($method !== 'cash') {
                        $proofPath = $this->storeProof->execute($data['proofs'][$method]);
                        $storedPaths[] = $proofPath;
                    }

                    $hold->transactions()->create([
                        'invoice_number' => 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(13)),
                        'membership_id' => $membership->id,
                        'user_id' => $membership->user_id,
                        'admin_id' => $cashier->id,
                        'shift' => $cashier->shiftSnapshot(),
                        'follow_up_id' => $membership->follow_up_id,
                        'follow_up_id_two' => $membership->follow_up_id_two,
                        'transaction_type' => 'HOLD PT',
                        'package_name' => 'Hold PT '.$hold->months.' bulan',
                        'amount' => $amount,
                        'payment_method' => $method,
                        'payment_proof_path' => $proofPath,
                        'payment_date' => $data['payment_date'],
                        'start_date' => $hold->previous_end_date,
                        'end_date' => $hold->new_end_date,
                        'notes' => $data['notes'] ?? null,
                    ]);
                }

                $membership->pt_end_date = $newEndDate;

                if ($membership->status === 'completed' && $newEndDate->gte(today())) {
                    $membership->status = 'active';
                    $membership->is_active = true;
                } elseif ($newEndDate->lt(today())) {
                    $membership->status = 'completed';
                    $membership->is_active = false;
                }

                $membership->save();

                return $hold;
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($storedPaths);

            throw $exception;
        }
    }
}
