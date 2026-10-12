<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * accounts_api holds bcrypt hashes written by two tools: PHP ($2y$) and
 * apisa's bcryptjs ($2a$). Laravel's verifying Bcrypt hasher rejects $2a$,
 * so verification uses password_verify() (as /admin does) and the hash is
 * upgraded to $2y$ by rehashPasswordIfRequired() after a successful login.
 */
class AccountUserProvider extends EloquentUserProvider
{
    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials)
    {
        $user = parent::retrieveByCredentials($credentials);

        // Accounts disabled on the Users screen cannot log in (flag in the app DB).
        if ($user && \App\Models\AccountPermission::query()->where('account_id', $user->getAuthIdentifier())->value('disabled')) {
            return null;
        }

        return $user;
    }

    public function validateCredentials(Authenticatable $user, #[\SensitiveParameter] array $credentials): bool
    {
        $plain = $credentials['password'] ?? null;
        $hash = $user->getAuthPassword();

        if (! is_string($plain) || ! is_string($hash) || $hash === '') {
            return false;
        }

        if (! preg_match('/^\$2[aby]\$\d{2}\$/', $hash)) {
            return false; // only bcrypt is accepted
        }

        return password_verify($plain, $hash);
    }

    public function rehashPasswordIfRequired(Authenticatable $user, #[\SensitiveParameter] array $credentials, bool $force = false): void
    {
        $hash = $user->getAuthPassword();

        if (! $force && str_starts_with($hash, '$2y$') && ! $this->hasher->needsRehash($hash)) {
            return;
        }

        $user->forceFill([
            $user->getAuthPasswordName() => $this->hasher->make($credentials['password']),
        ])->save();
    }
}
