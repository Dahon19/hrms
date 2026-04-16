<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Services\AuditLogger;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'cf-turnstile-response' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'login.required' => 'Email or Employee ID is required.',
            'password.required' => 'Password is required.',
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $login = (string) $this->input('login');

        $user = User::where('email', $login)->first();
        if (!$user) {
            $user = User::whereHas('employee', function ($query) use ($login) {
                $query->where('employee_id', $login);
            })->first();
        }

        if (!$user || ! Auth::attempt([
            'email' => $user->email,
            'password' => $this->input('password'),
        ], $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());
            AuditLogger::logSystem('login_failed', [
                'login' => $login,
                'reason' => 'invalid_credentials',
            ], $user?->id, $user ? $user->getMorphClass() : 'auth', $user?->getKey() ?? 0);

            throw ValidationException::withMessages([
                'login' => trans('auth.failed'),
            ]);
        }

        if (Auth::user()?->archived_at) {
            Auth::logout();
            RateLimiter::hit($this->throttleKey());
            AuditLogger::logSystem('login_failed', [
                'login' => $login,
                'reason' => 'archived_account',
            ], $user?->id, $user ? $user->getMorphClass() : 'auth', $user?->getKey() ?? 0);

            throw ValidationException::withMessages([
                'login' => 'Your account has been archived. Please contact Head.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('login')).'|'.$this->ip());
    }
}
