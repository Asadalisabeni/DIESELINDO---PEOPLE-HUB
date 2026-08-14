<?php

namespace App\Models;

use App\Enums\SalaryComponentType;
use App\Models\Concerns\BelongsToLegalEntity;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryComponent extends Model
{
    use BelongsToLegalEntity, HasPublicId;

    protected $fillable = [
        'legal_entity_id', 'code', 'name', 'type', 'calculation_type', 'taxable', 'bpjs_eligible',
        'currency', 'rounding_scale', 'rounding_mode', 'effective_from', 'effective_to', 'status',
        'approved_by', 'approved_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => SalaryComponentType::class, 'taxable' => 'boolean', 'bpjs_eligible' => 'boolean',
            'effective_from' => 'date', 'effective_to' => 'date', 'approved_at' => 'immutable_datetime',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeEffectiveOn(Builder $query, string $date): Builder
    {
        return $query->where('status', 'active')->whereDate('effective_from', '<=', $date)
            ->where(fn (Builder $period) => $period->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date));
    }

    public function componentType(): SalaryComponentType
    {
        return SalaryComponentType::from((string) $this->getRawOriginal('type'));
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return HasMany<EmployeeSalaryComponent, $this> */
    public function employeeLines(): HasMany
    {
        return $this->hasMany(EmployeeSalaryComponent::class);
    }
}
