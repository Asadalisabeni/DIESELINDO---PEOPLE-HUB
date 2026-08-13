<?php

namespace App\Http\Requests;

use App\Enums\AttendanceEventType;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->employee instanceof Employee && $user->can('attendance.clock');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'source_public_id' => ['required', 'string', 'size:26'],
            'external_event_id' => ['required', 'string', 'max:100'],
            'event_type' => ['required', Rule::enum(AttendanceEventType::class)],
            'occurred_at' => ['required', 'date'],
            'device_recorded_at' => ['nullable', 'date'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'gps_accuracy_meters' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'activity' => ['nullable', 'string', 'max:500'],
            'destination' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'device_info' => ['nullable', 'string', 'max:500'],
            'was_offline' => ['nullable', 'boolean'],
            'selfie' => ['nullable', 'file', 'max:5120', 'mimetypes:image/jpeg,image/png'],
        ];
    }
}
