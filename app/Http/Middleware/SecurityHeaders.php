<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers on every response (doc 08 Part A). CSP is skipped in local dev:
 * Vite's dev server (HMR script/style injection + websocket) runs on a different origin/port
 * that Chrome won't reliably let a CSP source list describe (it rejects bracketed IPv6
 * literals like http://[::1]:5173 as an "invalid source", and the dev server's actual bound
 * address varies by machine) — chasing that is fighting the tool for no real security benefit
 * on a single-developer localhost. Vite::useCspNonce() stamps the same nonce onto every tag
 *
 * @vite()/@viteReactRefresh render, so in non-local environments (where assets are pre-built
 * and served same-origin) the CSP stays strict with no 'unsafe-inline' needed for scripts.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        if (! app()->environment('local')) {
            $response->headers->set('Content-Security-Policy', $this->csp($nonce));
        }

        if (app()->environment('production') && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function csp(string $nonce): string
    {
        $script = "'self' 'nonce-{$nonce}'";
        $style = "'self' 'unsafe-inline' 'nonce-{$nonce}'";

        return "default-src 'self'; img-src 'self' data:; font-src 'self'; style-src {$style}; script-src {$script}; connect-src 'self'; frame-ancestors 'none'";
    }
}
