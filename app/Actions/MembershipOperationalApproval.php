<?php

namespace App\Actions;

use App\MembershipWaiverTerms;
use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\MembershipOperationalRequest;
use App\Models\MembershipTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class MembershipOperationalApproval
{
    public const ROLES = ['admin', 'kasir_gym', 'head_coach'];

    /** @param array<string, mixed> $input
     * @param  array<int, array{accepted?: bool, signature?: string|null}>  $waivers
     * @param  array<int, UploadedFile|null>  $photos
     */
    public function submit(User $actor, array $input, array $waivers, array $photos): MembershipOperationalRequest
    {
        abort_unless(in_array($actor->role, self::ROLES, true), 403);
        $data = Validator::make($input, [
            'submission_token' => ['required', 'uuid'],
            'user_ids' => ['required', 'array', 'min:1', 'max:100'],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'registration_type' => ['required', Rule::in(['membership', 'pt', 'bundle_pt_membership', 'visit'])],
            'is_active' => ['required', 'boolean'],
            'start_date' => ['nullable', 'required_if:is_active,1', 'date'],
            'membership_end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'pt_end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'gym_package_id' => ['nullable', 'integer', 'exists:gym_packages,id'],
            'pt_package_id' => ['nullable', 'integer', 'exists:gym_packages,id'],
            'pt_id' => ['nullable', Rule::exists('users', 'id')->where('role', 'pt')],
            'admin_id' => ['required', 'exists:users,id'],
            'manual_discount' => ['nullable', 'integer', 'min:0'],
            'admin_fee' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'payment_date' => ['required', 'date'],
            'transaction_type' => ['required', 'string', 'max:255'],
            'package_name' => ['required', 'string', 'max:255'],
            'notes' => ['required', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:1000'],
            'pt_trial_interest' => ['required', Rule::in(array_keys(Membership::PT_TRIAL_INTEREST_OPTIONS))],
            'payment_type' => ['required', 'in:paid'],
            'is_split_payment' => ['required', 'boolean', 'declined'],
        ])->validate();
        $createdFiles = [];
        try {
            return DB::transaction(function () use ($actor, $data, $waivers, $photos, &$createdFiles): MembershipOperationalRequest {
                User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
                $existing = MembershipOperationalRequest::where('submission_token', $data['submission_token'])->first();
                if ($existing) {
                    abort_unless($existing->requested_by === $actor->id, 403);

                    return $existing;
                }
                $members = User::whereIn('id', $data['user_ids'])->orderBy('id')->get();
                $snapshot = $this->snapshot($data, $members->count());
                $validatedWaivers = app(StoreMembershipWaivers::class)->validate($members, $waivers, required: true);
                $documents = [];
                foreach ($members as $member) {
                    $photoPath = null;
                    if (blank($member->photo)) {
                        Validator::make(['photo' => $photos[$member->id] ?? null], ['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:10240']])->validate();
                        $photoPath = app(StoreCompressedProfilePhoto::class)->execute($photos[$member->id]);
                        $createdFiles[] = ['disk' => 'public', 'path' => $photoPath];
                    }
                    $signaturePath = 'membership-operational/'.$data['submission_token'].'/'.$member->id.'.png';
                    $createdFiles[] = ['disk' => 'local', 'path' => $signaturePath];
                    if (! Storage::disk('local')->put($signaturePath, $validatedWaivers[$member->id]['signature'])) {
                        throw new \RuntimeException('Tanda tangan gagal disimpan.');
                    }
                    $documents[] = ['user_id' => $member->id, 'member_name' => $member->name, 'signature_path' => $signaturePath, 'photo_path' => $photoPath];
                }
                $snapshot['members'] = $members->map(fn (User $member): array => ['id' => $member->id, 'name' => $member->name])->all();
                $snapshot['terms_snapshot'] = MembershipWaiverTerms::snapshot();
                $snapshot['consent_label'] = MembershipWaiverTerms::CONSENT_LABEL;
                $request = MembershipOperationalRequest::create([
                    'submission_token' => $data['submission_token'], 'requested_by' => $actor->id,
                    'requested_by_name' => $actor->name, 'status' => 'pending', 'reason' => trim($data['reason']),
                    'requested_at' => now(), 'snapshot' => $snapshot, 'documents' => $documents,
                ]);
                if ($actor->role === 'admin') {
                    $this->approve($actor, $request->id);
                }

                return $request->refresh();
            });
        } catch (\Throwable $exception) {
            foreach ($createdFiles as $file) {
                Storage::disk($file['disk'])->delete($file['path']);
            }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function snapshot(array $data, int $memberCount): array
    {
        foreach (['start_date', 'membership_end_date', 'pt_end_date', 'payment_date'] as $field) {
            $data[$field] = filled($data[$field] ?? null) ? Carbon::parse($data[$field])->toDateString() : null;
        }
        $type = $data['registration_type'];
        if ($type === 'visit' && $memberCount !== 1) {
            throw ValidationException::withMessages(['registration_type' => 'Visit hanya untuk satu member.']);
        }
        $gym = in_array($type, ['membership', 'bundle_pt_membership', 'visit'], true) ? $this->package($data['gym_package_id'] ?? null, $type === 'visit' ? 'visit' : 'gym', $memberCount) : null;
        $pt = in_array($type, ['pt', 'bundle_pt_membership'], true) ? $this->package($data['pt_package_id'] ?? null, 'pt', $memberCount) : null;
        if ($data['is_active']) {
            Validator::make($data, [
                'membership_end_date' => $gym ? ['required', 'date', 'after_or_equal:start_date'] : ['nullable'],
                'pt_end_date' => $pt ? ['required', 'date', 'after_or_equal:start_date'] : ['nullable'],
            ])->validate();
        }
        $base = (int) ($gym?->price ?? 0) + (int) ($pt?->price ?? 0);
        $manual = (int) ($data['manual_discount'] ?? 0);
        if ($manual > $base) {
            throw ValidationException::withMessages(['manual_discount' => 'Diskon melebihi harga paket.']);
        }
        $discount = (int) ($gym?->discount ?? 0) + (int) ($pt?->discount ?? 0) + $manual;
        $fee = (int) ($data['admin_fee'] ?? 0);
        $price = max(0, $base - $discount) + $fee;
        $primaryPackage = $gym ?? $pt;
        $attributes = [
            'user_id' => $data['user_ids'][0], 'type' => $type,
            'gym_package_id' => $gym?->id, 'pt_package_id' => $pt?->id,
            'pt_id' => $pt ? ($data['pt_id'] ?? null) : null,
            'admin_id' => $data['admin_id'], 'follow_up_id' => null, 'follow_up_id_two' => null,
            'base_price' => $base, 'discount_applied' => $discount, 'admin_fee' => $fee,
            'normal_price' => $primaryPackage->normal_price, 'net_price' => $primaryPackage->net_price, 'unrecommended_price' => $primaryPackage->unrecommended_price,
            'price_paid' => $price, 'total_paid' => $price, 'payment_status' => 'paid',
            'total_sessions' => $pt?->pt_sessions, 'remaining_sessions' => $pt?->pt_sessions,
            'start_date' => $data['start_date'] ?? null,
            'membership_end_date' => $gym ? ($data['membership_end_date'] ?? null) : null,
            'pt_end_date' => $pt ? ($data['pt_end_date'] ?? null) : null,
            'status' => 'active', 'is_active' => (bool) $data['is_active'],
            'notes' => $data['notes'], 'pt_trial_interest' => $data['pt_trial_interest'],
            'transaction_type' => $data['transaction_type'], 'package_name' => $data['package_name'],
        ];

        return ['membership' => $attributes, 'payment_date' => $data['payment_date'],
            'gym_package_name' => $gym?->name, 'pt_package_name' => $pt?->name,
            'shift' => User::findOrFail($data['admin_id'])->shiftSnapshot(),
            'admin_name' => User::findOrFail($data['admin_id'])->name,
            'trainer_name' => isset($attributes['pt_id']) ? User::find($attributes['pt_id'])?->name : null,
        ];
    }

    private function package(?int $id, string $type, int $count): GymPackage
    {
        $package = GymPackage::whereKey($id)->where('type', $type)->where('is_active', true)->first();
        $category = $count === 1 ? 'single' : ($count === 2 ? 'couple' : 'group');
        if (! $package || ($type !== 'visit' && ($package->category !== $category || ($count >= 3 && $package->max_members < $count)))) {
            throw ValidationException::withMessages([$type === 'pt' ? 'pt_package_id' : 'gym_package_id' => 'Paket tidak sesuai dengan jenis atau jumlah member.']);
        }

        return $package;
    }

    public function approve(User $admin, int $id): MembershipOperationalRequest
    {
        abort_unless($admin->role === 'admin', 403);

        return DB::transaction(function () use ($admin, $id): MembershipOperationalRequest {
            $request = MembershipOperationalRequest::lockForUpdate()->findOrFail($id);
            if ($request->status === 'approved') {
                return $request;
            }
            $this->requirePending($request);
            $snapshot = $request->snapshot;
            $attributes = $snapshot['membership'];
            $packageIds = array_filter([$attributes['gym_package_id'], $attributes['pt_package_id']]);
            $staffIds = array_unique(array_filter([$attributes['admin_id'], $attributes['pt_id']]));
            if (GymPackage::whereIn('id', $packageIds)->count() !== count(array_unique($packageIds)) || User::whereIn('id', $staffIds)->count() !== count($staffIds)) {
                throw ValidationException::withMessages(['approval' => 'Paket atau petugas pada pengajuan tidak lagi tersedia.']);
            }
            $memberIds = array_column($snapshot['members'], 'id');
            $members = User::whereIn('id', $memberIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($members->count() !== count($memberIds)) {
                throw ValidationException::withMessages(['approval' => 'Data member tidak lagi lengkap.']);
            }
            foreach ($request->documents as $document) {
                if (! Storage::disk('local')->exists($document['signature_path']) || ($document['photo_path'] && ! Storage::disk('public')->exists($document['photo_path']))) {
                    throw ValidationException::withMessages(['approval' => 'Dokumen pengajuan tidak lengkap.']);
                }
                $member = $members->get($document['user_id']);
                if (blank($member->photo)) {
                    if (! $document['photo_path']) {
                        throw ValidationException::withMessages(['approval' => 'Foto profil member tidak lagi tersedia.']);
                    }
                    $member->update(['photo' => $document['photo_path']]);
                }
            }
            $today = today()->toDateString();
            $endDates = array_filter([$attributes['membership_end_date'], $attributes['pt_end_date']]);
            if ($attributes['is_active'] && $endDates && max($endDates) < $today) {
                $attributes['status'] = 'completed';
                $attributes['is_active'] = false;
            }
            if ($attributes['pt_end_date'] && $attributes['pt_end_date'] < $today) {
                $attributes['remaining_sessions'] = 0;
                $attributes['sesi_hangus'] = $attributes['total_sessions'];
            }
            $membership = Membership::create($attributes + ['operational_request_id' => $request->id]);
            $membership->members()->attach($memberIds);
            foreach ($request->documents as $document) {
                $membership->waivers()->create([
                    'user_id' => $document['user_id'], 'member_name' => $document['member_name'],
                    'accepted' => true, 'signature_path' => $document['signature_path'],
                    'recorded_at' => $request->requested_at, 'admin_id' => $request->requested_by,
                    'terms_snapshot' => $snapshot['terms_snapshot'], 'consent_label' => $snapshot['consent_label'],
                ]);
            }
            MembershipTransaction::create([
                'invoice_number' => 'INV-'.now()->format('Ymd').'-'.Str::upper((string) Str::uuid()),
                'operational_request_id' => $request->id, 'membership_id' => $membership->id,
                'user_id' => $membership->user_id, 'admin_id' => $membership->admin_id, 'shift' => $snapshot['shift'],
                'follow_up_id' => $membership->follow_up_id, 'follow_up_id_two' => $membership->follow_up_id_two,
                'transaction_type' => $membership->transaction_type, 'package_name' => $membership->package_name,
                'amount' => $membership->price_paid, 'payment_method' => 'operasional',
                'payment_date' => $snapshot['payment_date'], 'start_date' => $membership->start_date,
                'end_date' => $membership->type === 'pt' ? $membership->pt_end_date : $membership->membership_end_date,
                'notes' => $membership->notes,
            ]);
            $request->update(['status' => 'approved', 'decided_by' => $admin->id, 'decided_at' => now()]);

            return $request->refresh();
        }, 3);
    }

    public function reject(User $admin, int $id, string $reason): void
    {
        abort_unless($admin->role === 'admin', 403);
        Validator::make(['reason' => trim($reason)], ['reason' => ['required', 'string', 'max:1000']])->validate();
        DB::transaction(function () use ($admin, $id, $reason): void {
            $request = MembershipOperationalRequest::lockForUpdate()->findOrFail($id);
            $this->requirePending($request);
            $request->update(['status' => 'rejected', 'rejection_reason' => trim($reason), 'decided_by' => $admin->id, 'decided_at' => now()]);
        }, 3);
    }

    public function deletePending(User $actor, int $id): void
    {
        abort_unless(in_array($actor->role, self::ROLES, true), 403);
        DB::transaction(function () use ($actor, $id): void {
            $request = MembershipOperationalRequest::lockForUpdate()->findOrFail($id);
            abort_unless($actor->role === 'admin' || $request->requested_by === $actor->id, 403);
            $this->requirePending($request);
            $documents = $request->documents;
            $request->delete();
            DB::afterCommit(function () use ($documents): void {
                foreach ($documents as $document) {
                    Storage::disk('local')->delete($document['signature_path']);
                    if ($document['photo_path']) {
                        Storage::disk('public')->delete($document['photo_path']);
                    }
                }
            });
        }, 3);
    }

    private function requirePending(MembershipOperationalRequest $request): void
    {
        if ($request->status !== 'pending') {
            throw ValidationException::withMessages(['approval' => 'Pengajuan sudah diproses.']);
        }
    }
}
