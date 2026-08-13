<?php

use App\Http\Controllers\AttendanceAdminController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceCorrectionController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeDocumentController;
use App\Http\Controllers\EmploymentAssignmentController;
use App\Http\Controllers\EssChangeRequestController;
use App\Http\Controllers\EssDashboardController;
use App\Http\Controllers\EssProfileController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LeaveAdminController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\LeaveReviewController;
use App\Http\Controllers\NotificationCenterController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OvertimeAdminController;
use App\Http\Controllers\OvertimeController;
use App\Http\Controllers\OvertimeReviewController;
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

    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/attendance/events', [AttendanceController::class, 'store'])->name('attendance.events.store');
    Route::post('/attendance/sync', [AttendanceController::class, 'store'])->name('attendance.sync.store');
    Route::post('/attendance/corrections', [AttendanceCorrectionController::class, 'store'])->name('attendance.corrections.store');
    Route::delete('/attendance/corrections/{correction}', [AttendanceCorrectionController::class, 'cancel'])->name('attendance.corrections.cancel');
    Route::get('/attendance-review', [AttendanceCorrectionController::class, 'queue'])->name('attendance.review.queue');
    Route::put('/attendance-review/{correction}/manager', [AttendanceCorrectionController::class, 'managerReview'])->name('attendance.review.manager');
    Route::put('/attendance-review/{correction}/hr', [AttendanceCorrectionController::class, 'hrReview'])->name('attendance.review.hr');

    Route::get('/attendance-admin', [AttendanceAdminController::class, 'index'])->name('attendance.admin.index');
    Route::post('/attendance-admin/schedules', [AttendanceAdminController::class, 'storeSchedule'])->name('attendance.admin.schedules.store');
    Route::post('/attendance-admin/sources', [AttendanceAdminController::class, 'storeSource'])->name('attendance.admin.sources.store');
    Route::post('/attendance-admin/holidays', [AttendanceAdminController::class, 'storeHoliday'])->name('attendance.admin.holidays.store');
    Route::post('/attendance-admin/assignments', [AttendanceAdminController::class, 'assignSchedule'])->name('attendance.admin.assignments.store');
    Route::post('/attendance-admin/imports', [AttendanceAdminController::class, 'import'])->name('attendance.admin.imports.store');

    Route::get('/leave', [LeaveController::class, 'index'])->name('leave.index');
    Route::post('/leave/requests', [LeaveController::class, 'store'])->name('leave.requests.store');
    Route::delete('/leave/requests/{leaveRequest}', [LeaveController::class, 'cancel'])->name('leave.requests.cancel');
    Route::get('/leave-review', [LeaveReviewController::class, 'index'])->name('leave.review.index');
    Route::put('/leave-review/{leaveRequest}', [LeaveReviewController::class, 'review'])->name('leave.review.update');
    Route::get('/leave-evidence/{leaveRequest}/download', [LeaveReviewController::class, 'downloadEvidence'])->name('leave.evidence.download');

    Route::get('/leave-admin', [LeaveAdminController::class, 'index'])->name('leave.admin.index');
    Route::post('/leave-admin/types', [LeaveAdminController::class, 'storeType'])->name('leave.admin.types.store');
    Route::post('/leave-admin/policies', [LeaveAdminController::class, 'storePolicy'])->name('leave.admin.policies.store');
    Route::post('/leave-admin/entitlements', [LeaveAdminController::class, 'grant'])->name('leave.admin.entitlements.store');
    Route::post('/leave-admin/entitlements/{entitlement}/adjustments', [LeaveAdminController::class, 'adjust'])->name('leave.admin.adjustments.store');
    Route::post('/leave-admin/delegations', [LeaveAdminController::class, 'storeDelegation'])->name('leave.admin.delegations.store');
    Route::put('/leave-admin/delegations/{delegation}/revoke', [LeaveAdminController::class, 'revokeDelegation'])->name('leave.admin.delegations.revoke');
    Route::get('/leave-admin/report.csv', [LeaveAdminController::class, 'export'])->name('leave.admin.report.export');

    Route::get('/overtime', [OvertimeController::class, 'index'])->name('overtime.index');
    Route::post('/overtime/requests', [OvertimeController::class, 'store'])->name('overtime.requests.store');
    Route::delete('/overtime/requests/{overtimeRequest}', [OvertimeController::class, 'cancel'])->name('overtime.requests.cancel');
    Route::get('/overtime-review', [OvertimeReviewController::class, 'index'])->name('overtime.review.index');
    Route::put('/overtime-review/{overtimeRequest}', [OvertimeReviewController::class, 'review'])->name('overtime.review.update');

    Route::get('/overtime-admin', [OvertimeAdminController::class, 'index'])->name('overtime.admin.index');
    Route::post('/overtime-admin/rules', [OvertimeAdminController::class, 'storeRule'])->name('overtime.admin.rules.store');
    Route::post('/overtime-admin/delegations', [OvertimeAdminController::class, 'storeDelegation'])->name('overtime.admin.delegations.store');
    Route::put('/overtime-admin/delegations/{delegation}/revoke', [OvertimeAdminController::class, 'revokeDelegation'])->name('overtime.admin.delegations.revoke');
    Route::get('/overtime-admin/report.csv', [OvertimeAdminController::class, 'export'])->name('overtime.admin.report.export');
});

Route::post('/locale', function (Request $request): RedirectResponse {
    $validated = $request->validate([
        'locale' => ['required', 'string', 'in:id,en'],
    ]);

    $request->session()->put('locale', $validated['locale']);

    return back();
})->name('locale.update');
