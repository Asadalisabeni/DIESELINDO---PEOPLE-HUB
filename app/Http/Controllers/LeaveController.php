<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeaveRequest;
use App\Models\Employee;
use App\Models\LeaveEntitlement;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\Employee\EmployeeDocumentManager;
use App\Services\Leave\LeaveManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

class LeaveController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('leave.access') && $actor->employee instanceof Employee, 403);
        $employee = $actor->employee;

        return view('leave.index', [
            'employee' => $employee,
            'types' => LeaveType::query()->where('legal_entity_id', $employee->legal_entity_id)
                ->where('status', 'active')->orderBy('name')->get(),
            'entitlements' => LeaveEntitlement::query()->where('employee_id', $employee->getKey())
                ->with('type')->withSum('ledgerEntries as balance', 'quantity')->orderBy('valid_to')->get(),
            'requests' => LeaveRequest::query()->where('employee_id', $employee->getKey())
                ->with(['type', 'approvalInstance.steps'])->latest('submitted_at')->paginate(15),
        ]);
    }

    public function store(
        StoreLeaveRequest $request,
        LeaveManager $manager,
        EmployeeDocumentManager $documents,
    ): RedirectResponse {
        $actor = $this->actor($request);
        $employee = $actor->employee;
        abort_unless($employee instanceof Employee, 403);
        $type = LeaveType::query()->where('legal_entity_id', $employee->legal_entity_id)
            ->where('public_id', $request->string('leave_type_public_id'))->where('status', 'active')->firstOrFail();
        $document = null;
        $file = $request->file('evidence');
        if ($file) {
            $document = $documents->store($actor, $employee, $file, [
                'type' => 'leave_evidence', 'classification' => 'restricted',
                'issued_date' => now()->toDateString(),
            ]);
        }

        try {
            $manager->submit($actor, $employee, $type, $request->safe()->except(['evidence']), $document?->getKey());
        } catch (Throwable $exception) {
            if ($document) {
                Storage::disk($document->storage_disk)->delete($document->storage_path);
                $document->delete();
            }
            throw $exception;
        }

        return redirect()->route('leave.index')->with('status', __('leave.status.request_submitted'));
    }

    public function cancel(Request $request, string $leaveRequest, LeaveManager $manager): RedirectResponse
    {
        $actor = $this->actor($request);
        $record = LeaveRequest::query()->where('requested_by', $actor->getKey())
            ->where('public_id', $leaveRequest)->firstOrFail();
        $manager->cancel($actor, $record);

        return redirect()->route('leave.index')->with('status', __('leave.status.request_cancelled'));
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
