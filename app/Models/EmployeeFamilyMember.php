<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLegalEntity;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeFamilyMember extends Model
{
    use BelongsToLegalEntity, HasPublicId;

    protected $fillable = [
        'legal_entity_id', 'employee_id', 'full_name', 'relationship', 'birth_date',
        'identity_number', 'identity_number_last_four', 'identity_number_blind_index',
        'status', 'effective_from', 'effective_to', 'created_by',
    ];

    protected $hidden = ['identity_number', 'identity_number_blind_index'];

    protected function casts(): array
    {
        return [
            'full_name' => 'encrypted',
            'identity_number' => 'encrypted',
            'birth_date' => 'date',
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
