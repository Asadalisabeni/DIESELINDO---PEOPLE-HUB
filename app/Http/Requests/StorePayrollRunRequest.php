<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StorePayrollRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && $this->user()->can('payroll.prepare');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['run_type' => ['required', 'in:regular,off_cycle,thr,bonus,final_settlement']];
    }
}
