<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLegalEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeContact extends Model
{
    use BelongsToLegalEntity;

    protected $fillable = ['legal_entity_id', 'employee_id', 'type', 'value', 'is_primary', 'effective_from', 'effective_to', 'created_by'];

    protected $hidden = ['value'];

    protected function casts(): array
    {
        return [
            'value' => 'encrypted',
            'is_primary' => 'boolean',
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
