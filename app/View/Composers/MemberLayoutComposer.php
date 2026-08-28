<?php

namespace App\View\Composers;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MemberLayoutComposer
{
    public function compose(View $view): void
    {
        $user = Auth::user();

        $hasPtMembership = $user instanceof User
            && Membership::query()
                ->where('type', 'pt')
                ->where(function (Builder $query) use ($user): void {
                    $query->whereBelongsTo($user, 'user')
                        ->orWhereHas('members', function (Builder $memberQuery) use ($user): void {
                            $memberQuery->whereKey($user->getKey());
                        });
                })
                ->exists();

        $view->with('hasPtMembership', $hasPtMembership);
    }
}
