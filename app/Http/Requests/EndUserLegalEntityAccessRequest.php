<?php

namespace App\Http\Requests;

use App\Models\UserLegalEntityAccess;
use App\Services\Organization\LegalEntityScope;
use Illuminate\Foundation\Http\FormRequest;

class EndUserLegalEntityAccessRequest extends FormRequest
{
    private ?UserLegalEntityAccess $resolvedAccess = null;

    public function authorize(): bool
    {
        $actor = $this->user();
        $id = $this->route('access');
        if (! $actor || ! is_numeric($id) || ! $actor->can('entity-access.manage')) {
            return false;
        }

        $this->resolvedAccess = UserLegalEntityAccess::query()
            ->whereIn('legal_entity_id', app(LegalEntityScope::class)->idsFor($actor))
            ->whereKey((int) $id)
            ->with('user')
            ->first();

        return $this->resolvedAccess !== null
            && app(LegalEntityScope::class)->manages($actor, (int) $this->resolvedAccess->legal_entity_id)
            && $actor->can('update', $this->resolvedAccess->user);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'effective_to' => ['required', 'date', 'after:'.(string) $this->scopedAccess()->getRawOriginal('effective_from')],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function scopedAccess(): UserLegalEntityAccess
    {
        return $this->resolvedAccess ?? abort(404);
    }
}
