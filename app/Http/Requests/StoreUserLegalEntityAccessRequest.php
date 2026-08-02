<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesScopedLegalEntity;
use App\Models\User;
use App\Services\Organization\LegalEntityScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserLegalEntityAccessRequest extends FormRequest
{
    use ResolvesScopedLegalEntity;

    public function authorize(): bool
    {
        $target = $this->route('user');
        $entity = $this->resolveLegalEntity();
        $actor = $this->user();

        return $target instanceof User
            && $entity !== null
            && $actor instanceof User
            && $actor->can('entity-access.manage') === true
            && app(LegalEntityScope::class)->manages($actor, (int) $entity->getKey())
            && $actor->can('update', $target) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'legal_entity_public_id' => ['required', 'string', 'size:26'],
            'access_level' => ['required', Rule::in(['view', 'manage'])],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
