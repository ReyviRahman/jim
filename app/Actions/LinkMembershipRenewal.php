<?php

namespace App\Actions;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LinkMembershipRenewal
{
    /** @param array<int, int> $memberIds */
    public function lockMembers(array $memberIds): void
    {
        User::whereKey($memberIds)->orderBy('id')->lockForUpdate()->get();
    }

    /** @param array<int, int> $memberIds */
    public function execute(Membership $renewal, array $memberIds, ?array $selectedIds = null, ?array $candidateIds = null): void
    {
        if (! $renewal->is_renewal) {
            return;
        }

        $candidates = $this->candidates($memberIds, $renewal->id, true);
        $previousIds = $selectedIds === null ? $candidates->modelKeys() : $this->validateSelection($candidates->modelKeys(), $selectedIds, $candidateIds);

        foreach (Membership::whereKey($previousIds)->orderBy('id')->lockForUpdate()->get() as $previous) {
            $previous->update(['renewal_from' => $renewal->id]);
        }
    }

    /** @param array<int, int> $memberIds
     * @return Collection<int, Membership>
     */
    public function candidates(array $memberIds, ?int $beforeId = null, bool $lock = false): Collection
    {
        $previousIds = [];
        sort($memberIds);
        foreach (array_unique($memberIds) as $memberId) {
            $previous = Membership::query()
                ->when($beforeId !== null, fn (Builder $query): Builder => $query->where('id', '<', $beforeId))
                ->where(function (Builder $query) use ($memberId): void {
                    $query->where('user_id', $memberId)
                        ->orWhereHas('members', fn (Builder $members): Builder => $members->whereKey($memberId));
                })
                ->orderByDesc('id')->when($lock, fn (Builder $query): Builder => $query->lockForUpdate())->first();

            if ($previous !== null) {
                $previousIds[] = $previous->id;
            }
        }

        return Membership::with(['user', 'members', 'gymPackage', 'ptPackage'])
            ->whereKey(array_unique($previousIds))->orderBy('id')->get();
    }

    /** @return array<int, int> */
    public function validateSelection(array $currentIds, ?array $selectedIds, ?array $candidateIds = null): array
    {
        sort($currentIds);
        if ($candidateIds !== null) {
            sort($candidateIds);
            if ($currentIds !== $candidateIds) {
                throw ValidationException::withMessages(['selectedRenewalIds' => 'Daftar paket berubah. Buka kembali pilihan paket renewal.']);
            }
        }
        if ($selectedIds === null && count($currentIds) <= 1) {
            return $currentIds;
        }

        $validated = Validator::make(['selectedRenewalIds' => $selectedIds], [
            'selectedRenewalIds' => ['required', 'array', 'min:1'],
            'selectedRenewalIds.*' => ['required', 'integer', 'distinct', Rule::in($currentIds)],
        ], ['selectedRenewalIds.required' => 'Pilih minimal satu paket yang akan di-renewal.',
            'selectedRenewalIds.*.in' => 'Paket yang dipilih tidak tersedia untuk renewal.'])->validate();

        return array_map('intval', $validated['selectedRenewalIds']);
    }
}
