<?php

namespace App\Http\Controllers;

use App\Http\Requests\EndUserLegalEntityAccessRequest;
use App\Http\Requests\StoreUserLegalEntityAccessRequest;
use App\Models\LegalEntity;
use App\Models\User;
use App\Models\UserLegalEntityAccess;
use App\Services\Organization\LegalEntityScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserLegalEntityAccessController extends Controller
{
    public function store(StoreUserLegalEntityAccessRequest $request, User $user, LegalEntityScope $scope): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $entity = $request->scopedLegalEntity();
        $data = $request->validated();

        DB::transaction(function () use ($actor, $user, $entity, $data, $scope): void {
            $overlap = UserLegalEntityAccess::query()
                ->where('user_id', $user->getKey())
                ->where('legal_entity_id', $entity->getKey())
                ->whereDate('effective_from', '<', $data['effective_to'] ?? '9999-12-31')
                ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>', $data['effective_from']))
                ->lockForUpdate()
                ->exists();

            if ($overlap) {
                throw ValidationException::withMessages(['effective_from' => __('auth.entity_access_overlap')]);
            }

            $access = UserLegalEntityAccess::query()->create([
                'user_id' => $user->getKey(),
                'legal_entity_id' => $entity->getKey(),
                'access_level' => $data['access_level'],
                'effective_from' => $data['effective_from'],
                'effective_to' => $data['effective_to'] ?? null,
                'granted_by' => $actor->getKey(),
                'reason' => $data['reason'],
            ]);
            $scope->forget($user);

            activity('iam')
                ->causedBy($actor)
                ->performedOn($user)
                ->event('legal_entity_access_granted')
                ->withProperties([
                    'access_id' => $access->getKey(),
                    'legal_entity_public_id' => $entity->public_id,
                    'access_level' => $access->access_level,
                    'effective_from' => $data['effective_from'],
                    'effective_to' => $data['effective_to'] ?? null,
                ])
                ->log('User legal-entity scope granted.');
        });

        return back()->with('status', __('auth.entity_access_granted'));
    }

    public function end(EndUserLegalEntityAccessRequest $request, LegalEntityScope $scope): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $access = $request->scopedAccess();
        $data = $request->validated();

        DB::transaction(function () use ($actor, $access, $data, $scope): void {
            $access->update(['effective_to' => $data['effective_to']]);
            $target = $access->user;
            $entity = $access->legalEntity;
            abort_unless($target instanceof User && $entity instanceof LegalEntity, 404);
            $scope->forget($target);

            activity('iam')
                ->causedBy($actor)
                ->performedOn($target)
                ->event('legal_entity_access_ended')
                ->withProperties([
                    'access_id' => $access->getKey(),
                    'legal_entity_public_id' => $entity->public_id,
                    'effective_to' => $data['effective_to'],
                    'reason' => $data['reason'],
                ])
                ->log('User legal-entity scope ended by effective date.');
        });

        return back()->with('status', __('auth.entity_access_ended'));
    }
}
