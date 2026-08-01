<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\Odoo\OdooAuthService;
use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooUserSynchronizer;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'pin_code' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(
        OdooAuthService $odooAuthService,
        OdooUserSynchronizer $odooUserSynchronizer
    ): void
    {
        $this->ensureIsNotRateLimited();

        $odooFailureMessage = null;

        if ($odooAuthService->isConfigured()) {
            try {
                $profile = $odooAuthService->authenticate(
                    trim((string) $this->input('email')),
                    (string) $this->input('pin_code')
                );
            } catch (OdooException $exception) {
                $profile = null;
                $odooFailureMessage = $exception->getMessage();
            }

            if ($profile) {
                $user = $odooUserSynchronizer->sync($profile);

                Auth::login($user, $this->boolean('remember'));

                $this->session()->put('odoo', [
                    'auth_source' => 'odoo',
                    'user_id' => $user->odoo_user_id,
                    'employee_id' => $user->odoo_employee_id,
                    'resource_id' => $user->odoo_resource_id,
                    'role' => $user->role,
                    'is_manager' => $user->isOdooManager(),
                ]);

                RateLimiter::clear($this->throttleKey());

                return;
            }
        } else {
            $odooFailureMessage = 'Employee login is unavailable until the Odoo connection is configured.';
        }

        if (Auth::attempt([
            'email' => trim((string) $this->input('email')),
            'password' => (string) $this->input('pin_code'),
        ], $this->boolean('remember'))) {
            $this->session()->forget('odoo');
            RateLimiter::clear($this->throttleKey());

            return;
        }

        RateLimiter::hit($this->throttleKey());

        $message = $odooFailureMessage ?? trans('auth.failed');

        if (! $odooAuthService->isConfigured() && User::whereRaw('LOWER(email) = ?', [Str::lower((string) $this->input('email'))])->exists()) {
            $message = trans('auth.failed');
        }

        throw ValidationException::withMessages([
            'email' => $message,
        ]);
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
            'email' => trans('auth.throttle', [
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
        return Str::transliterate(Str::lower($this->input('email')).'|'.$this->ip());
    }
}
