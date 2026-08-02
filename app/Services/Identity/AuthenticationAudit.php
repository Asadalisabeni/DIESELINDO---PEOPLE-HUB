<?php

namespace App\Services\Identity;

use App\Models\AuthenticationEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuthenticationAudit
{
    /**
     * @param  array<string, bool|int|string|null>  $context
     */
    public function record(
        string $type,
        ?User $user = null,
        ?Request $request = null,
        ?string $email = null,
        array $context = [],
    ): AuthenticationEvent {
        $request ??= request();
        $normalizedEmail = $email ?? $user?->email;

        return AuthenticationEvent::query()->create([
            'user_id' => $user?->getKey(),
            'type' => $type,
            'email_hash' => $normalizedEmail === null
                ? null
                : hash_hmac('sha256', Str::lower(trim($normalizedEmail)), (string) config('app.key')),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'context' => $context === [] ? null : $context,
            'occurred_at' => now(),
        ]);
    }
}
