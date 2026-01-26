<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identity_no' => ['required', 'string'],
            'password'    => ['required', 'string'],
        ];
    }

    /**
     * ERP Login Flow:
     * 1. Authenticate credentials
     * 2. If no MFA → redirect to MFA Setup
     * 3. If MFA exists → redirect to MFA Verify
     */
    public function authenticate(): string
    {
        $this->ensureIsNotRateLimited();

        $identity = $this->identity_no;
        $password = $this->password;

        $user = \App\Models\User::where('identity_no', $identity)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'identity_no' => __('auth.failed'),
            ]);
        }

        // Normal login using password
        if (\Auth::attempt(
            $this->only('identity_no', 'password'),
            $this->boolean('remember')
        )) {
            RateLimiter::clear($this->throttleKey());

            // CASE 1: No secret → must setup MFA
            if (!$user->google2fa_secret) {
                return 'mfa.setup';
            }

            // CASE 2: Secret exists BUT MFA not activated yet
            if (!$user->mfa_enabled) {
                return 'mfa.setup';
            }

            // CASE 3: MFA fully enabled → go to verification
            return 'mfa.verify';
        }

        // Wrong password
        RateLimiter::hit($this->throttleKey());

        throw ValidationException::withMessages([
            'password' => __('auth.failed'),
        ]);
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'identity_no' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::lower($this->string('identity_no')).'|'.$this->ip();
    }
}
