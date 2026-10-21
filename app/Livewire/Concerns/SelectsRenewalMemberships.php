<?php

namespace App\Livewire\Concerns;

use App\Actions\LinkMembershipRenewal;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

trait SelectsRenewalMemberships
{
    public bool $showRenewalSelection = false;

    public array $selectedRenewalIds = [];

    #[Locked]
    public ?array $renewalCandidateIds = null;

    #[Locked]
    public ?array $confirmedRenewalIds = null;

    #[Computed]
    public function renewalCandidates(): Collection
    {
        return app(LinkMembershipRenewal::class)->candidates($this->selectedUsers->modelKeys());
    }

    public function updatedIsRenewal(): void
    {
        $this->reset('selectedRenewalIds', 'renewalCandidateIds', 'confirmedRenewalIds', 'showRenewalSelection');
        if ((bool) $this->is_renewal && $this->renewalCandidates->count() > 1) {
            $this->openRenewalSelection();
        }
    }

    public function openRenewalSelection(): void
    {
        unset($this->renewalCandidates);
        $this->renewalCandidateIds = $this->renewalCandidates->modelKeys();
        $this->selectedRenewalIds = array_values(array_intersect($this->confirmedRenewalIds ?? [], $this->renewalCandidateIds));
        $this->confirmedRenewalIds = null;
        $this->resetValidation('selectedRenewalIds');
        $this->showRenewalSelection = true;
    }

    public function confirmRenewalSelection(): void
    {
        $this->confirmedRenewalIds = app(LinkMembershipRenewal::class)->validateSelection(
            $this->renewalCandidates->modelKeys(), $this->selectedRenewalIds, $this->renewalCandidateIds,
        );
        $this->showRenewalSelection = false;
    }

    protected function renewalSelectionReady(bool $isRenewal): bool
    {
        if (! $isRenewal) {
            return true;
        }
        unset($this->renewalCandidates);
        $ids = $this->renewalCandidates->modelKeys();
        if ($this->confirmedRenewalIds !== null && $ids === $this->renewalCandidateIds) {
            return true;
        }
        $this->confirmedRenewalIds = null;
        $this->renewalCandidateIds = null;
        if (count($ids) > 1) {
            $this->openRenewalSelection();

            return false;
        }

        return true;
    }

    protected function validateRenewalSelectionForSave(bool $isRenewal): void
    {
        if ($isRenewal) {
            app(LinkMembershipRenewal::class)->validateSelection(
                app(LinkMembershipRenewal::class)->candidates($this->selectedUsers->modelKeys(), lock: true)->modelKeys(),
                $this->confirmedRenewalIds, $this->renewalCandidateIds,
            );
        }
    }
}
