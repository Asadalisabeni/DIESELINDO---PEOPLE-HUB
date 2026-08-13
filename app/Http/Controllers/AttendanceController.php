<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceSourceType;
use App\Http\Requests\StoreAttendanceEventRequest;
use App\Models\AttendanceEvent;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSource;
use App\Models\Employee;
use App\Models\User;
use App\Services\Attendance\AttendanceManager;
use App\Services\Employee\EmployeeDocumentManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $this->actor($request);
        $employee = $actor->employee;
        abort_unless($employee instanceof Employee && $actor->can('attendance.access'), 403);
        $records = AttendanceRecord::query()
            ->where('employee_id', $employee->getKey())->where('is_current', true)
            ->with(['scheduleAssignment.schedule', 'workSchedule'])->latest('work_date')->paginate(31);

        return view('attendance.index', [
            'employee' => $employee->load('legalEntity'),
            'records' => $records,
            'monthLateMinutes' => AttendanceRecord::query()
                ->where('employee_id', $employee->getKey())->where('is_current', true)
                ->whereBetween('work_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
                ->sum('late_minutes'),
            'sources' => AttendanceSource::query()
                ->where('legal_entity_id', $employee->legal_entity_id)->where('status', 'active')
                ->whereIn('type', [
                    AttendanceSourceType::Web->value,
                    AttendanceSourceType::MobileGps->value,
                    AttendanceSourceType::OfflineMobile->value,
                ])->orderBy('name')->get(),
            'corrections' => $employee->attendanceCorrections()->with('record')->limit(10)->get(),
        ]);
    }

    public function store(
        StoreAttendanceEventRequest $request,
        AttendanceManager $manager,
        EmployeeDocumentManager $documents,
    ): RedirectResponse|JsonResponse {
        $actor = $this->actor($request);
        $employee = $actor->employee;
        abort_unless($employee instanceof Employee, 403);
        $source = AttendanceSource::query()
            ->where('legal_entity_id', $employee->legal_entity_id)->where('status', 'active')
            ->where('public_id', $request->string('source_public_id'))->firstOrFail();
        if ($request->boolean('was_offline') && $request->hasFile('selfie')) {
            throw ValidationException::withMessages(['selfie' => __('attendance.validation.offline_selfie')]);
        }

        $data = $request->safe()->except(['selfie']);
        $file = $request->file('selfie');
        $eventAlreadyExists = AttendanceEvent::query()
            ->where('attendance_source_id', $source->getKey())
            ->where('external_event_id', trim((string) $request->input('external_event_id')))
            ->exists();
        if ($file && ! $eventAlreadyExists) {
            $document = $documents->store($actor, $employee, $file, [
                'type' => 'attendance_selfie',
                'classification' => 'restricted',
                'issued_date' => now()->toDateString(),
            ]);
            $data['selfie_document_id'] = $document->getKey();
        }
        $result = $manager->ingest($actor, $employee, $source, $data);

        if ($request->expectsJson()) {
            return response()->json([
                'event_public_id' => $result['event']->public_id,
                'record_public_id' => $result['record']->public_id,
                'duplicate' => $result['duplicate'],
                'status' => $result['event']->eventStatus()->value,
            ], $result['duplicate'] ? 200 : 201);
        }

        return redirect()->route('attendance.index')->with('status',
            $result['duplicate'] ? __('attendance.status.duplicate') : __('attendance.status.recorded'));
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
