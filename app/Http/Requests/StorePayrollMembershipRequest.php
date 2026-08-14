<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StorePayrollMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && $this->user()->can('payroll.prepare');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['payroll_group_public_id' => ['required', 'string', 'size:26'], 'employee_public_id' => ['required', 'string', 'size:26'],
            'effective_from' => ['required', 'date'], 'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'reason' => ['required', 'string', 'min:10', 'max:1000']];
    }
}
