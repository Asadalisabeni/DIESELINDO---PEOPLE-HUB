<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StorePayrollGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && $this->user()->can('payroll.prepare');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['legal_entity_public_id' => ['required', 'string', 'size:26'], 'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
            'name' => ['required', 'string', 'max:255'], 'proration_basis' => ['required', 'in:calendar_days,working_days'],
            'cutoff_start_day' => ['required', 'integer', 'min:1', 'max:31'], 'cutoff_end_day' => ['required', 'integer', 'min:1', 'max:31'],
            'payment_day' => ['required', 'integer', 'min:1', 'max:31'], 'payment_date_adjustment' => ['required', 'in:none,previous_working_day,next_working_day']];
    }
}
