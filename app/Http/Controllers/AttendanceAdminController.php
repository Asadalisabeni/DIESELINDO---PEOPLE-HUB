<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignWorkScheduleRequest;
use App\Http\Requests\ImportAttendanceRequest;
use App\Http\Requests\StoreAttendanceSourceRequest;
use App\Http\Requests\StoreHolidayRequest;
use App\Http\Requests\StoreWorkScheduleRequest;
use App\Models\AttendanceImportBatch;
use App\Models\AttendanceSource;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LegalEntity;
use App\Models\User;
use App\Models\UserLegalEntityAccess;
use App\Models\WorkSchedule;
use App\Services\Attendance\AttendanceManager;
use App\Services\Attendance\X100cCsvImporter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AttendanceAdminController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('attendance.view') || $actor->can('attendance.manage'), 403);
        $entityIds = $this->scopedEntityIds($actor);
        abort_if($entityIds === [], 403);

        return view('attendance.admin', [
            'entities' => LegalEntity::query()->whereIn('id', $entityIds)->orderBy('display_name')->get(),
            'branches' => Branch::query()->whereIn('legal_entity_id', $entityIds)->with('legalEntity')->orderBy('name')->get(),
            'departments' => Department::query()->whereIn('legal_entity_id', $entityIds)->with('legalEntity')->orderBy('name')->get(),
            'schedules' => WorkSchedule::query()->whereIn('legal_entity_id', $entityIds)->with(['legalEntity', 'days'])->latest()->get(),
            'sources' => AttendanceSource::query()->whereIn('legal_entity_id', $entityIds)->with('legalEntity')->latest()->get(),
            'employees' => Employee::query()->whereIn('legal_entity_id', $entityIds)->orderBy('full_name')->get(),
            'imports' => AttendanceImportBatch::query()->whereIn('legal_entity_id', $entityIds)->with('source')->latest()->limit(20)->get(),
            'holidays' => Holiday::query()->whereIn('legal_entity_id', $entityIds)->latest('holiday_date')->limit(30)->get(),
            'canManage' => $actor->can('attendance.manage'),
        ]);
    }

    public function storeSchedule(StoreWorkScheduleRequest $request, AttendanceManager $manager): RedirectResponse
    {
        $actor = $this->actor($request);
        $entity = LegalEntity::query()->whereIn('id', $this->managedEntityIds($actor))
            ->where('public_id', $request->string('legal_entity_public_id'))->firstOrFail();
        $manager->createSchedule($actor, $entity, $request->validated());

        return redirect()->route('attendance.admin.index')->with('status', __('attendance.status.schedule_created'));
    }

    public function storeSource(StoreAttendanceSourceRequest $request, AttendanceManager $manager): RedirectResponse
    {
        $actor = $this->actor($request);
        $entity = LegalEntity::query()->whereIn('id', $this->managedEntityIds($actor))
            ->where('public_id', $request->string('legal_entity_public_id'))->firstOrFail();
        $manager->createSource($actor, $entity, $request->validated());

        return redirect()->route('attendance.admin.index')->with('status', __('attendance.status.source_created'));
    }

    public function storeHoliday(StoreHolidayRequest $request, AttendanceManager $manager): RedirectResponse
    {
        $actor = $this->actor($request);
        $entity = LegalEntity::query()->whereIn('id', $this->managedEntityIds($actor))
            ->where('public_id', $request->string('legal_entity_public_id'))->firstOrFail();
        $manager->createHoliday($actor, $entity, $request->validated());

        return redirect()->route('attendance.admin.index')->with('status', __('attendance.status.holiday_created'));
    }

    public function assignSchedule(AssignWorkScheduleRequest $request, AttendanceManager $manager): RedirectResponse
    {
        $actor = $this->actor($request);
        $entityIds = $this->managedEntityIds($actor);
        $employee = Employee::query()->whereIn('legal_entity_id', $entityIds)
            ->where('public_id', $request->string('employee_public_id'))->firstOrFail();
        $schedule = WorkSchedule::query()->where('legal_entity_id', $employee->legal_entity_id)
            ->where('public_id', $request->string('work_schedule_public_id'))->firstOrFail();
        $manager->assignSchedule(
            $actor,
            $employee,
            $schedule,
            (string) $request->input('effective_from'),
            $request->filled('effective_to') ? (string) $request->input('effective_to') : null,
            (string) $request->input('reason'),
        );

        return redirect()->route('attendance.admin.index')->with('status', __('attendance.status.schedule_assigned'));
    }

    public function import(ImportAttendanceRequest $request, X100cCsvImporter $importer): RedirectResponse
    {
        $actor = $this->actor($request);
        $source = AttendanceSource::query()->whereIn('legal_entity_id', $this->managedEntityIds($actor))
            ->where('public_id', $request->string('source_public_id'))->firstOrFail();
        $file = $request->file('import_file');
        abort_if($file === null, 422);
        $importer->import($actor, $source, $file);

        return redirect()->route('attendance.admin.index')->with('status', __('attendance.status.import_completed'));
    }

    /** @return list<int> */
    private function managedEntityIds(User $actor): array
    {
        $ids = UserLegalEntityAccess::query()->where('user_id', $actor->getKey())
            ->where('access_level', 'manage')->effectiveOn(now()->toDateString())
            ->pluck('legal_entity_id')->map(static fn (mixed $id): int => (int) $id)->values()->all();

        return array_values($ids);
    }

    /** @return list<int> */
    private function scopedEntityIds(User $actor): array
    {
        $ids = UserLegalEntityAccess::query()->where('user_id', $actor->getKey())
            ->effectiveOn(now()->toDateString())
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
