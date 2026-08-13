<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class ReviewOvertimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && ($user->can('overtime.approve-manager')
            || $user->can('overtime.validate') || $user->can('overtime.include-payroll'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', 'in:approve,reject,request_revision'],
            'review_notes' => ['required', 'string', 'min:10', 'max:2000'],
            'approved_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'payroll_period_key' => ['nullable', 'date_format:Y-m'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ];
    }
}
