<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreOvertimeRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && $this->user()->can('overtime.manage');
    }

    protected function prepareForValidation(): void
    {
        $decoded = json_decode((string) $this->input('segment_rules_json'), true);
        $this->merge(['segment_rules' => is_array($decoded) ? $decoded : null]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'legal_entity_public_id' => ['required', 'string', 'size:26'],
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
            'name' => ['required', 'string', 'max:255'],
            'day_type' => ['required', 'string', 'in:working_day,rest_day,national_holiday'],
            'calculation_method' => ['required', 'string', 'in:government,internal'],
            'minimum_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'rounding_increment_minutes' => ['required', 'integer', 'min:1', 'max:120'],
            'rounding_mode' => ['required', 'string', 'in:floor,nearest,ceil'],
            'maximum_minutes' => ['required', 'integer', 'min:1', 'max:1440', 'gte:minimum_minutes'],
            'segment_rules_json' => ['required', 'string', 'json', 'max:5000'],
            'segment_rules' => ['required', 'array', 'min:1', 'max:12'],
            'segment_rules.*.up_to_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'segment_rules.*.multiplier_hundredths' => ['required', 'integer', 'min:1', 'max:10000'],
            'meal_threshold_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'meal_allowance_idr' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'transport_threshold_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'transport_allowance_idr' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'eligibility' => ['required', 'string', 'in:all_active,permanent,contract'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }
}
