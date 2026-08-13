<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLegalEntity;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkSchedule extends Model
{
    use BelongsToLegalEntity, HasPublicId;

    protected $fillable = [
        'legal_entity_id', 'branch_id', 'department_id', 'code', 'name', 'timezone', 'late_grace_minutes',
        'early_leave_grace_minutes', 'effective_from', 'effective_to', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'late_grace_minutes' => 'integer',
            'early_leave_grace_minutes' => 'integer',
        ];
    }

    /** @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeEffectiveOn(Builder $query, string $date): Builder
    {
        return $query->whereDate('effective_from', '<=', $date)
            ->where(fn (Builder $builder) => $builder->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date));
    }

    /** @return HasMany<WorkScheduleDay, $this> */
    public function days(): HasMany
    {
        return $this->hasMany(WorkScheduleDay::class)->orderBy('day_of_week');
    }

    /** @return HasMany<EmployeeScheduleAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeScheduleAssignment::class);
    }
}
