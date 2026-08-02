<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLegalEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmergencyContact extends Model
{
    use BelongsToLegalEntity;

    protected $fillable = [
        'legal_entity_id', 'employee_id', 'name', 'relationship', 'phone', 'address',
        'priority', 'effective_from', 'effective_to', 'created_by',
    ];

    protected $hidden = ['phone', 'address'];

    protected function casts(): array
    {
        return [
            'phone' => 'encrypted',
            'address' => 'encrypted',
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
