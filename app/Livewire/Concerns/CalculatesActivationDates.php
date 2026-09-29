<?php

namespace App\Livewire\Concerns;

use App\MembershipDates;
use App\Models\Membership;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

trait CalculatesActivationDates
{
    public function updatedStartDate(): void
    {
        $this->endDate = '';
        $this->resetValidation(['startDate', 'membership']);
        if (Validator::make(['date' => $this->startDate], ['date' => 'required|date_format:Y-m-d'])->fails()) {
            return;
        }
        if ($this->selectedMembership === null) {
            return;
        }
        try {
            $this->endDate = $this->activationEndDate($this->selectedMembership, $this->startDate);
        } catch (ValidationException $exception) {
            $this->addError('membership', $exception->validator->errors()->first());
        }
    }

    private function activationEndDate(Membership $membership, string $startDate): string
    {
        $isPt = $membership->type === 'pt';
        $package = $isPt ? $membership->ptPackage : $membership->gymPackage;
        if ($package === null || $package->durationInDays() < 1) {
            throw ValidationException::withMessages(['membership' => 'Durasi master paket belum tersedia. Lengkapi master paket sebelum aktivasi.']);
        }
        $dates = app(MembershipDates::class)->endDates($startDate, $isPt ? null : $package, $isPt ? $package : null);

        return $dates[$isPt ? 'pt_end_date' : 'membership_end_date'];
    }
}
