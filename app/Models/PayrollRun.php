<?php

namespace App\Models;

use App\Enums\PayrollRunStatus;
use App\Models\Concerns\BelongsToLegalEntity;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class PayrollRun extends Model
{
    use BelongsToLegalEntity, HasPublicId;

    protected $fillable = ['legal_entity_id', 'payroll_period_id', 'run_type', 'version', 'status', 'currency', 'source_snapshot_at', 'gross_total', 'deduction_total', 'employer_total', 'tax_total', 'bpjs_total', 'net_total', 'validation_summary', 'created_by', 'calculated_by', 'validated_by', 'calculated_at', 'validated_at'];

    protected function casts(): array
    {
        return ['status' => PayrollRunStatus::class, 'source_snapshot_at' => 'immutable_datetime', 'gross_total' => 'decimal:4', 'deduction_total' => 'decimal:4', 'employer_total' => 'decimal:4', 'tax_total' => 'decimal:4', 'bpjs_total' => 'decimal:4', 'net_total' => 'decimal:4', 'validation_summary' => 'array', 'calculated_at' => 'immutable_datetime', 'validated_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (PayrollRun $run): void {
            if (! in_array($run->getRawOriginal('status'), [PayrollRunStatus::Draft->value, PayrollRunStatus::Calculated->value], true)) {
                throw new LogicException('Validated payroll runs are immutable in Phase 10.');
            }
        });
        static::deleting(static fn (): never => throw new LogicException('Payroll runs cannot be deleted.'));
    }

    public function runStatus(): PayrollRunStatus
    {
        return PayrollRunStatus::from((string) $this->getRawOriginal('status'));
    }

    /** @return BelongsTo<PayrollPeriod, $this> */
    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    /** @return HasMany<PayrollRunEmployee, $this> */
    public function employees(): HasMany
    {
        return $this->hasMany(PayrollRunEmployee::class);
    }

    /** @return HasMany<PayrollValidationFinding, $this> */
    public function findings(): HasMany
    {
        return $this->hasMany(PayrollValidationFinding::class);
    }
}
