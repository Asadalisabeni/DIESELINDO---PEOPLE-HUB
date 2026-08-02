<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Identity\AuthenticationAudit;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function __construct(private readonly AuthenticationAudit $audit) {}

    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User || ($user->is_active && $user->deactivated_at === null)) {
            return $next($request);
        }

        $this->audit->record('session.revoked_inactive_account', $user, $request);
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors([
            'email' => __('auth.account_inactive'),
        ]);
    }
}
