<?php

namespace App\Livewire\Forms;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Form;

class LoginForm extends Form
{
    /** Username or e-mail address from accounts_api. */
    #[Validate('required|string|max:100')]
    public string $login = '';

    #[Validate('required|string|max:200')]
    public string $password = '';

    public const MAX_ATTEMPTS = 5;

    public function authenticate(): void
    {
        $this->validate();
        $key = $this->throttleKey();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            event(new Lockout(request()));
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'form.login' => "Too many login attempts. Try again in {$seconds} seconds.",
            ]);
        }

        $field = str_contains($this->login, '@') ? 'email' : 'username';

        if (! Auth::attempt([$field => $this->login, 'password' => $this->password])) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['form.login' => 'These credentials do not match our records.']);
        }

        RateLimiter::clear($key);
    }

    private function throttleKey(): string
    {
        return 'login:'.Str::transliterate(Str::lower($this->login)).'|'.request()->ip();
    }
}
