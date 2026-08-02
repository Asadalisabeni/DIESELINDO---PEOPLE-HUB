<?php

namespace App\Services\Identity;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AccountAuthenticator
{
    private static ?string $dummyHash = null;

    public function attempt(Request $request): ?User
    {
        $email = Str::lower(trim((string) $request->input('email')));
        $user = User::query()->where('email', $email)->first();

        $passwordHash = $user?->getAuthPassword()
            ?? (self::$dummyHash ??= Hash::make(Str::random(48)));
        $validPassword = Hash::check((string) $request->input('password'), $passwordHash);

        if ($user !== null && $user->locked_until !== null && ! $user->isTemporarilyLocked()) {
            $user->forceFill([
                'failed_login_attempts' => 0,
                'locked_until' => null,
            ])->save();
        }

        if ($user === null || ! $validPassword) {
            $request->attributes->set('auth.failure_reason', 'credentials');

            return null;
        }

        if (! $user->is_active || $user->deactivated_at !== null) {
            $request->attributes->set('auth.failure_reason', 'inactive');

            return null;
        }

        if ($user->isTemporarilyLocked()) {
            $request->attributes->set('auth.failure_reason', 'locked');

            return null;
        }

        if ($user->failed_login_attempts > 0) {
            $user->forceFill([
                'failed_login_attempts' => 0,
            ])->save();
        }

        return $user;
    }
}
