<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLegalEntity;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveEntitlement extends Model
{
    use BelongsToLegalEntity, HasPublicId;

    protected $fillable = [
        'legal_entity_id', 'employee_id', 'leave_type_id', 'leave_policy_id', 'grant_reference',
        'valid_from', 'valid_to', 'opening_quantity', 'source', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return ['valid_from' => 'date', 'valid_to' => 'date', 'opening_quantity' => 'decimal:2'];
    }

    /** @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeUsableOn(Builder $query, string $date): Builder
    {
        return $query->where('status', 'active')->whereDate('valid_from', '<=', $date)
            ->where(fn (Builder $period) => $period->whereNull('valid_to')->orWhereDate('valid_to', '>=', $date));
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<LeaveType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    /** @return BelongsTo<LeavePolicy, $this> */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(LeavePolicy::class, 'leave_policy_id');
    }

    /** @return HasMany<LeaveLedgerEntry, $this> */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LeaveLedgerEntry::class);
    }

    public function balance(): string
    {
        return number_format((float) $this->ledgerEntries()->sum('quantity'), 2, '.', '');
    }
}
