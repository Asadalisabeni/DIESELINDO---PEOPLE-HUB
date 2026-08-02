<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\Iam\RoleMatrix;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User && $this->user()?->can('update', $target) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'string', 'distinct', Rule::in(RoleMatrix::assignableRoleNames($this->user()))],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
