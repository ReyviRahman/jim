<?php

namespace App\Concerns;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;

trait QueriesPtSessionMemberships
{
    private function getMembershipQuery(): Builder
    {
        return Membership::query()
            ->when($this->dateStart && $this->dateEnd, function ($query) {
                $query->where(function ($q) {
                    $q->where(function ($periodQuery) {
                        $periodQuery->whereDate('start_date', '<=', $this->dateEnd)
                            ->whereDate('pt_end_date', '>=', $this->dateStart);
                    })->orWhereExists(function ($bookingQuery) {
                        $bookingQuery->select(DB::raw(1))
                            ->from('pt_bookings')
                            ->whereColumn('pt_bookings.membership_id', 'memberships.id')
                            ->where('pt_bookings.pt_id', $this->user->id)
                            ->whereIn('pt_bookings.attendance', ['attended', 'noshow'])
                            ->whereBetween('pt_bookings.booking_date', [
                                $this->dateStart.' 00:00:00',
                                $this->dateEnd.' 23:59:59',
                            ]);
                    });
                });
            })
            ->when($this->search, function ($query) {
                $query->whereHas('user', function ($q) {
                    $q->where('name', 'like', '%'.$this->search.'%');
                });
            })
            ->with(['user', 'ptPackage', 'gymPackage', 'followUp', 'followUpTwo', 'members'])
            ->withCount([
                'ptBookings as berjalan' => function ($q) {
                    $q->where('attendance', 'attended')
                        ->where('is_free', false)
                        ->where('pt_id', $this->user->id);
                    if ($this->dateStart && $this->dateEnd) {
                        $q->whereBetween('booking_date', [
                            $this->dateStart.' 00:00:00',
                            $this->dateEnd.' 23:59:59',
                        ]);
                    }
                },
                'ptBookings as berjalan_dibayar' => function ($q) {
                    $q->where('attendance', 'attended')
                        ->where('is_free', false)
                        ->where('is_paid', true)
                        ->where('pt_id', $this->user->id);
                    if ($this->dateStart && $this->dateEnd) {
                        $q->whereBetween('booking_date', [
                            $this->dateStart.' 00:00:00',
                            $this->dateEnd.' 23:59:59',
                        ]);
                    }
                },
                'ptBookings as berjalan_belum_dibayar' => function ($q) {
                    $q->where('attendance', 'attended')
                        ->where('is_free', false)
                        ->where('is_paid', false)
                        ->where('pt_id', $this->user->id);
                    if ($this->dateStart && $this->dateEnd) {
                        $q->whereBetween('booking_date', [
                            $this->dateStart.' 00:00:00',
                            $this->dateEnd.' 23:59:59',
                        ]);
                    }
                },
                'ptBookings as hangus' => function ($q) {
                    $q->where('attendance', 'noshow')
                        ->where('pt_id', $this->user->id);
                    if ($this->dateStart && $this->dateEnd) {
                        $q->whereBetween('booking_date', [
                            $this->dateStart.' 00:00:00',
                            $this->dateEnd.' 23:59:59',
                        ]);
                    }
                },
                'ptBookings as bookings_before' => function ($q) {
                    $q->whereIn('attendance', ['attended', 'noshow'])
                        ->where('is_free', false);
                    if ($this->dateStart) {
                        $q->whereDate('booking_date', '<', $this->dateStart);
                    }
                },
                'ptBookings as free_total' => function ($q) {
                    $q->where('is_free', true)
                        ->where('pt_id', $this->user->id);
                },
                'ptBookings as free_berjalan' => function ($q) {
                    $q->where('is_free', true)
                        ->where('attendance', 'attended')
                        ->where('pt_id', $this->user->id);
                },
                'ptBookings as sesi_digantikan' => function ($q) {
                    $q->where('attendance', 'attended')
                        ->where('is_free', false)
                        ->where('pt_id', '!=', $this->user->id);
                    if ($this->dateStart && $this->dateEnd) {
                        $q->whereBetween('booking_date', [
                            $this->dateStart.' 00:00:00',
                            $this->dateEnd.' 23:59:59',
                        ]);
                    }
                },
            ]);
    }

    #[Computed]
    public function ptMembershipsDirect(): Collection
    {
        return $this->getMembershipQuery()
            ->where('pt_id', $this->user->id)
            ->orderBy(
                User::select('name')
                    ->whereColumn('users.id', 'memberships.user_id')
                    ->limit(1)
            )
            ->orderBy('created_at', 'desc')
            ->get();
    }

    #[Computed]
    public function ptMembershipsBookingOnly(): Collection
    {
        return $this->getMembershipQuery()
            ->where('pt_id', '!=', $this->user->id)
            ->whereExists(function ($subQuery) {
                $subQuery->select(DB::raw(1))
                    ->from('pt_bookings')
                    ->whereColumn('pt_bookings.membership_id', 'memberships.id')
                    ->where('pt_bookings.pt_id', $this->user->id);
            })
            ->orderBy(
                User::select('name')
                    ->whereColumn('users.id', 'memberships.user_id')
                    ->limit(1)
            )
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
