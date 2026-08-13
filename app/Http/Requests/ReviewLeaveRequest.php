<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class ReviewLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && (
            $user->can('leave.approve-manager') || $user->can('leave.review') || $user->can('leave.confirm-payroll')
        );
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:approve,reject,request_revision'],
            'review_notes' => ['required', 'string', 'min:5', 'max:2000'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ];
    }
}
