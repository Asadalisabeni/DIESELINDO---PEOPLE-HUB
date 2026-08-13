<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class GrantLeaveEntitlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && $this->user()->can('leave.manage');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'employee_public_id' => ['required', 'string', 'size:26'],
            'leave_type_public_id' => ['required', 'string', 'size:26'],
            'grant_reference' => ['required', 'alpha_dash:ascii', 'max:100'],
            'valid_from' => ['required', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'quantity' => ['required', 'decimal:0,2', 'min:0.01', 'max:9999'],
            'source' => ['required', 'in:opening,entitlement,carry_forward,migration'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }
}
