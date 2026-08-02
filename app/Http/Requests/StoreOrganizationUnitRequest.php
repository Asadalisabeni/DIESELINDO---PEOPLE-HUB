<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesScopedLegalEntity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrganizationUnitRequest extends FormRequest
{
    use ResolvesScopedLegalEntity;

    public function authorize(): bool
    {
        $entity = $this->resolveLegalEntity();

        return $entity && $this->user()?->can('update', $entity) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $unitType = (string) $this->route('unitType');
        $table = match ($unitType) {
            'branches' => 'branches',
            'divisions' => 'divisions',
            'departments' => 'departments',
            'positions' => 'positions',
            'work-locations' => 'work_locations',
            'cost-centers' => 'cost_centers',
            default => 'branches',
        };
        $entityId = $this->resolveLegalEntity()?->getKey();

        return [
            'code' => [
                'required', 'string', 'max:32', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique($table, 'code')->where('legal_entity_id', $entityId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'address' => ['nullable', 'string', 'max:500'],
            'timezone' => ['nullable', 'timezone:all'],
            'branch_public_id' => [Rule::requiredIf(in_array($unitType, ['departments'], true)), 'nullable', 'string', 'size:26'],
            'division_public_id' => ['nullable', 'string', 'size:26'],
            'department_public_id' => [Rule::requiredIf($unitType === 'positions'), 'nullable', 'string', 'size:26'],
            'level' => ['nullable', 'string', 'max:64'],
            'external_code' => ['nullable', 'string', 'max:64'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! in_array($this->route('unitType'), ['branches', 'divisions', 'departments', 'positions', 'work-locations', 'cost-centers'], true)) {
            abort(404);
        }
    }
}
