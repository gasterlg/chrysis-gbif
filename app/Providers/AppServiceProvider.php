<?php

namespace App\Providers;

use App\Auth\AccountUserProvider;
use App\Gbif\GbifClient;
use App\Models\Account;
use App\Models\AuditLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GbifClient::class, fn () => GbifClient::fromConfig());
    }

    public function boot(): void
    {
        Auth::provider('accounts', fn ($app, array $config) => new AccountUserProvider($app['hash'], $config['model']));

        // Roles: accounts_api.role (Admin / SuperUser); update_gbif lives in the app DB.
        Gate::define('admin', fn (Account $user) => $user->isAdmin());
        Gate::define('update-gbif', fn (Account $user) => $user->canUpdateGbif());

        // Audit of logins (plan section 7).
        Event::listen(Login::class, fn (Login $e) => AuditLog::record('login', $e->user->getAuthIdentifier(), $e->user->username ?? null));
        Event::listen(Logout::class, function (Logout $e) {
            if ($e->user) {
                AuditLog::record('logout', $e->user->getAuthIdentifier(), $e->user->username ?? null);
            }
        });
        Event::listen(Failed::class, fn (Failed $e) => AuditLog::record('login_failed', $e->user?->getAuthIdentifier(), $e->credentials['username'] ?? $e->credentials['email'] ?? null));
    }
}
