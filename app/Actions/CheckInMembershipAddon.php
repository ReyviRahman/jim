<?php

namespace App\Actions;

use App\Models\Attendance;
use App\Models\MembershipAddon;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class CheckInMembershipAddon
{
    /** @param array<string, mixed> $data */
    public function execute(User $actor, User $member, array $data): Attendance
    {
        abort_unless(in_array($actor->role, MembershipOperationalApproval::ROLES, true), 403);
        $validated = Validator::make($data, [
            'membership_addon_id' => ['required', 'integer'],
            'membership_id' => ['required', 'integer'],
            'booking_id' => ['prohibited'],
        ])->validate();

        return DB::transaction(function () use ($member, $validated): Attendance {
            User::whereKey($member->id)->lockForUpdate()->firstOrFail();
            $addon = MembershipAddon::query()->accessibleTo($member)->usable()
                ->whereKey($validated['membership_addon_id'])->where('membership_id', $validated['membership_id'])
                ->lockForUpdate()->first();
            if ($addon === null) {
                throw ValidationException::withMessages(['addon' => 'Add-on belum disetujui, belum aktif, sudah berakhir, atau bukan milik member ini.']);
            }
            if (Attendance::where('user_id', $member->id)->where('check_in_time', '>=', now()->subMinute())->exists()) {
                throw ValidationException::withMessages(['addon' => 'Member sudah melakukan absen.']);
            }

            return Attendance::create([
                'user_id' => $member->id, 'membership_id' => $addon->membership_id,
                'membership_addon_id' => $addon->id, 'type' => 'gym', 'check_in_time' => now(),
            ]);
        });
    }
}
