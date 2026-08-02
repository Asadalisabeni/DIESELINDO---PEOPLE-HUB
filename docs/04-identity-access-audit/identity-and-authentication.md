# Identity and authentication

## Decision

Phase 4 uses Laravel Fortify as the headless authentication backend and project-owned Blade views for the bilingual interface. Public registration and passkeys are disabled. Accounts are provisioned by an authorized administrator or by the one-time console bootstrap flow.

The supported flows are:

1. email and password sign-in;
2. email verification;
3. forgot/reset password with an account-enumeration-safe response;
4. password change after current-password confirmation;
5. TOTP two-factor authentication with eight single-use recovery codes, with password reconfirmation before recovery-code display;
6. active-session inventory and revocation of other sessions; and
7. authentication history for the signed-in user.

## Account state

An account is permitted to authenticate only when `is_active = true`, `deactivated_at IS NULL`, and `locked_until` is either null or in the past. The password comparison is still performed before returning an inactive/locked result to reduce timing-based account discovery.

Five invalid passwords within the configured window set a 15-minute application lock. A separate email-plus-IP limiter permits five login attempts per minute. The browser receives a generic credential error; the detailed reason is available only in the encrypted authentication-event context.

Changing or resetting a password revokes other database sessions. Changing `is_active` from true to false deletes all database sessions for that user. `EnsureActiveAccount` also fails closed on every web request, invalidates a stale session, and regenerates the CSRF token.

## Password and session policy

- Minimum 12 characters, upper-case, lower-case, number, and symbol.
- Compromised-password lookup is configurable and must be enabled in staging/production.
- Password reset tokens expire after 60 minutes and are throttled for 60 seconds.
- Database sessions expire after 120 idle minutes by default.
- Session payload encryption, HTTP-only cookies, and `SameSite=Lax` are enabled.
- `SESSION_SECURE_COOKIE=true` is mandatory behind HTTPS in staging/production.
- Laravel's web middleware supplies CSRF protection to every mutating browser route.

## Data minimization

Authentication events never store the raw submitted email. They store an HMAC-SHA-256 lookup value keyed with `APP_KEY`. IP address, user-agent, and the small allow-listed context object are encrypted with Laravel's application encrypter. The account-security and audit screens do not expose those raw values; the current user's session screen shows only a masked IP.

## Package compatibility baseline

- Laravel Fortify `1.37.3` supports Illuminate 13 and PHP 8.2+.
- Spatie Laravel Permission `8.3.0` supports Laravel 12/13 and PHP 8.3+.
- Spatie Laravel Activitylog `4.12.3` supports Illuminate 13 and PHP 8.1+.

These versions are locked in `composer.lock`. Activitylog 5 is intentionally not used because it requires PHP 8.4 while the approved platform is PHP 8.3.30.

References: [Laravel Fortify 13.x](https://laravel.com/docs/13.x/fortify), [Fortify releases](https://github.com/laravel/fortify/releases), [Spatie Permission](https://github.com/spatie/laravel-permission), and [Spatie Activitylog](https://github.com/spatie/laravel-activitylog).
