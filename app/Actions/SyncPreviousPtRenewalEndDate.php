<?php

namespace App\Actions;

use App\Models\Membership;
use App\Models\User;

final class SyncPreviousPtRenewalEndDate
{
    /** @param array<int, int> $memberIds */
    public function execute(Membership $renewal, array $memberIds): void
    {
        $endDate = in_array($renewal->type, ['pt', 'bundle_pt_membership'], true)
            ? $renewal->pt_end_date : $renewal->membership_end_date;

        if (! $renewal->is_renewal || $endDate === null) {
            return;
        }

        $previousIds = [];
        foreach (User::whereKey($memberIds)->orderBy('id')->get() as $member) {
            $previous = $member->memberships()
                ->where('memberships.id', '<', $renewal->id)
                ->orderByDesc('memberships.id')
                ->lockForUpdate()
                ->first();

            if ($previous !== null) {
                $previousIds[] = $previous->id;
            }
        }

        foreach (Membership::whereKey(array_unique($previousIds))->orderBy('id')->lockForUpdate()->get() as $previous) {
            if ($previous->type === 'pt'
                && $previous->status === 'active'
                && $previous->is_active
                && $previous->pt_end_date?->greaterThanOrEqualTo(today())
                && $previous->remaining_sessions > 0) {
                $previous->update(['pt_end_date' => $endDate]);
            }
        }
    }
}
