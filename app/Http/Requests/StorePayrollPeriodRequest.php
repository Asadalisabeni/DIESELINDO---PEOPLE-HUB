<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StorePayrollPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && $this->user()->can('payroll.prepare');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['payroll_group_public_id' => ['required', 'string', 'size:26'], 'period_key' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'period_type' => ['required', 'in:monthly,off_cycle,thr,bonus,final_settlement'],
            'payroll_start' => ['required', 'date'], 'payroll_end' => ['required', 'date', 'after_or_equal:payroll_start'],
            'attendance_cutoff_start' => ['required', 'date'], 'attendance_cutoff_end' => ['required', 'date', 'after_or_equal:attendance_cutoff_start'],
            'payment_date' => ['required', 'date', 'after_or_equal:payroll_end']];
    }
}
