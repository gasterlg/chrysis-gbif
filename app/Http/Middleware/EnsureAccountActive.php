<?php

namespace App\Http\Middleware;

use App\Models\AccountPermission;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Logs out a user whose account was disabled on the Users screen. */
class EnsureAccountActive
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check() && AccountPermission::query()->where('account_id', Auth::id())->value('disabled')) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('toast', ['type' => 'error', 'message' => 'Your account is disabled.']);
        }

        return $next($request);
    }
}
