<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    // Gunakan ...$roles untuk menangkap semua parameter yang dipisahkan koma menjadi array
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // 1. Cek apakah user sudah login
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        // Token head_coach mewakili akun khusus berbasis email, bukan role database.
        $allowedRoles = array_diff($roles, ['head_coach']);
        $hasHeadCoachAccess = in_array('head_coach', $roles, true) && $user->isHeadCoach();

        if (! in_array($user->role, $allowedRoles, true) && ! $hasHeadCoachAccess) {
            return redirect()->route('home'); // Atau bisa pakai abort(403) agar lebih tepat
        }

        return $next($request);
    }
}
