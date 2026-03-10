<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class EnsureUserRole
{
    // Gunakan ...$roles untuk menangkap semua parameter yang dipisahkan koma menjadi array
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // 1. Cek apakah user sudah login
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        // 2. Cek apakah role user ada di dalam daftar roles yang diizinkan
        if (!in_array(Auth::user()->role, $roles)) {
            return redirect()->route('home'); // Atau bisa pakai abort(403) agar lebih tepat
        }

        return $next($request);
    }
}