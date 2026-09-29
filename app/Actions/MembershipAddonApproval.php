<?php

namespace App\Actions;

use App\Models\Membership;
use App\Models\MembershipAddon;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class MembershipAddonApproval
{
    /** @param array{name: string, duration_months: int, duration_weeks: int, duration_days: int}|null $input */
    public function submit(Membership $membership, ?array $input, ?int $requestedBy, ?string $activatedStartDate = null): ?MembershipAddon
    {
        if ($input === null) {
            return null;
        }
        abort_unless($membership->type === 'pt', 422);

        return DB::transaction(function () use ($membership, $input, $requestedBy, $activatedStartDate): MembershipAddon {
            Membership::whereKey($membership->id)->lockForUpdate()->firstOrFail();
            $addon = $membership->addon()->firstOrCreate([], $input + [
                'requested_by' => $requestedBy, 'requested_at' => now(),
                'approval_status' => 'pending', 'status' => 'pending',
            ]);
            if ($addon->wasRecentlyCreated && $activatedStartDate !== null) {
                $addon->update([
                    'start_date' => $activatedStartDate,
                    'end_date' => CarbonImmutable::parse($activatedStartDate)->addDays($addon->durationInDays() - 1),
                ]);
            }
            $this->synchronize($membership);

            return $addon->refresh();
        });
    }

    public function approve(User $actor, int $id): void
    {
        $this->decide($actor, $id, 'approved');
    }

    public function reject(User $actor, int $id, string $reason): void
    {
        abort_unless($actor->role === 'admin', 403);
        $data = Validator::make(['rejection_reason' => trim($reason)], [
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ], ['rejection_reason.required' => 'Alasan penolakan wajib diisi.'])->validate();
        $this->decide($actor, $id, 'rejected', $data['rejection_reason']);
    }

    private function decide(User $actor, int $id, string $decision, ?string $reason = null): void
    {
        abort_unless($actor->role === 'admin', 403);
        $membershipId = MembershipAddon::findOrFail($id)->membership_id;
        DB::transaction(function () use ($actor, $id, $decision, $reason, $membershipId): void {
            $membership = Membership::whereKey($membershipId)->lockForUpdate()->firstOrFail();
            $addon = MembershipAddon::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($addon->approval_status === $decision) {
                return;
            }
            if ($addon->approval_status !== 'pending') {
                throw ValidationException::withMessages(['addon_approval' => 'Pengajuan ini sudah diputuskan.']);
            }
            if ($decision === 'approved' && ($membership->status === 'rejected' || $addon->status === 'rejected')) {
                throw ValidationException::withMessages(['addon_approval' => 'Kontrak PT ditolak. Add-on tidak dapat disetujui.']);
            }
            $addon->update([
                'approval_status' => $decision, 'decided_by' => $actor->id, 'decided_at' => now(),
                'rejection_reason' => $reason,
                'status' => $decision === 'rejected' ? 'rejected' : $addon->status,
            ]);
            $this->synchronize($membership);
        }, 3);
    }

    public function synchronize(Membership $membership): void
    {
        DB::transaction(function () use ($membership): void {
            $membership = Membership::whereKey($membership->id)->lockForUpdate()->firstOrFail();
            $addon = $membership->addon()->lockForUpdate()->first();
            if ($addon === null || in_array($addon->status, ['active', 'completed', 'rejected'], true)) {
                return;
            }
            if ($membership->status === 'rejected') {
                $addon->update(['status' => 'rejected']);

                return;
            }
            if ($addon->start_date === null && $membership->payment_status === 'paid'
                && (($membership->is_active && $membership->status === 'active') || $membership->status === 'completed')
                && $membership->start_date !== null) {
                $addon->start_date = $membership->start_date;
                $addon->end_date = $membership->start_date->copy()->addDays($addon->durationInDays() - 1);
            }
            if ($addon->approval_status === 'approved' && $addon->start_date !== null
                && $membership->payment_status === 'paid'
                && ($membership->is_active || $membership->status === 'completed')) {
                $addon->status = $addon->end_date->lt(today('Asia/Jakarta')) ? 'completed' : 'active';
            }
            $addon->save();
        });
    }
}
