<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLegalEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeTaxProfile extends Model
{
    use BelongsToLegalEntity;

    protected $fillable = [
        'legal_entity_id', 'employee_id', 'tax_identifier', 'tax_identifier_last_four',
        'tax_identifier_blind_index', 'ptkp_code', 'tax_method', 'verification_status',
        'effective_from', 'effective_to', 'created_by',
    ];

    protected $hidden = ['tax_identifier', 'tax_identifier_blind_index'];

    protected function casts(): array
    {
        return [
            'tax_identifier' => 'encrypted',
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
