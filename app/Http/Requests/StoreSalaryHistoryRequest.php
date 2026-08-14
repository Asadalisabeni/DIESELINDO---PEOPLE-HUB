<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreSalaryHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && $this->user()->can('salaries.update');
    }

    protected function prepareForValidation(): void
    {
        $decoded = json_decode((string) $this->input('components_json'), true);
        $this->merge(['components' => is_array($decoded) ? $decoded : null]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['employee_public_id' => ['required', 'string', 'size:26'], 'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'], 'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'components_json' => ['required', 'string', 'json', 'max:20000'], 'components' => ['required', 'array', 'min:1', 'max:50'],
            'components.*.component_public_id' => ['required', 'string', 'size:26'],
            'components.*.amount' => ['required', 'regex:/^\d{1,14}(\.\d{1,4})?$/'],
            'components.*.quantity' => ['nullable', 'regex:/^\d{1,7}(\.\d{1,4})?$/']];
    }
}
