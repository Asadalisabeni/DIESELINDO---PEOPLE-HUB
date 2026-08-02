<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesScopedLegalEntity;
use App\Models\Employee;
use App\Support\Security\SensitiveValue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEmployeeRequest extends FormRequest
{
    use ResolvesScopedLegalEntity;

    public function authorize(): bool
    {
        $entity = $this->resolveLegalEntity();

        return $entity && $this->user()?->can('create', [Employee::class, $entity]) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $entityId = $this->resolveLegalEntity()?->getKey();

        return [
            'legal_entity_public_id' => ['required', 'string', 'size:26'],
            'employee_number' => ['required', 'string', 'max:64', Rule::unique('employees')->where('legal_entity_id', $entityId)],
            'full_name' => ['required', 'string', 'max:255'],
            'nik' => ['nullable', 'string', 'max:32'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'marital_status' => ['nullable', Rule::in(['single', 'married', 'divorced', 'widowed'])],
            'personal_email' => ['nullable', 'email:rfc', 'max:255'],
            'company_email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:1000'],
            'emergency_name' => ['nullable', 'required_with:emergency_phone', 'string', 'max:255'],
            'emergency_relationship' => ['nullable', 'required_with:emergency_phone', 'string', 'max:64'],
            'emergency_phone' => ['nullable', 'string', 'max:32'],
            'emergency_address' => ['nullable', 'string', 'max:1000'],
            ...$this->employmentRules(),
            'contract_type' => ['required', Rule::in(['permanent', 'fixed_term'])],
            'contract_number' => ['required', 'string', 'max:64'],
            'contract_start_date' => ['required', 'date'],
            'contract_end_date' => ['nullable', 'date', 'after:contract_start_date'],
            'probation_end_date' => ['nullable', 'date', 'after_or_equal:contract_start_date'],
            'bank_code' => ['nullable', 'string', 'max:16'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_number' => ['nullable', 'string', 'max:64'],
            'bank_account_holder' => ['nullable', 'required_with:bank_account_number', 'string', 'max:255'],
            'tax_identifier' => ['nullable', 'string', 'max:64'],
            'ptkp_code' => ['nullable', 'string', 'max:16'],
            'bpjs_health_number' => ['nullable', 'string', 'max:64'],
            'bpjs_employment_number' => ['nullable', 'string', 'max:64'],
            'jkk_risk_category' => ['nullable', 'string', 'max:32'],
        ];
    }

    /** @return array<string, mixed> */
    private function employmentRules(): array
    {
        return [
            'branch_public_id' => ['required', 'string', 'size:26'],
            'division_public_id' => ['nullable', 'string', 'size:26'],
            'department_public_id' => ['required', 'string', 'size:26'],
            'position_public_id' => ['required', 'string', 'size:26'],
            'work_location_public_id' => ['nullable', 'string', 'size:26'],
            'cost_center_public_id' => ['nullable', 'string', 'size:26'],
            'manager_public_id' => ['nullable', 'string', 'size:26'],
            'employment_status' => ['required', Rule::in(['permanent', 'fixed_term'])],
            'join_date' => ['required', 'date'],
            'effective_from' => ['required', 'date', 'after_or_equal:join_date'],
            'change_reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $nikIndex = SensitiveValue::blindIndex($this->input('nik'), 'employee.nik');
            $entityId = $this->resolveLegalEntity()?->getKey();
            if ($nikIndex && Employee::query()->where('legal_entity_id', $entityId)->where('nik_blind_index', $nikIndex)->exists()) {
                $validator->errors()->add('nik', __('employee.validation.duplicate_nik'));
            }

            if ($this->input('employment_status') === 'fixed_term' && ! $this->filled('contract_end_date')) {
                $validator->errors()->add('contract_end_date', __('employee.validation.fixed_term_end_required'));
            }

            if ($this->input('contract_type') !== $this->input('employment_status')) {
                $validator->errors()->add('contract_type', __('employee.validation.contract_status_mismatch'));
            }
        }];
    }
}
