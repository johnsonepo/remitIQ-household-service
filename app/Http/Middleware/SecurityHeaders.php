<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ============================================================================
 * SecurityHeaders Middleware
 * ============================================================================
 *
 * Adds common HTTP security headers to every response.
 *
 * These headers help protect the API against common web attacks and
 * communicate browser security policies.
 *
 * ============================================================================
 */
class SecurityHeaders
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        // Instructs browsers/clients to only ever connect over HTTPS,
        // preventing downgrade attacks. Only meaningful in production
        // (over an actual HTTPS connection) — harmless to send in
        // local dev over HTTP, browsers just ignore it there.
        if (app()->environment('production')) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains',
            );
        }

        // A minimal CSP appropriate for a pure JSON API — no scripts,
        // styles, or frames should ever be rendered here.
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'none'; frame-ancestors 'none'",
        );

        return $response;
    }
}
