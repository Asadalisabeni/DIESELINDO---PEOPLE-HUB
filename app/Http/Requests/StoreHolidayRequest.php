<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHolidayRequest extends FormRequest
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
            'holiday_date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['national', 'company', 'collective_leave', 'special'])],
            'source' => ['nullable', 'string', 'max:100'],
        ];
    }
}
