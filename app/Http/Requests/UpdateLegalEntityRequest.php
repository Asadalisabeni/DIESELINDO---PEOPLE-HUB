<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesScopedLegalEntity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLegalEntityRequest extends FormRequest
{
    use ResolvesScopedLegalEntity;

    public function authorize(): bool
    {
        $entity = $this->resolveLegalEntity();

        return $entity && $this->user()?->can('update', $entity) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'legal_name' => ['required', 'string', 'max:255'],
            'display_name' => ['required', 'string', 'max:255'],
            'tax_identifier' => ['nullable', 'string', 'max:64'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:16'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
