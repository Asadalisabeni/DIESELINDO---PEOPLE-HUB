<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class AdjustLeaveBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && $this->user()->can('leave.adjust');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'decimal:0,2', 'between:-9999,9999', 'not_in:0,0.00'],
            'effective_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'reference_key' => ['nullable', 'alpha_dash:ascii', 'max:120'],
        ];
    }
}
