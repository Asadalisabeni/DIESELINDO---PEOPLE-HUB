<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesScopedEmployee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContractRequest extends FormRequest
{
    use ResolvesScopedEmployee;

    public function authorize(): bool
    {
        $employee = $this->resolveEmployee();

        return $employee && $this->user()?->can('manageContract', $employee) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $employee = $this->resolveEmployee();

        return [
            'contract_type' => ['required', Rule::in(['permanent', 'fixed_term'])],
            'contract_number' => [
                'required', 'string', 'max:64',
                Rule::unique('contracts')->where('legal_entity_id', $employee?->legal_entity_id),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', Rule::requiredIf($this->input('contract_type') === 'fixed_term'), 'date', 'after:start_date'],
            'probation_end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'change_reason' => ['required', 'string', 'max:500'],
        ];
    }
}
