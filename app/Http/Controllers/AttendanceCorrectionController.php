<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceCorrectionStatus;
use App\Http\Requests\ReviewAttendanceCorrectionRequest;
use App\Http\Requests\StoreAttendanceCorrectionRequest;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmploymentHistory;
use App\Models\User;
use App\Models\UserLegalEntityAccess;
use App\Services\Attendance\AttendanceManager;
use App\Services\Employee\EmployeeDocumentManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AttendanceCorrectionController extends Controller
{
    public function store(
        StoreAttendanceCorrectionRequest $request,
        AttendanceManager $manager,
        EmployeeDocumentManager $documents,
    ): RedirectResponse {
        $actor = $this->actor($request);
        $employee = $actor->employee;
        abort_unless($employee instanceof Employee, 403);
        $record = AttendanceRecord::query()->where('employee_id', $employee->getKey())
            ->where('is_current', true)->where('public_id', $request->string('attendance_record_public_id'))->firstOrFail();
        $evidenceId = null;
        $file = $request->file('evidence');
        if ($file) {
            $evidenceId = $documents->store($actor, $employee, $file, [
                'type' => 'attendance_correction_evidence',
                'classification' => 'restricted',
                'issued_date' => now()->toDateString(),
            ])->getKey();
        }
        $manager->submitCorrection($actor, $employee, $record, $request->safe()->except(['evidence']), $evidenceId);

        return redirect()->route('attendance.index')->with('status', __('attendance.status.correction_submitted'));
    }

    public function queue(Request $request): View
    {
        $actor = $this->actor($request);
        $managedEntityIds = $this->managedEntityIds($actor);
        $managedEmployeeIds = [];
        if ($actor->employee instanceof Employee && $actor->can('attendance.corrections.approve-manager')) {
            $managedEmployeeIds = EmploymentHistory::query()
                ->where('manager_employee_id', $actor->employee->getKey())
                ->effectiveOn(now()->toDateString())->pluck('employee_id')->all();
        }
        abort_if($managedEmployeeIds === [] && $managedEntityIds === [], 403);

        return view('attendance.review', [
            'managerQueue' => AttendanceCorrection::query()
                ->whereIn('employee_id', $managedEmployeeIds)
                ->where('status', AttendanceCorrectionStatus::PendingManager->value)
                ->with(['employee', 'record'])->oldest('submitted_at')->get(),
            'hrQueue' => AttendanceCorrection::query()
                ->whereIn('legal_entity_id', $managedEntityIds)
                ->where('status', AttendanceCorrectionStatus::PendingHr->value)
                ->with(['employee', 'record'])->oldest('submitted_at')->get(),
        ]);
    }

    public function managerReview(
        ReviewAttendanceCorrectionRequest $request,
        string $correction,
        AttendanceManager $manager,
    ): RedirectResponse {
        $actor = $this->actor($request);
        abort_unless($actor->can('attendance.corrections.approve-manager'), 403);
        $employeeIds = $actor->employee instanceof Employee
            ? EmploymentHistory::query()->where('manager_employee_id', $actor->employee->getKey())
                ->effectiveOn(now()->toDateString())->pluck('employee_id')->all()
            : [];
        $record = AttendanceCorrection::query()->whereIn('employee_id', $employeeIds)
            ->where('public_id', $correction)->firstOrFail();
        $manager->managerReview($actor, $record, $request->validated());

        return redirect()->route('attendance.review.queue')->with('status', __('attendance.status.correction_reviewed'));
    }

    public function hrReview(
        ReviewAttendanceCorrectionRequest $request,
        string $correction,
        AttendanceManager $manager,
    ): RedirectResponse {
        $actor = $this->actor($request);
        abort_unless($actor->can('attendance.corrections.review'), 403);
        $record = AttendanceCorrection::query()->whereIn('legal_entity_id', $this->managedEntityIds($actor))
            ->where('public_id', $correction)->firstOrFail();
        $manager->hrReview($actor, $record, $request->validated());

        return redirect()->route('attendance.review.queue')->with('status', __('attendance.status.correction_reviewed'));
    }

    public function cancel(Request $request, string $correction, AttendanceManager $manager): RedirectResponse
    {
        $actor = $this->actor($request);
        $record = AttendanceCorrection::query()->where('requested_by', $actor->getKey())
            ->where('public_id', $correction)->firstOrFail();
        $manager->cancelCorrection($actor, $record);

        return redirect()->route('attendance.index')->with('status', __('attendance.status.correction_cancelled'));
    }

    /** @return list<int> */
    private function managedEntityIds(User $actor): array
    {
        $ids = UserLegalEntityAccess::query()->where('user_id', $actor->getKey())
            ->where('access_level', 'manage')->effectiveOn(now()->toDateString())
            ->pluck('legal_entity_id')->map(static fn (mixed $id): int => (int) $id)->values()->all();

        return array_values($ids);
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
