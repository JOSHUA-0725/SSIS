<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // A deactivated account loses access on its next request, not only at next login.
        if (Auth::check() && Auth::user()->status !== 'Active') {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['username' => 'Your account has been deactivated.']);
        }

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'same-origin');

        // Inline scripts are blocked, so injected <script> tags cannot run even if one slipped through.
        if ($response->getStatusCode() < 500) {
            $response->headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "script-src 'self'",
                "style-src 'self' https://cdn.jsdelivr.net https://fonts.googleapis.com",
                "font-src 'self' https://fonts.gstatic.com",
                "img-src 'self' data:",
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self'",
                "frame-ancestors 'none'",
            ]));
        }

        return $response;
    }
}
