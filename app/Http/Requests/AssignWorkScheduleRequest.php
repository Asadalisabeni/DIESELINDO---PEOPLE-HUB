<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class AssignWorkScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('attendance.manage');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'employee_public_id' => ['required', 'string', 'size:26'],
            'work_schedule_public_id' => ['required', 'string', 'size:26'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }
}
