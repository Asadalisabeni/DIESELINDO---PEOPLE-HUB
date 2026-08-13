<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalStepStatus;
use App\Http\Requests\ReviewLeaveRequest;
use App\Models\ApprovalInstanceStep;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Models\UserLegalEntityAccess;
use App\Services\Leave\LeaveManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveReviewController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $this->actor($request);
        $instanceIds = $this->availableInstanceIds($actor);
        abort_if($instanceIds === [], 403);

        return view('leave.review', [
            'requests' => LeaveRequest::query()->whereIn('approval_instance_id', $instanceIds)
                ->with(['employee', 'type', 'approvalInstance.steps', 'evidenceDocument'])
                ->oldest('submitted_at')->get(),
        ]);
    }

    public function review(
        ReviewLeaveRequest $request,
        string $leaveRequest,
        LeaveManager $manager,
    ): RedirectResponse {
        $actor = $this->actor($request);
        $record = LeaveRequest::query()->whereIn('approval_instance_id', $this->availableInstanceIds($actor))
            ->where('public_id', $leaveRequest)->firstOrFail();
        $manager->review($actor, $record, [
            'decision' => (string) $request->input('decision'),
            'review_notes' => (string) $request->input('review_notes'),
            'idempotency_key' => (string) ($request->input('idempotency_key') ?: ''),
        ]);

        return redirect()->route('leave.review.index')->with('status', __('leave.status.request_reviewed'));
    }

    public function downloadEvidence(Request $request, string $leaveRequest): StreamedResponse
    {
        $actor = $this->actor($request);
        $record = LeaveRequest::query()->with(['evidenceDocument', 'employee'])
            ->where('public_id', $leaveRequest)
            ->where(function ($query) use ($actor): void {
                $query->where('requested_by', $actor->getKey())
                    ->orWhereIn('approval_instance_id', $this->availableInstanceIds($actor));
            })->firstOrFail();
        $document = $record->evidenceDocument;
        abort_unless($document !== null, 404);
        $disk = Storage::disk($document->storage_disk);
        abort_unless($disk->exists($document->storage_path), 404);

        activity('leave')->causedBy($actor)->performedOn($record)->event('leave_evidence_downloaded')
            ->withProperties([
                'leave_request_public_id' => $record->public_id,
                'document_public_id' => $document->public_id,
                'legal_entity_public_id' => $record->legalEntity()->value('public_id'),
            ])->log('Private leave evidence downloaded by requester or current approver.');

        return $disk->download($document->storage_path, $document->original_name, [
            'Content-Type' => $document->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    /** @return list<int> */
    private function availableInstanceIds(User $actor): array
    {
        $managedEntityIds = UserLegalEntityAccess::query()->where('user_id', $actor->getKey())
            ->where('access_level', 'manage')->effectiveOn(now()->toDateString())
            ->pluck('legal_entity_id')->map(static fn (mixed $id): int => (int) $id)->values()->all();

        $ids = ApprovalInstanceStep::query()->with('instance')->where('status', ApprovalStepStatus::Pending->value)
            ->get()->filter(function (ApprovalInstanceStep $step) use ($actor, $managedEntityIds): bool {
                $instance = $step->instance;
                if (! $instance || (int) $instance->current_step_order !== (int) $step->step_order) {
                    return false;
                }
                if ((int) $step->assigned_approver_user_id === (int) $actor->getKey()) {
                    return true;
                }

                return is_string($step->required_permission)
                    && $actor->can($step->required_permission)
                    && in_array((int) $instance->legal_entity_id, $managedEntityIds, true);
            })->pluck('approval_instance_id')->map(static fn (mixed $id): int => (int) $id)->values()->all();

        return array_values($ids);
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
