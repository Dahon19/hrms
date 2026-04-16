<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuditLogger;
use App\Services\AccessControl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $this->ensureTurnstileIsValid($request);
        $request->authenticate();
        $request->session()->regenerate();
        $request->session()->forget('tab_auth');

        $user = Auth::user();
        AuditLogger::log('login', $user, [
            'email' => $user->email,
        ]);
        $hasPasswordNoticeColumn = Schema::hasColumn('users', 'password_notice_seen_at');
        if (
            $hasPasswordNoticeColumn
            && !$user->isAdmin()
            && $user->role === 'employee'
            && is_null($user->password_notice_seen_at)
        ) {
            $user->forceFill([
                'password_notice_seen_at' => now(),
            ])->save();
            $request->session()->flash('show_password_change_notice', true);
        }

        if (!AccessControl::canAccessDashboard($user)) {
            return redirect()->intended(route('attendance.history', [
                'period' => 'weekly',
                'date' => now()->toDateString(),
            ]));
        }

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Validate the Cloudflare Turnstile token before password auth.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private function ensureTurnstileIsValid(Request $request): void
    {
        $siteKey = config('services.turnstile.site_key');
        $secretKey = config('services.turnstile.secret_key');

        if (!filled($siteKey) || !filled($secretKey)) {
            return;
        }

        $token = (string) $request->input('cf-turnstile-response', '');

        if ($token === '') {
            throw ValidationException::withMessages([
                'cf-turnstile-response' => 'Please complete the human verification challenge.',
            ]);
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout(10)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => $secretKey,
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ]);
        } catch (\Throwable $exception) {
            AuditLogger::logSystem('turnstile_verification_failed', [
                'reason' => 'request_exception',
                'message' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'cf-turnstile-response' => 'Human verification is temporarily unavailable. Please try again.',
            ]);
        }

        $payload = $response->json();
        $verified = $response->successful() && is_array($payload) && ($payload['success'] ?? false) === true;

        if ($verified) {
            return;
        }

        AuditLogger::logSystem('turnstile_verification_failed', [
            'reason' => 'verification_rejected',
            'error_codes' => is_array($payload['error-codes'] ?? null) ? $payload['error-codes'] : [],
            'hostname' => $payload['hostname'] ?? null,
        ]);

        throw ValidationException::withMessages([
            'cf-turnstile-response' => 'Human verification failed. Please refresh the page and try again.',
        ]);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if ($user) {
            AuditLogger::log('logout', $user, [
                'email' => $user->email,
            ]);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Graceful fallback for accidental GET /logout hits.
     */
    public function destroyViaGet(Request $request): RedirectResponse
    {
        return $this->destroy($request);
    }
}
