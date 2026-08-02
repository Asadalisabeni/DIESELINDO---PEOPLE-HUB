<?php

namespace App\Http\Controllers;

use App\Enums\ProfileChangeStatus;
use App\Http\Requests\ReviewEssChangeRequest;
use App\Http\Requests\StoreEssChangeRequest;
use App\Models\Employee;
use App\Models\EmployeeProfileChangeRequest;
use App\Models\User;
use App\Models\UserLegalEntityAccess;
use App\Services\Employee\EmployeeSelfServiceManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EssChangeRequestController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $this->actor($request);
        $employee = $actor->employee;
        abort_unless($employee instanceof Employee, 403);
        $this->authorize('viewSelfService', $employee);

        return view('ess.requests.index', [
            'changeRequests' => EmployeeProfileChangeRequest::query()
                ->where('requested_by', $actor->getKey())
                ->with(['reviewer', 'attachmentDocument'])
                ->latest('submitted_at')
                ->paginate(15),
        ]);
    }

    public function store(StoreEssChangeRequest $request, EmployeeSelfServiceManager $manager): RedirectResponse
    {
        $actor = $this->actor($request);
        $employee = $actor->employee;
        abort_unless($employee instanceof Employee, 403);
        $changeRequest = $manager->submitChangeRequest(
            $actor,
            $employee,
            $request->validated(),
            $request->file('attachment'),
        );

        return redirect()->route('ess.requests.show', $changeRequest)
            ->with('status', __('ess.status.request_submitted'));
    }

    public function show(Request $request, string $changeRequest, EmployeeSelfServiceManager $manager): View
    {
        $actor = $this->actor($request);
        $record = $this->visibleRequest($actor, $changeRequest);
        $this->authorize('view', $record);

        return view('ess.requests.show', [
            'changeRequest' => $record,
            'currentValues' => $manager->presentValues($record->changeType(), $record->currentValues()),
            'proposedValues' => $manager->presentValues($record->changeType(), $record->proposedValues()),
            'canReview' => $actor->can('review', $record),
            'canCancel' => $record->changeStatus() === ProfileChangeStatus::Pending && $actor->can('cancel', $record),
        ]);
    }

    public function cancel(Request $request, string $changeRequest, EmployeeSelfServiceManager $manager): RedirectResponse
    {
        $actor = $this->actor($request);
        $record = EmployeeProfileChangeRequest::query()
            ->where('requested_by', $actor->getKey())
            ->where('public_id', $changeRequest)
            ->firstOrFail();
        $this->authorize('cancel', $record);
        $manager->cancel($actor, $record);

        return redirect()->route('ess.requests.index')->with('status', __('ess.status.request_cancelled'));
    }

    public function reviewIndex(Request $request): View
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('ess.profile-change.review'), 403);
        $entityIds = $this->managedEntityIds($actor);
        abort_if($entityIds === [], 403);

        return view('ess.review.index', [
            'changeRequests' => EmployeeProfileChangeRequest::query()
                ->whereIn('legal_entity_id', $entityIds)
                ->where('status', ProfileChangeStatus::Pending->value)
                ->with(['employee', 'legalEntity', 'attachmentDocument'])
                ->oldest('submitted_at')
                ->paginate(20),
        ]);
    }

    public function review(
        ReviewEssChangeRequest $request,
        string $changeRequest,
        EmployeeSelfServiceManager $manager,
    ): RedirectResponse {
        $actor = $this->actor($request);
        $record = EmployeeProfileChangeRequest::query()
            ->whereIn('legal_entity_id', $this->managedEntityIds($actor))
            ->where('public_id', $changeRequest)
            ->firstOrFail();
        $this->authorize('review', $record);
        $manager->review($actor, $record, $request->validated());

        return redirect()->route('ess.review.index')->with('status', __('ess.status.request_reviewed'));
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }

    private function visibleRequest(User $actor, string $publicId): EmployeeProfileChangeRequest
    {
        $entityIds = $actor->can('ess.profile-change.review') ? $this->managedEntityIds($actor) : [];

        return EmployeeProfileChangeRequest::query()
            ->where('public_id', $publicId)
            ->where(function ($query) use ($actor, $entityIds): void {
                $query->where('requested_by', $actor->getKey());
                if ($entityIds !== []) {
                    $query->orWhereIn('legal_entity_id', $entityIds);
                }
            })
            ->with(['employee', 'legalEntity', 'requester', 'reviewer', 'attachmentDocument'])
            ->firstOrFail();
    }

    /** @return list<int> */
    private function managedEntityIds(User $actor): array
    {
        $ids = UserLegalEntityAccess::query()
            ->where('user_id', $actor->getKey())
            ->where('access_level', 'manage')
            ->effectiveOn(now()->toDateString())
            ->pluck('legal_entity_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()->all();

        return array_values($ids);
    }
}
