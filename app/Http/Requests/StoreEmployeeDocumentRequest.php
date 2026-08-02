<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesScopedEmployee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeDocumentRequest extends FormRequest
{
    use ResolvesScopedEmployee;

    public function authorize(): bool
    {
        $employee = $this->resolveEmployee();

        return $employee && $this->user()?->can('uploadDocument', $employee) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['ktp', 'kk', 'npwp', 'bpjs', 'contract', 'diploma', 'certificate', 'drivers_license', 'photo', 'bank_proof', 'warning_letter', 'health_document'])],
            'document' => ['required', 'file', 'max:10240', 'mimetypes:application/pdf,image/jpeg,image/png'],
            'issued_date' => ['nullable', 'date'],
            'expires_date' => ['nullable', 'date', 'after:issued_date'],
            'classification' => ['required', Rule::in(['confidential', 'restricted'])],
        ];
    }
}
