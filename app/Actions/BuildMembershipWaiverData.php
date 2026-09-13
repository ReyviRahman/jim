<?php

namespace App\Actions;

use App\Models\Membership;
use App\Models\MembershipWaiver;
use Illuminate\Support\Facades\Storage;

class BuildMembershipWaiverData
{
    /** @return array<int, array<string, mixed>> */
    public function execute(Membership $membership): array
    {
        $membership->loadMissing('waivers');

        return $membership->waivers->map(fn (MembershipWaiver $waiver): array => [
            'id' => $waiver->id,
            'user_id' => $waiver->user_id,
            'member_name' => $waiver->member_name,
            'accepted' => $waiver->accepted,
            'recorded_at' => $waiver->recorded_at,
            'terms_snapshot' => $waiver->terms_snapshot,
            'consent_label' => $waiver->consent_label,
            'signature_data_uri' => $this->signatureDataUri($waiver->signature_path),
        ])->all();
    }

    private function signatureDataUri(?string $path): ?string
    {
        if (! $path || preg_match('#\Amembership-waivers/\d+/[a-f0-9-]+\.png\z#i', $path) !== 1) {
            return null;
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($disk->get($path));
    }
}
