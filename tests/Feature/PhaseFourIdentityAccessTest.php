<?php

use App\Models\AuthenticationEvent;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Features;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    RateLimiter::clear('login');
});

test('fortify exposes only the approved identity features', function () {
    expect(Features::enabled(Features::resetPasswords()))->toBeTrue()
        ->and(Features::enabled(Features::emailVerification()))->toBeTrue()
        ->and(Features::enabled(Features::updatePasswords()))->toBeTrue()
        ->and(Features::enabled(Features::twoFactorAuthentication()))->toBeTrue()
        ->and(Features::enabled(Features::registration()))->toBeFalse()
        ->and(Features::enabled(Features::passkeys()))->toBeFalse();

    $this->get('/login')->assertOk()->assertSee('Masuk');
    $this->get('/register')->assertNotFound();
});

test('an active verified user can sign in and the sensitive audit fields are encrypted', function () {
    $user = User::factory()->create(['password' => Hash::make('ValidPassword!2026')]);

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.44'])
        ->post('/login', ['email' => $user->email, 'password' => 'ValidPassword!2026'])
        ->assertRedirect('/home');

    $this->assertAuthenticatedAs($user);
    expect(AuthenticationEvent::query()->where('type', 'login.succeeded')->count())->toBe(1);

    $event = AuthenticationEvent::query()->where('type', 'login.succeeded')->firstOrFail();
    $raw = DB::table('authentication_events')->find($event->getKey());

    expect($event->ip_address)->toBe('192.0.2.44')
        ->and($raw->ip_address)->not->toBe('192.0.2.44')
        ->and($raw->email_hash)->not->toBe($user->email)
        ->and((string) $raw->context)->not->toContain($user->email);
});

test('inactive accounts fail closed with a generic login error', function () {
    $user = User::factory()->create([
        'password' => Hash::make('ValidPassword!2026'),
        'is_active' => false,
        'deactivated_at' => now(),
    ]);

    $this->from('/login')
        ->post('/login', ['email' => $user->email, 'password' => 'ValidPassword!2026'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors('email');

    $this->assertGuest();
    expect(AuthenticationEvent::query()->where('type', 'login.failed')->count())->toBe(1);
});

test('repeated invalid passwords temporarily lock the account', function () {
    config()->set('security.login.max_attempts', 3);
    config()->set('security.login.rate_limit_per_minute', 10);
    $user = User::factory()->create(['password' => Hash::make('ValidPassword!2026')]);

    foreach (range(1, 3) as $attempt) {
        $this->post('/login', ['email' => $user->email, 'password' => 'WrongPassword!2026']);
    }

    $user->refresh();

    expect($user->failed_login_attempts)->toBe(3)
        ->and($user->isTemporarilyLocked())->toBeTrue()
        ->and(AuthenticationEvent::query()->where('type', 'account.temporarily_locked')->count())->toBe(1);

    $this->post('/login', ['email' => $user->email, 'password' => 'ValidPassword!2026']);
    $this->assertGuest();
});

test('an expired account lock starts a fresh failure counter', function () {
    $user = User::factory()->create([
        'password' => Hash::make('ValidPassword!2026'),
        'failed_login_attempts' => 5,
        'locked_until' => now()->subMinute(),
    ]);

    $this->post('/login', ['email' => $user->email, 'password' => 'WrongPassword!2026']);

    $user->refresh();
    expect($user->failed_login_attempts)->toBe(1)
        ->and($user->locked_until)->toBeNull();
});

test('password reset responses do not disclose whether an account exists', function () {
    Notification::fake();
    $user = User::factory()->create();

    $existing = $this->post('/forgot-password', ['email' => $user->email]);
    $missing = $this->post('/forgot-password', ['email' => 'missing@example.test']);

    $existing->assertSessionHas('status', __('auth.reset_link_generic'));
    $missing->assertSessionHas('status', __('auth.reset_link_generic'));
    Notification::assertSentTo($user, ResetPassword::class);
});

test('email verification completes and is recorded', function () {
    $user = User::factory()->unverified()->create();
    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(30),
        ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())],
    );

    $this->actingAs($user)->get($url)->assertRedirect('/home?verified=1');

    expect($user->refresh()->hasVerifiedEmail())->toBeTrue()
        ->and(AuthenticationEvent::query()->where('type', 'email.verified')->count())->toBe(1);
});

test('two factor setup creates encrypted secrets and recovery codes', function () {
    $this->mock(TwoFactorAuthenticationProvider::class, function ($mock): void {
        $mock->shouldReceive('generateSecretKey')->once()->andReturn('TOP-SECRET-TOTP-KEY');
    });
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post('/user/two-factor-authentication')
        ->assertRedirect();

    $user->refresh();
    expect($user->two_factor_secret)->not->toBeNull()
        ->and($user->two_factor_secret)->not->toContain('TOP-SECRET-TOTP-KEY')
        ->and($user->two_factor_recovery_codes)->not->toBeNull()
        ->and(AuthenticationEvent::query()->where('type', 'two_factor.setup_started')->count())->toBe(1);

    $user->forceFill(['two_factor_confirmed_at' => now()])->save();
    $firstRecoveryCode = $user->recoveryCodes()[0];

    $this->actingAs($user)
        ->get('/security')
        ->assertOk()
        ->assertDontSee($firstRecoveryCode);

    $this->withSession(['auth.password_confirmed_at' => time()])
        ->get('/security/recovery-codes')
        ->assertOk()
        ->assertSee($firstRecoveryCode);
});

