<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLegalEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeBankAccount extends Model
{
    use BelongsToLegalEntity;

    protected $fillable = [
        'legal_entity_id', 'employee_id', 'bank_code', 'bank_name', 'account_number',
        'account_number_last_four', 'account_number_blind_index', 'account_holder_name',
        'verification_status', 'effective_from', 'effective_to', 'created_by',
    ];

    protected $hidden = ['account_number', 'account_number_blind_index', 'account_holder_name'];

    protected function casts(): array
    {
        return [
            'account_number' => 'encrypted',
            'account_holder_name' => 'encrypted',
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
