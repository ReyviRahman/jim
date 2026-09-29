<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('memberships as m')
            ->leftJoin('gym_packages as g', 'g.id', '=', 'm.gym_package_id')
            ->leftJoin('gym_packages as p', 'p.id', '=', 'm.pt_package_id')
            ->leftJoin('membership_operational_requests as r', 'r.id', '=', 'm.operational_request_id')
            ->select('m.id', 'm.type', 'm.gym_package_name_snapshot', 'm.pt_package_name_snapshot', 'g.name as gym_name', 'p.name as pt_name', 'r.snapshot')
            ->chunkById(500, function ($memberships): void {
                foreach ($memberships as $membership) {
                    $snapshot = json_decode($membership->snapshot ?? '{}', true) ?? [];
                    foreach (['gym' => ['membership', 'visit', 'bundle_pt_membership'], 'pt' => ['pt', 'bundle_pt_membership']] as $kind => $types) {
                        $field = $kind.'_package_name_snapshot';
                        if (! in_array($membership->type, $types, true) || filled($membership->{$field})) {
                            continue;
                        }
                        $name = $snapshot['membership'][$field] ?? null;
                        if (blank($name)) {
                            $name = $snapshot[$kind.'_package_name'] ?? null;
                        }
                        if (blank($name)) {
                            $name = $membership->{$kind.'_name'};
                        }
                        if (filled($name)) {
                            DB::table('memberships')->where('id', $membership->id)
                                ->where(function ($query) use ($field): void {
                                    $query->whereNull($field)->orWhereRaw('TRIM('.$field.") = ''");
                                })->update([$field => $name]);
                        }
                    }
                }
            }, 'm.id', 'id');
    }

    public function down(): void {}
};