test('the role seeder is idempotent and grants least privilege', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(RolePermissionSeeder::class);

    expect(Role::query()->count())->toBe(10)
        ->and(Permission::query()->count())->toBe(15);

    $employee = User::factory()->create();
    $employee->assignRole('Employee');
    $reviewer = User::factory()->create();
    $reviewer->assignRole('Finance Reviewer');

    expect($employee->can('employees.view'))->toBeFalse()
        ->and($reviewer->can('payroll.review'))->toBeTrue()
        ->and($reviewer->can('payroll.approve'))->toBeFalse()
        ->and($reviewer->can('iam.manage'))->toBeFalse();
});

test('only authorized administrators can access IAM and provision users', function () {
    Notification::fake();
    $this->seed(RolePermissionSeeder::class);
    $employee = User::factory()->create();
    $employee->assignRole('Employee');
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');

    $this->actingAs($employee)->get('/iam/users')->assertForbidden();

    $this->actingAs($admin)->post('/iam/users', [
        'name' => 'Provisioned User',
        'email' => 'provisioned@example.test',
        'role' => 'Auditor',
    ])->assertRedirect();

    $provisioned = User::query()->where('email', 'provisioned@example.test')->firstOrFail();
    expect($provisioned->hasRole('Auditor'))->toBeTrue()
        ->and($provisioned->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($provisioned, ResetPassword::class);
});

test('group HR administrators cannot grant protected or financial roles', function () {
    $this->seed(RolePermissionSeeder::class);
    $groupHr = User::factory()->create();
    $groupHr->assignRole('Group HR Admin');

    foreach (['Super Admin', 'Payroll Administrator', 'Finance Reviewer', 'Final Payroll Approver', 'Auditor'] as $role) {
        $email = Str::slug($role).'@example.test';

        $this->actingAs($groupHr)->post('/iam/users', [
            'name' => $role.' Candidate',
            'email' => $email,
            'role' => $role,
        ])->assertSessionHasErrors('role');

        expect(User::query()->where('email', $email)->exists())->toBeFalse();
    }

    $this->actingAs($groupHr)->post('/iam/users', [
        'name' => 'Employee Candidate',
        'email' => 'employee.candidate@example.test',
        'role' => 'Employee',
    ])->assertSessionDoesntHaveErrors();

    expect(User::query()->where('email', 'employee.candidate@example.test')->firstOrFail()->hasRole('Employee'))
        ->toBeTrue();
});

test('the bootstrap command creates a verified super admin without a password argument', function () {
    $this->artisan('iam:bootstrap-admin', [
        'email' => 'first.admin@example.test',
        '--name' => 'First Administrator',
    ])
        ->expectsQuestion('Password', 'BootstrapPassword!2026')
        ->expectsQuestion('Confirm password', 'BootstrapPassword!2026')
        ->assertSuccessful();

    $admin = User::query()->where('email', 'first.admin@example.test')->firstOrFail();

    expect($admin->hasVerifiedEmail())->toBeTrue()
        ->and($admin->hasRole('Super Admin'))->toBeTrue()
        ->and(Hash::check('BootstrapPassword!2026', $admin->password))->toBeTrue();
});

test('deactivating an account revokes every database session', function () {
    $user = User::factory()->create();
    DB::table('sessions')->insert([
        'id' => 'server-session-id',
        'user_id' => $user->getKey(),
        'ip_address' => '192.0.2.10',
        'user_agent' => 'Test browser',
        'payload' => 'encrypted-payload',
        'last_activity' => now()->timestamp,
    ]);

    $user->forceFill(['is_active' => false, 'deactivated_at' => now()])->save();

    expect(DB::table('sessions')->where('user_id', $user->getKey())->exists())->toBeFalse();
});

test('authentication events are immutable through the application model', function () {
    $event = AuthenticationEvent::query()->create([
        'type' => 'security.event',
        'occurred_at' => now(),
    ]);

    expect(fn () => $event->update(['type' => 'tampered']))
        ->toThrow(LogicException::class, 'append-only')
        ->and(fn () => $event->delete())
        ->toThrow(LogicException::class, 'append-only');
});

test('the phase four review package is complete and contains no open placeholders', function () {
    $documents = [
        'identity-and-authentication.md',
        'role-permission-matrix.md',
        'audit-and-sensitive-data.md',
        'operations-runbook.md',
        'phase-4-exit-review.md',
    ];

    foreach ($documents as $document) {
        $contents = file_get_contents(base_path('docs/04-identity-access-audit/'.$document));

        expect($contents)->toBeString()
            ->not->toContain('[TODO]', '[TBD]')
            ->and(strlen((string) $contents))->toBeGreaterThan(500);
    }
});
