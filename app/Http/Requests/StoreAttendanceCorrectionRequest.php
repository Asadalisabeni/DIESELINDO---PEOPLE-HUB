<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAttendanceCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->employee instanceof Employee && $user->can('attendance.corrections.request');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'attendance_record_public_id' => ['required', 'string', 'size:26'],
            'type' => ['required', Rule::in([
                'missing_check_in', 'missing_check_out', 'wrong_location', 'field_duty', 'work_from_home',
                'business_travel', 'late_permission', 'early_leave', 'holiday_attendance', 'hr_manual',
            ])],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'proposed_check_in_at' => ['nullable', 'date'],
            'proposed_check_out_at' => ['nullable', 'date'],
            'evidence' => ['nullable', 'file', 'max:5120', 'mimetypes:application/pdf,image/jpeg,image/png'],
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->filled('proposed_check_in_at') && ! $this->filled('proposed_check_out_at')) {
                $validator->errors()->add('proposed_check_in_at', __('attendance.validation.correction_time_required'));
            }
        }];
    }
}
