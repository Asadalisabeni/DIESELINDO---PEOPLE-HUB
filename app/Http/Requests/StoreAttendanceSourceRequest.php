<?php

namespace App\Http\Requests;

use App\Enums\AttendanceSourceType;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceSourceRequest extends FormRequest
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
            'code' => ['required', 'alpha_dash:ascii', 'max:32'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(AttendanceSourceType::class)],
            'adapter' => ['required', Rule::in(['web_gps_v1', 'offline_mobile_v1', 'x100c_csv_v1'])],
            'requires_gps' => ['nullable', 'boolean'],
            'requires_selfie' => ['nullable', 'boolean'],
            'max_gps_accuracy_meters' => ['required', 'integer', 'min:1', 'max:10000'],
            'max_offline_delay_minutes' => ['required', 'integer', 'min:1', 'max:10080'],
        ];
    }
}
