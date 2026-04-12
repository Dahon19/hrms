<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireDeviceTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('services.nfc.require_device_token', false)) {
            return $next($request);
        }

        $configuredTokens = collect((array) config('services.nfc.device_tokens', []))
            ->map(fn ($token) => trim((string) $token))
            ->filter()
            ->values();

        if ($configuredTokens->isEmpty()) {
            if (app()->environment(['local', 'testing']) && config('services.nfc.allow_unprotected_local', true)) {
                return $next($request);
            }

            return $this->deny('NFC device access is not configured.', 503);
        }

        $providedToken = trim((string) (
            $request->header('X-HRMS-Device-Token', '')
            ?: $request->bearerToken()
            ?: $request->input('device_token', '')
            ?: $request->query('device_token', '')
        ));

        if ($providedToken === '') {
            return $this->deny('Device token is required.', 401);
        }

        $isValid = $configuredTokens->contains(
            fn (string $configuredToken) => hash_equals($configuredToken, $providedToken)
        );

        if (!$isValid) {
            return $this->deny('Invalid device token.', 401);
        }

        return $next($request);
    }

    protected function deny(string $message, int $status): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
        ], $status);
    }
}
