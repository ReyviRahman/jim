<?php

namespace App\Actions;

use App\Models\BeverageSale;
use App\Models\Expense;
use App\Models\ExpenseRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ExpenseApproval
{
    public const ROLES = [
        'gym' => ['admin', 'kasir_gym', 'head_coach'],
        'beverage' => ['admin', 'kasir_gym', 'kasir_minum'],
    ];

    /** @param array{description: mixed, amount: mixed, expense_date: mixed} $input */
    public function submit(User $user, string $source, array $input): ExpenseRequest
    {
        abort_unless(in_array($user->role, self::ROLES[$source] ?? [], true), 403);
        $data = Validator::make($input, [
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:1', 'max:'.($source === 'beverage' ? 2147483647 : 999999999999)],
            'expense_date' => ['required', 'date_format:Y-m-d'],
        ])->validate();

        return DB::transaction(function () use ($user, $source, $data): ExpenseRequest {
            $request = ExpenseRequest::create([
                ...$data,
                'source' => $source,
                'requested_by' => $user->id,
                'requested_by_name' => $user->name,
                'shift' => $source === 'beverage' ? $user->beverageShiftSnapshot() : $user->shiftSnapshot(),
                'status' => 'pending',
                'requested_at' => now(),
            ]);
            if ($user->role === 'admin') {
                return $this->approve($user, $request->id);
            }

            return $request;
        }, 3);
    }

    public function approve(User $admin, int $id): ExpenseRequest
    {
        abort_unless($admin->role === 'admin', 403);

        return DB::transaction(function () use ($admin, $id): ExpenseRequest {
            $request = ExpenseRequest::lockForUpdate()->findOrFail($id);
            if ($request->status === 'approved') {
                return $request;
            }
            $this->ensurePending($request);

            if ($request->source === 'gym') {
                $expense = Expense::create([
                    'admin_id' => $request->requested_by,
                    'shift' => $request->shift,
                    'description' => $request->description,
                    'amount' => $request->amount,
                    'expense_date' => $request->expense_date,
                ]);
                $request->expense_id = $expense->id;
            } else {
                $sale = BeverageSale::create([
                    'nama_staff' => $request->requested_by_name,
                    'nama_penghutang' => $request->requested_by_name,
                    'shift' => $request->shift,
                    'nama_produk' => $request->description,
                    'waktu_transaksi' => $request->expense_date,
                    'jumlah_beli' => 0,
                    'harga_satuan' => 0,
                    'total_harga' => $request->amount,
                    'keterangan_bayar' => 'pengeluaran_umum',
                ]);
                $request->beverage_sale_id = $sale->id;
            }
            $request->fill(['status' => 'approved', 'decided_by' => $admin->id, 'decided_at' => now()])->save();

            return $request;
        }, 3);
    }

    public function reject(User $admin, int $id, string $reason): void
    {
        abort_unless($admin->role === 'admin', 403);
        $data = Validator::make(['rejection_reason' => trim($reason)], [
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ])->validate();

        DB::transaction(function () use ($admin, $id, $data): void {
            $request = ExpenseRequest::lockForUpdate()->findOrFail($id);
            $this->ensurePending($request);
            $request->update([...$data, 'status' => 'rejected', 'decided_by' => $admin->id, 'decided_at' => now()]);
        }, 3);
    }

    public function deletePending(User $user, int $id): void
    {
        DB::transaction(function () use ($user, $id): void {
            $request = ExpenseRequest::lockForUpdate()->findOrFail($id);
            abort_unless(in_array($user->role, self::ROLES[$request->source] ?? [], true), 403);
            abort_unless($user->role === 'admin' || $request->requested_by === $user->id, 403);
            $this->ensurePending($request);
            $request->delete();
        }, 3);
    }

    private function ensurePending(ExpenseRequest $request): void
    {
        if ($request->status !== 'pending') {
            throw ValidationException::withMessages(['approval' => 'Pengajuan sudah diproses.']);
        }
    }
}
