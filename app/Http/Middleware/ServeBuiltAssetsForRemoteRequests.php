<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ServeBuiltAssetsForRemoteRequests
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->getHost(), ['localhost', '127.0.0.1', '::1', '[::1]'], true)) {
            return $next($request);
        }

        $vite = app(Vite::class);
        $hotFile = $vite->hotFile();
        $vite->useHotFile('');

        try {
            return $next($request);
        } finally {
            $vite->useHotFile($hotFile);
        }
    }
}
