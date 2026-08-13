<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLegalEntity;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    use BelongsToLegalEntity, HasPublicId;

    protected $fillable = [
        'legal_entity_id', 'code', 'name', 'category', 'is_paid', 'requires_balance', 'unit',
        'evidence_required_from_days', 'requires_payroll_confirmation', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'requires_balance' => 'boolean',
            'requires_payroll_confirmation' => 'boolean',
            'evidence_required_from_days' => 'integer',
        ];
    }

    /** @return HasMany<LeavePolicy, $this> */
    public function policies(): HasMany
    {
        return $this->hasMany(LeavePolicy::class);
    }
}
