<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreWorkScheduleRequest extends FormRequest
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
            'legal_entity_public_id' => ['required', 'string', 'size:26'],
            'branch_public_id' => ['nullable', 'string', 'size:26'],
            'department_public_id' => ['nullable', 'string', 'size:26'],
            'code' => ['required', 'alpha_dash:ascii', 'max:32'],
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'timezone:all'],
            'late_grace_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'early_leave_grace_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'days' => ['required', 'array', 'size:7'],
            'days.*.is_working_day' => ['required', 'boolean'],
            'days.*.start_time' => ['nullable', 'date_format:H:i'],
            'days.*.end_time' => ['nullable', 'date_format:H:i'],
            'days.*.break_minutes' => ['nullable', 'integer', 'min:0', 'max:480'],
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $days = (array) $this->input('days', []);
            $dayNumbers = array_map(static fn (int|string $day): int => (int) $day, array_keys($days));
            sort($dayNumbers);
            if ($dayNumbers !== range(1, 7)) {
                $validator->errors()->add('days', __('attendance.validation.invalid_weekdays'));
            }
            foreach ($days as $day => $values) {
                if ((bool) ($values['is_working_day'] ?? false)
                    && (empty($values['start_time']) || empty($values['end_time']))) {
                    $validator->errors()->add('days.'.$day, __('attendance.validation.working_hours_required'));
                }
                if (($values['start_time'] ?? null) >= ($values['end_time'] ?? '99:99')) {
                    $validator->errors()->add('days.'.$day.'.end_time', __('attendance.validation.no_night_shift'));
                }
            }
        }];
    }
}
