<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLegalEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeBpjsProfile extends Model
{
    use BelongsToLegalEntity;

    protected $fillable = [
        'legal_entity_id', 'employee_id', 'health_number', 'health_number_last_four',
        'health_number_blind_index', 'employment_number', 'employment_number_last_four',
        'employment_number_blind_index', 'jkk_risk_category', 'verification_status',
        'effective_from', 'effective_to', 'created_by',
    ];

    protected $hidden = [
        'health_number', 'health_number_blind_index', 'employment_number', 'employment_number_blind_index',
    ];

    protected function casts(): array
    {
        return [
            'health_number' => 'encrypted',
            'employment_number' => 'encrypted',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
