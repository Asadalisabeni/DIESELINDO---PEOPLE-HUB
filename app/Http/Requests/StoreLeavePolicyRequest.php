<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreLeavePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && $this->user()->can('leave.manage');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'leave_type_public_id' => ['required', 'string', 'size:26'],
            'eligibility_months' => ['required', 'integer', 'min:0', 'max:600'],
            'entitlement_quantity' => ['required', 'decimal:0,2', 'min:0', 'max:9999'],
            'validity_months' => ['nullable', 'integer', 'min:1', 'max:600'],
            'carry_forward_enabled' => ['required', 'boolean'],
            'carry_forward_limit' => ['nullable', 'decimal:0,2', 'min:0', 'max:9999'],
            'minimum_notice_days' => ['required', 'integer', 'min:0', 'max:365'],
            'maximum_request_days' => ['nullable', 'decimal:0,2', 'min:1', 'max:9999'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
        ];
    }
}
