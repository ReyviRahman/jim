<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class ImpersonationController extends Controller
{
    public function start(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('impersonate', $user);

        $adminId = $request->user()->getAuthIdentifier();
        Auth::guard('web')->logoutCurrentDevice();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::guard('web')->login($user);
        $request->session()->put('impersonator_id', $adminId);

        Log::info('impersonation.started', [
            'admin_id' => $adminId,
            'target_id' => $user->getKey(),
            'at' => now()->toIso8601String(),
        ]);

        return redirect()->route($user->dashboardRoute());
    }

    public function stop(Request $request): RedirectResponse
    {
        Gate::authorize('stop-impersonating');

        $adminId = $request->session()->get('impersonator_id');
        $targetId = $request->user()->getAuthIdentifier();
        $admin = User::query()->whereKey($adminId)->where('role', 'admin')->where('is_active', true)->first();

        Auth::guard('web')->logoutCurrentDevice();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Log::info('impersonation.stopped', [
            'admin_id' => $adminId,
            'target_id' => $targetId,
            'reason' => $admin ? 'restored' : 'admin_unavailable',
            'at' => now()->toIso8601String(),
        ]);

        if (! $admin) {
            return redirect()->route('login');
        }

        Auth::guard('web')->login($admin);

        return redirect()->route($admin->dashboardRoute());
    }
}
