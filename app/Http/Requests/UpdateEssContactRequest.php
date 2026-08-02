<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEssContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user instanceof User) {
            return false;
        }

        $employee = $user->employee;

        return $employee instanceof Employee
            && $user->can('updateSelfService', $employee) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:32'],
            'address' => ['required', 'string', 'max:1000'],
            'emergency_name' => ['required', 'string', 'max:255'],
            'emergency_relationship' => ['required', 'string', 'max:64'],
            'emergency_phone' => ['required', 'string', 'max:32'],
            'emergency_address' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
