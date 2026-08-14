<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLegalEntity;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollGroup extends Model
{
    use BelongsToLegalEntity, HasPublicId;

    protected $fillable = ['legal_entity_id', 'code', 'name', 'frequency', 'timezone', 'currency', 'proration_basis', 'cutoff_start_day', 'cutoff_end_day', 'payment_day', 'payment_date_adjustment', 'status', 'created_by'];

    /** @return HasMany<PayrollGroupMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(PayrollGroupMembership::class);
    }

    /** @return HasMany<PayrollPeriod, $this> */
    public function periods(): HasMany
    {
        return $this->hasMany(PayrollPeriod::class);
    }
}
