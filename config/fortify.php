<?php

use Laravel\Fortify\Features;

return [
    'guard' => 'web',
    'passwords' => 'users',
    'username' => 'email',
    'email' => 'email',
    'lowercase_usernames' => true,
    'home' => '/home',
    'prefix' => '',
    'domain' => null,
    'middleware' => ['web'],
    'views' => true,
    'limiters' => [
        'login' => 'login',
        'two-factor' => 'two-factor',
    ],
    'features' => [
        Features::resetPasswords(),
        Features::emailVerification(),
        Features::updatePasswords(),
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]),
    ],
];
