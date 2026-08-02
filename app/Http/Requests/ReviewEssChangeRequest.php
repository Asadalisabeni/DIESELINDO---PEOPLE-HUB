<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewEssChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('ess.profile-change.review') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'review_notes' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
