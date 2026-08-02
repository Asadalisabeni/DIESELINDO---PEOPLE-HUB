<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesScopedEmployee;
use App\Http\Requests\Concerns\ResolvesScopedLegalEntity;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmploymentAssignmentRequest extends FormRequest
{
    use ResolvesScopedEmployee, ResolvesScopedLegalEntity;

    public function authorize(): bool
    {
        $employee = $this->resolveEmployee();
        $entity = $this->resolveLegalEntity();
        $actor = $this->user();

        return $employee && $entity
            && $actor instanceof User
            && $actor->can('update', $employee) === true
            && $actor->can('update', $entity) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $employee = $this->resolveEmployee();
        $entity = $this->resolveLegalEntity();

        return [
            'legal_entity_public_id' => ['required', 'string', 'size:26'],
            'employee_number' => [
                'required', 'string', 'max:64',
                Rule::unique('employees')->where('legal_entity_id', $entity?->getKey())->ignore($employee?->getKey()),
            ],
            'branch_public_id' => ['required', 'string', 'size:26'],
            'division_public_id' => ['nullable', 'string', 'size:26'],
            'department_public_id' => ['required', 'string', 'size:26'],
            'position_public_id' => ['required', 'string', 'size:26'],
            'work_location_public_id' => ['nullable', 'string', 'size:26'],
            'cost_center_public_id' => ['nullable', 'string', 'size:26'],
            'manager_public_id' => ['nullable', 'string', 'size:26'],
            'employment_status' => ['required', Rule::in(['permanent', 'fixed_term'])],
            'termination_date' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'effective_from' => ['required', 'date'],
            'change_reason' => ['required', 'string', 'max:500'],
        ];
    }
}
