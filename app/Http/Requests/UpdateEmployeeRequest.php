<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesScopedEmployee;
use App\Models\Employee;
use App\Support\Security\SensitiveValue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEmployeeRequest extends FormRequest
{
    use ResolvesScopedEmployee;

    public function authorize(): bool
    {
        $employee = $this->resolveEmployee();

        return $employee && $this->user()?->can('update', $employee) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
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
            'status' => ['required', Rule::in(['active', 'inactive', 'terminated'])],
            'effective_from' => ['required', 'date'],
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $employee = $this->resolveEmployee();
            $nikIndex = SensitiveValue::blindIndex($this->input('nik'), 'employee.nik');
            if ($employee && $nikIndex && Employee::query()
                ->where('legal_entity_id', $employee->legal_entity_id)
                ->where('nik_blind_index', $nikIndex)
                ->whereKeyNot($employee->getKey())
                ->exists()) {
                $validator->errors()->add('nik', __('employee.validation.duplicate_nik'));
            }
        }];
    }
}
