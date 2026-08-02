<?php

namespace App\Http\Requests;

use App\Models\LegalEntity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLegalEntityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', LegalEntity::class) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9_-]+$/', 'unique:legal_entities,code'],
            'legal_name' => ['required', 'string', 'max:255'],
            'display_name' => ['required', 'string', 'max:255'],
            'tax_identifier' => ['nullable', 'string', 'max:64'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:16'],
            'country_code' => ['required', 'string', 'size:2'],
            'timezone' => ['required', 'timezone:all'],
            'currency' => ['required', 'string', 'size:3', Rule::in(['IDR'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
