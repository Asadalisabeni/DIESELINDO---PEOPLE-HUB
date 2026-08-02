<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\Iam\RoleMatrix;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'role' => ['required', 'string', Rule::in(RoleMatrix::assignableRoleNames($this->user()))],
        ];
    }
}
