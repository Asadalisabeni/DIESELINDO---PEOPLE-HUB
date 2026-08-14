<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreSalaryComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && $this->user()->can('salaries.update');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['legal_entity_public_id' => ['required', 'string', 'size:26'], 'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
            'name' => ['required', 'string', 'max:255'], 'type' => ['required', 'in:income,deduction,employer'],
            'calculation_type' => ['required', 'in:fixed_monthly,daily_rate,unpaid_leave_daily,overtime_meal,overtime_transport'],
            'taxable' => ['nullable', 'boolean'], 'bpjs_eligible' => ['nullable', 'boolean'],
            'rounding_scale' => ['required', 'integer', 'min:0', 'max:4'], 'rounding_mode' => ['required', 'in:floor,nearest,ceil'],
            'effective_from' => ['required', 'date'], 'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from']];
    }
}
