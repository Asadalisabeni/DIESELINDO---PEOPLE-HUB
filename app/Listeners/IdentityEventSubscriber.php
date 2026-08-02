<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Identity\AuthenticationAudit;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Fortify\Events\PasswordUpdatedViaController;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;

class IdentityEventSubscriber
{
    public function __construct(
        private readonly AuthenticationAudit $audit,
        private readonly DatabaseManager $database,
        private readonly Request $request,
    ) {}

    public function handleFailed(Failed $event): void
    {
        $email = Str::lower(trim((string) ($event->credentials['email'] ?? '')));
        $reason = (string) $this->request->attributes->get('auth.failure_reason', 'credentials');
        $user = $event->user instanceof User
            ? $event->user
            : User::query()->where('email', $email)->first();

        if ($user !== null && $reason === 'credentials' && $user->is_active && ! $user->isTemporarilyLocked()) {
            $this->database->transaction(function () use ($user): void {
                /** @var User|null $lockedUser */
                $lockedUser = User::query()->lockForUpdate()->find($user->getKey());

                if ($lockedUser === null || ! $lockedUser->is_active || $lockedUser->isTemporarilyLocked()) {
                    return;
                }

                $attempts = $lockedUser->failed_login_attempts + 1;
                $lockedUser->forceFill([
                    'failed_login_attempts' => $attempts,
                    'locked_until' => $attempts >= (int) config('security.login.max_attempts')
                        ? now()->addMinutes((int) config('security.login.lock_minutes'))
                        : null,
                ])->save();

                if ($lockedUser->isTemporarilyLocked()) {
                    $this->audit->record('account.temporarily_locked', $lockedUser, $this->request);
                }
            });
        }

        $this->audit->record('login.failed', $user, $this->request, $email ?: null, [
            'reason' => in_array($reason, ['credentials', 'inactive', 'locked'], true) ? $reason : 'credentials',
        ]);
    }

    public function handleLogin(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
        ])->save();

        $this->audit->record('login.succeeded', $event->user, $this->request);
    }

    public function handleLogout(Logout $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;
        $this->audit->record('logout.completed', $user, $this->request);
    }

    public function handleLockout(Lockout $event): void
    {
        $this->audit->record(
            'login.rate_limited',
            null,
            $event->request,
            (string) $event->request->input('email'),
        );
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;
        $this->audit->record('password.reset', $user, $this->request);
    }

    public function handleVerified(Verified $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;
        $this->audit->record('email.verified', $user, $this->request);
    }

    public function handlePasswordUpdated(PasswordUpdatedViaController $event): void
    {
        $this->recordFortifyEvent($event, 'password.updated');
    }

    public function handleRecoveryCodesGenerated(RecoveryCodesGenerated $event): void
    {
        $this->recordFortifyEvent($event, 'two_factor.recovery_codes_regenerated');
    }

    public function handleTwoFactorChallenged(TwoFactorAuthenticationChallenged $event): void
    {
        $this->recordFortifyEvent($event, 'two_factor.challenged');
    }

    public function handleTwoFactorConfirmed(TwoFactorAuthenticationConfirmed $event): void
    {
        $this->recordFortifyEvent($event, 'two_factor.confirmed');
    }

    public function handleTwoFactorDisabled(TwoFactorAuthenticationDisabled $event): void
    {
        $this->recordFortifyEvent($event, 'two_factor.disabled');
    }

    public function handleTwoFactorEnabled(TwoFactorAuthenticationEnabled $event): void
    {
        $this->recordFortifyEvent($event, 'two_factor.setup_started');
    }

    public function handleTwoFactorFailed(TwoFactorAuthenticationFailed $event): void
    {
        $this->recordFortifyEvent($event, 'two_factor.failed');
    }

    private function recordFortifyEvent(object $event, string $type): void
    {
        $user = property_exists($event, 'user') && $event->user instanceof User
            ? $event->user
            : null;

        $this->audit->record($type, $user, $this->request);
    }
}
