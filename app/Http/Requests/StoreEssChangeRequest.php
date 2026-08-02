<?php

namespace App\Http\Requests;

use App\Enums\ProfileChangeType;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEssChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user instanceof User) {
            return false;
        }

        $employee = $user->employee;

        return $employee instanceof Employee
            && $user->can('requestProfileChange', $employee) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'request_type' => ['required', Rule::enum(ProfileChangeType::class)],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimetypes:application/pdf,image/jpeg,image/png'],

            'full_name' => ['required_if:request_type,legal_name', 'nullable', 'string', 'max:255'],
            'marital_status' => ['required_if:request_type,marital_status', 'nullable', Rule::in(['single', 'married', 'divorced', 'widowed'])],

            'bank_code' => ['required_if:request_type,bank_account', 'nullable', 'string', 'max:16'],
            'bank_name' => ['required_if:request_type,bank_account', 'nullable', 'string', 'max:100'],
            'account_number' => ['required_if:request_type,bank_account', 'nullable', 'string', 'max:64'],
            'account_holder_name' => ['required_if:request_type,bank_account', 'nullable', 'string', 'max:255'],

            'tax_identifier' => ['nullable', 'string', 'max:64'],
            'ptkp_code' => ['required_if:request_type,tax_profile', 'nullable', 'string', 'max:16'],

            'health_number' => ['nullable', 'string', 'max:64'],
            'employment_number' => ['nullable', 'string', 'max:64'],
            'jkk_risk_category' => ['nullable', 'string', 'max:32'],

            'family_full_name' => ['required_if:request_type,family_data', 'nullable', 'string', 'max:255'],
            'relationship' => ['required_if:request_type,family_data', 'nullable', Rule::in(['spouse', 'child', 'parent', 'other'])],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'identity_number' => ['nullable', 'string', 'max:64'],

            'document_type' => ['required_if:request_type,identity_document', 'nullable', Rule::in([
                'ktp', 'kk', 'npwp', 'bpjs', 'diploma', 'certificate', 'drivers_license', 'photo', 'bank_proof', 'health_document',
            ])],

            'requested_change' => ['required_if:request_type,employment_data', 'nullable', 'string', 'min:10', 'max:2000'],
            'preferred_effective_date' => ['nullable', 'date'],

            'effective_from' => [
                'required_if:request_type,bank_account,tax_profile,bpjs_profile,family_data',
                'nullable', 'date', 'after_or_equal:today',
            ],
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $type = ProfileChangeType::tryFrom((string) $this->input('request_type'));

            if ($type?->requiresAttachment() && ! $this->hasFile('attachment')) {
                $validator->errors()->add('attachment', __('ess.validation.attachment_required'));
            }

            if ($type === ProfileChangeType::BpjsProfile
                && ! $this->filled('health_number')
                && ! $this->filled('employment_number')) {
                $validator->errors()->add('health_number', __('ess.validation.bpjs_number_required'));
            }
        }];
    }
}
