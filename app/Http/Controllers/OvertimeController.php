<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOvertimeRequest;
use App\Models\Employee;
use App\Models\EmploymentHistory;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Services\Overtime\OvertimeManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OvertimeController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('overtime.access') && $actor->employee instanceof Employee, 403);
        $employee = $actor->employee;
        $team = collect();
        if ($actor->can('overtime.team.request')) {
            $teamIds = EmploymentHistory::query()->where('manager_employee_id', $employee->getKey())
                ->effectiveOn(now()->toDateString())->pluck('employee_id');
            $team = Employee::query()->whereIn('id', $teamIds)->orderBy('full_name')->get();
        }

        return view('overtime.index', [
            'employee' => $employee, 'team' => $team,
            'requests' => OvertimeRequest::query()->where(fn ($query) => $query
                ->where('employee_id', $employee->getKey())->orWhere('requested_by', $actor->getKey()))
                ->with(['employee', 'rule', 'calculation', 'approvalInstance.steps'])
                ->latest('submitted_at')->paginate(15),
        ]);
    }

    public function store(StoreOvertimeRequest $request, OvertimeManager $manager): RedirectResponse
    {
        $actor = $this->actor($request);
        $employee = $actor->employee;
        abort_unless($employee instanceof Employee, 403);
        if ($request->filled('employee_public_id')) {
            $employee = Employee::query()->where('public_id', $request->string('employee_public_id'))->firstOrFail();
        }
        $manager->submit($actor, $employee, $request->validated());

        return redirect()->route('overtime.index')->with('status', __('overtime.status.request_submitted'));
    }

    public function cancel(Request $request, string $overtimeRequest, OvertimeManager $manager): RedirectResponse
    {
        $actor = $this->actor($request);
        $record = OvertimeRequest::query()->where('requested_by', $actor->getKey())
            ->where('public_id', $overtimeRequest)->firstOrFail();
        $manager->cancel($actor, $record);

        return redirect()->route('overtime.index')->with('status', __('overtime.status.request_cancelled'));
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
