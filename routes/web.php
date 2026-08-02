<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeDocumentController;
use App\Http\Controllers\EmploymentAssignmentController;
use App\Http\Controllers\EssChangeRequestController;
use App\Http\Controllers\EssDashboardController;
use App\Http\Controllers\EssProfileController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NotificationCenterController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\SecurityController;
use App\Http\Controllers\UserAccessController;
use App\Http\Controllers\UserLegalEntityAccessController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('home') : redirect()->route('login');
})->name('root');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/home', HomeController::class)->name('home');
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
    Route::post('/iam/users/{user}/legal-entity-access', [UserLegalEntityAccessController::class, 'store'])->name('iam.entity-access.store');
    Route::put('/iam/entity-access/{access}/end', [UserLegalEntityAccessController::class, 'end'])->name('iam.entity-access.end');

    Route::get('/audit', AuditLogController::class)->name('audit.index');

    Route::get('/organization', [OrganizationController::class, 'index'])->name('organization.index');
    Route::post('/organization/legal-entities', [OrganizationController::class, 'store'])->name('organization.legal-entities.store');
    Route::put('/organization/legal-entities/{legalEntity}', [OrganizationController::class, 'update'])->name('organization.legal-entities.update');
    Route::post('/organization/{legalEntity}/units/{unitType}', [OrganizationController::class, 'storeUnit'])->name('organization.units.store');
    Route::put('/organization/{legalEntity}/units/{unitType}/{unit}', [OrganizationController::class, 'updateUnit'])->name('organization.units.update');

    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('/employees/create', [EmployeeController::class, 'create'])->name('employees.create');
    Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
    Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::post('/employees/{employee}/assignments', [EmploymentAssignmentController::class, 'store'])->name('employees.assignments.store');
    Route::post('/employees/{employee}/contracts', [ContractController::class, 'store'])->name('employees.contracts.store');
    Route::post('/employees/{employee}/documents', [EmployeeDocumentController::class, 'store'])->name('employees.documents.store');
    Route::get('/employee-documents/{document}/download', [EmployeeDocumentController::class, 'download'])->name('employee-documents.download');

    Route::get('/ess', EssDashboardController::class)->name('ess.dashboard');
    Route::put('/ess/profile/contact', [EssProfileController::class, 'update'])->name('ess.profile.contact.update');
    Route::get('/ess/requests', [EssChangeRequestController::class, 'index'])->name('ess.requests.index');
    Route::post('/ess/requests', [EssChangeRequestController::class, 'store'])->name('ess.requests.store');
    Route::get('/ess/requests/{changeRequest}', [EssChangeRequestController::class, 'show'])->name('ess.requests.show');
    Route::delete('/ess/requests/{changeRequest}', [EssChangeRequestController::class, 'cancel'])->name('ess.requests.cancel');
    Route::get('/ess-review', [EssChangeRequestController::class, 'reviewIndex'])->name('ess.review.index');
    Route::put('/ess-review/{changeRequest}', [EssChangeRequestController::class, 'review'])->name('ess.review.update');

    Route::get('/notifications', [NotificationCenterController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/read-all', [NotificationCenterController::class, 'readAll'])->name('notifications.read-all');
    Route::patch('/notifications/{notification}/read', [NotificationCenterController::class, 'read'])->name('notifications.read');
});

Route::post('/locale', function (Request $request): RedirectResponse {
    $validated = $request->validate([
        'locale' => ['required', 'string', 'in:id,en'],
    ]);

    $request->session()->put('locale', $validated['locale']);

    return back();
})->name('locale.update');
