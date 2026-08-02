<?php

namespace App\Http\Controllers;

use App\Http\Requests\RevokeOtherSessionsRequest;
use App\Models\AuthenticationEvent;
use App\Models\User;
use App\Services\Identity\AuthenticationAudit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SecurityController extends Controller
{
    public function index(): View
    {
        /** @var User $user */
        $user = request()->user();
        $sessions = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->getKey())
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn (object $session): array => [
                'id' => $session->id,
                'is_current' => hash_equals((string) $session->id, request()->session()->getId()),
                'ip_address' => $this->maskIp((string) ($session->ip_address ?? '')),
                'device' => Str::limit((string) ($session->user_agent ?? __('auth.unknown_device')), 90),
                'last_active_at' => Carbon::createFromTimestamp((int) $session->last_activity),
            ]);
        $loginHistory = AuthenticationEvent::query()
            ->where('user_id', $user->getKey())
            ->latest('occurred_at')
            ->limit(20)
            ->get();

        return view('security.index', compact('user', 'sessions', 'loginHistory'));
    }

    public function recoveryCodes(): View
    {
        /** @var User $user */
        $user = request()->user();
        abort_unless($user->hasEnabledTwoFactorAuthentication(), 404);

        return view('security.recovery-codes', [
            'recoveryCodes' => $user->recoveryCodes(),
        ]);
    }

    public function destroyOtherSessions(
        RevokeOtherSessionsRequest $request,
        AuthenticationAudit $audit,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        Auth::logoutOtherDevices((string) $request->validated('current_password'));

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->getKey())
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        $audit->record('session.revoked_others', $user, $request);

        return back()->with('status', __('auth.sessions_revoked'));
    }

    private function maskIp(string $ip): string
    {
        if ($ip === '') {
            return __('auth.unknown_ip');
        }

        if (str_contains($ip, ':')) {
            return implode(':', array_slice(explode(':', $ip), 0, 4)).'::';
        }

        $parts = explode('.', $ip);

        return count($parts) === 4 ? $parts[0].'.'.$parts[1].'.*.*' : '***';
    }
}
