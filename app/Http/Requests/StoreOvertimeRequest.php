<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreOvertimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && $this->user()->can('overtime.request');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'employee_public_id' => ['nullable', 'string', 'size:26'],
            'request_type' => ['required', 'string', 'in:regular,emergency'],
            'planned_start' => ['required', 'date_format:Y-m-d H:i'],
            'planned_end' => ['required', 'date_format:Y-m-d H:i', 'after:planned_start'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'work_description' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }
}
