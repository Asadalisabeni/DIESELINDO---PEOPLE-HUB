<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalStepStatus;
use App\Http\Requests\ReviewOvertimeRequest;
use App\Models\ApprovalInstanceStep;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Models\UserLegalEntityAccess;
use App\Services\Overtime\OvertimeManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OvertimeReviewController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $this->actor($request);
        $ids = $this->availableInstanceIds($actor);
        abort_if($ids === [], 403);

        return view('overtime.review', [
            'requests' => OvertimeRequest::query()->whereIn('approval_instance_id', $ids)
                ->with(['employee', 'rule', 'attendanceRecord', 'calculation', 'approvalInstance.steps'])
                ->oldest('submitted_at')->get(),
        ]);
    }

    public function review(ReviewOvertimeRequest $request, string $overtimeRequest, OvertimeManager $manager): RedirectResponse
    {
        $actor = $this->actor($request);
        $record = OvertimeRequest::query()->whereIn('approval_instance_id', $this->availableInstanceIds($actor))
            ->where('public_id', $overtimeRequest)->firstOrFail();
        $manager->review($actor, $record, $request->validated());

        return redirect()->route('overtime.review.index')->with('status', __('overtime.status.request_reviewed'));
    }

    /** @return list<int> */
    private function availableInstanceIds(User $actor): array
    {
        $managed = UserLegalEntityAccess::query()->where('user_id', $actor->getKey())
            ->where('access_level', 'manage')->effectiveOn(now()->toDateString())
            ->pluck('legal_entity_id')->map(static fn (mixed $id): int => (int) $id)->all();

        return array_values(ApprovalInstanceStep::query()->with('instance')->where('status', ApprovalStepStatus::Pending->value)
            ->get()->filter(function (ApprovalInstanceStep $step) use ($actor, $managed): bool {
                $instance = $step->instance;
                if (! $instance || $instance->subject_type !== 'overtime_request'
                    || (int) $instance->current_step_order !== (int) $step->step_order) {
                    return false;
                }
                if ((int) $step->assigned_approver_user_id === (int) $actor->getKey()) {
                    return true;
                }

                return is_string($step->required_permission) && $actor->can($step->required_permission)
                    && in_array((int) $instance->legal_entity_id, $managed, true);
            })->pluck('approval_instance_id')->map(static fn (mixed $id): int => (int) $id)->values()->all());
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
