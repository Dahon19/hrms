<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        if ($this->isHttpsRequest($request)) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            $this->secureResponseCookies($response);
        }

        return $response;
    }

    protected function isHttpsRequest(Request $request): bool
    {
        $forwardedProto = strtolower((string) $request->header('X-Forwarded-Proto'));
        $forwardedSsl = strtolower((string) $request->header('X-Forwarded-Ssl'));

        return $request->isSecure()
            || str_contains($forwardedProto, 'https')
            || $forwardedSsl === 'on';
    }

    protected function secureResponseCookies(Response $response): void
    {
        /** @var Cookie $cookie */
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->isSecure()) {
                continue;
            }

            $response->headers->setCookie($cookie->withSecure(true));
        }
    }
}
