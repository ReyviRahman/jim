<?php

namespace App\Livewire\Concerns;

use App\MembershipDates;
use App\Models\GymPackage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;

trait CalculatesMembershipDates
{
    protected function syncMembershipDates(): void
    {
        $this->membership_end_date = null;
        $this->pt_end_date = null;

        if (! $this->is_active || $this->payment_type === 'partial') {
            $this->start_date = null;

            return;
        }

        if (Validator::make(['start_date' => $this->start_date], ['start_date' => ['required', 'date_format:Y-m-d']])->fails()) {
            return;
        }

        $gym = in_array($this->registration_type, ['membership', 'bundle_pt_membership', 'visit'], true) && $this->gym_package_id
            ? GymPackage::find($this->gym_package_id) : null;
        $pt = in_array($this->registration_type, ['pt', 'bundle_pt_membership'], true) && $this->pt_package_id
            ? GymPackage::find($this->pt_package_id) : null;

        $this->fill(app(MembershipDates::class)->endDates($this->start_date, $gym, $pt));
    }

    protected function validateMembershipDates(): void
    {
        if (! $this->is_active || $this->payment_type === 'partial') {
            $this->syncMembershipDates();

            return;
        }

        $rules = ['start_date' => ['required', 'date_format:Y-m-d']];
        foreach (['membership_end_date' => ['membership', 'bundle_pt_membership', 'visit'], 'pt_end_date' => ['pt', 'bundle_pt_membership']] as $field => $types) {
            if (in_array($this->registration_type, $types, true)) {
                $rules[$field] = ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'];
            }
        }
        $this->validate($rules);
    }

    #[Computed]
    public function programDuration(): string
    {
        if (Validator::make(['date' => $this->start_date], ['date' => 'required|date_format:Y-m-d'])->fails()) {
            return '-';
        }

        $parts = [];
        foreach (['Gym' => $this->membership_end_date, 'PT' => $this->pt_end_date] as $label => $endDate) {
            if (Validator::make(['date' => $endDate, 'start' => $this->start_date], ['date' => 'required|date_format:Y-m-d|after_or_equal:start'])->passes()) {
                $days = (int) CarbonImmutable::parse($this->start_date)->diffInDays(CarbonImmutable::parse($endDate)) + 1;
                $duration = [];
                foreach (['bulan' => 30, 'minggu' => 7, 'hari' => 1] as $unit => $size) {
                    $amount = intdiv($days, $size);
                    $days %= $size;
                    if ($amount > 0) {
                        $duration[] = $amount.' '.$unit;
                    }
                }
                $parts[] = ($this->registration_type === 'bundle_pt_membership' ? $label.': ' : '').implode(' ', $duration);
            }
        }

        return $parts ? implode(' / ', $parts) : '-';
    }
}
