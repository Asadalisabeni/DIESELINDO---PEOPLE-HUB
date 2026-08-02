<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Http\Responses\GenericPasswordResetLinkResponse;
use App\Services\Identity\AccountAuthenticator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            SuccessfulPasswordResetLinkRequestResponse::class,
            static fn (): GenericPasswordResetLinkResponse => new GenericPasswordResetLinkResponse,
        );
        $this->app->bind(
            FailedPasswordResetLinkRequestResponse::class,
            static fn (): GenericPasswordResetLinkResponse => new GenericPasswordResetLinkResponse,
        );
    }

    public function boot(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::viewPrefix('auth.');
        Fortify::authenticateUsing(fn (Request $request) => app(AccountAuthenticator::class)->attempt($request));

        Password::defaults(function (): Password {
            $rule = Password::min((int) config('security.password.minimum_length'))
                ->mixedCase()
                ->numbers()
                ->symbols();

            return config('security.password.check_compromised') ? $rule->uncompromised() : $rule;
        });

        RateLimiter::for('login', function (Request $request): Limit {
            $email = Str::transliterate(Str::lower((string) $request->input(Fortify::username())));

            return Limit::perMinute((int) config('security.login.rate_limit_per_minute'))
                ->by($email.'|'.$request->ip());
        });

        RateLimiter::for('two-factor', function (Request $request): Limit {
            return Limit::perMinute(5)->by((string) $request->session()->get('login.id'));
        });
    }
}
