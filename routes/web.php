<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\SecurityController;
use App\Http\Controllers\UserAccessController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('home') : redirect()->route('login');
})->name('root');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::view('/home', 'welcome')->name('home');
    Route::view('/design-system', 'design-system.index')->name('design-system');

    Route::get('/security', [SecurityController::class, 'index'])->name('security.index');
    Route::get('/security/recovery-codes', [SecurityController::class, 'recoveryCodes'])
        ->middleware('password.confirm')
        ->name('security.recovery-codes');
    Route::delete('/security/sessions', [SecurityController::class, 'destroyOtherSessions'])
        ->name('security.sessions.destroy-others');

    Route::get('/iam/users', [UserAccessController::class, 'index'])->name('iam.users.index');
    Route::post('/iam/users', [UserAccessController::class, 'store'])->name('iam.users.store');
    Route::put('/iam/users/{user}', [UserAccessController::class, 'update'])->name('iam.users.update');

    Route::get('/audit', AuditLogController::class)->name('audit.index');
});

Route::post('/locale', function (Request $request): RedirectResponse {
    $validated = $request->validate([
        'locale' => ['required', 'string', 'in:id,en'],
    ]);

    $request->session()->put('locale', $validated['locale']);

    return back();
})->name('locale.update');
