<?php

namespace App\Models;

use App\Enums\SalaryHistoryStatus;
use App\Models\Concerns\BelongsToLegalEntity;
use App\Models\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class SalaryHistory extends Model
{
    use BelongsToLegalEntity, HasPublicId;

    protected $fillable = [
        'legal_entity_id', 'employee_id', 'currency', 'effective_from', 'effective_to', 'status', 'reason',
        'monthly_income_total', 'monthly_deduction_total', 'version_checksum', 'created_by', 'approved_by', 'approved_at',
    ];

    protected $hidden = ['reason'];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date', 'effective_to' => 'date', 'status' => SalaryHistoryStatus::class,
            'reason' => 'encrypted', 'monthly_income_total' => 'decimal:4', 'monthly_deduction_total' => 'decimal:4',
            'approved_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (SalaryHistory $history): void {
            if ($history->getRawOriginal('status') === SalaryHistoryStatus::Approved->value) {
                throw new LogicException('Approved salary histories are immutable.');
            }
        });
        static::deleting(static fn (): never => throw new LogicException('Salary histories cannot be deleted.'));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOverlapping(Builder $query, string $from, string $to): Builder
    {
        return $query->whereDate('effective_from', '<=', $to)
            ->where(fn (Builder $period) => $period->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from));
    }

    public function historyStatus(): SalaryHistoryStatus
    {
        return SalaryHistoryStatus::from((string) $this->getRawOriginal('status'));
    }

    public function effectiveFrom(): CarbonImmutable
    {
        return CarbonImmutable::parse((string) $this->getRawOriginal('effective_from'));
    }

    public function effectiveTo(): ?CarbonImmutable
    {
        $value = $this->getRawOriginal('effective_to');

        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value) : null;
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return HasMany<EmployeeSalaryComponent, $this> */
    public function components(): HasMany
    {
        return $this->hasMany(EmployeeSalaryComponent::class)->orderBy('sequence');
    }
}
