<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLegalEntity;
use App\Models\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class PayrollRunEmployee extends Model
{
    use BelongsToLegalEntity, HasPublicId;

    protected $fillable = ['legal_entity_id', 'payroll_run_id', 'employee_id', 'employment_history_id', 'salary_history_id', 'employee_snapshot', 'bank_snapshot', 'salary_snapshot', 'service_from', 'service_to', 'payable_days', 'period_days', 'gross_total', 'deduction_total', 'employer_total', 'tax_total', 'bpjs_total', 'net_total', 'validation_status', 'snapshot_checksum'];

    protected $hidden = ['bank_snapshot'];

    protected function casts(): array
    {
        return ['employee_snapshot' => 'array', 'bank_snapshot' => 'encrypted:array', 'salary_snapshot' => 'array', 'service_from' => 'date', 'service_to' => 'date', 'gross_total' => 'decimal:4', 'deduction_total' => 'decimal:4', 'employer_total' => 'decimal:4', 'tax_total' => 'decimal:4', 'bpjs_total' => 'decimal:4', 'net_total' => 'decimal:4'];
    }

    protected static function booted(): void
    {
        static::updating(static fn (): never => throw new LogicException('Payroll employee snapshots are immutable.'));
        static::deleting(static fn (): never => throw new LogicException('Payroll employee snapshots are immutable.'));
    }

    /** @return array{employee_number?: string, full_name?: string} */
    public function employeeSnapshot(): array
    {
        $value = $this->getAttribute('employee_snapshot');

        return is_array($value) ? $value : [];
    }

    public function serviceFrom(): CarbonImmutable
    {
        return CarbonImmutable::parse((string) $this->getRawOriginal('service_from'));
    }

    public function serviceTo(): CarbonImmutable
    {
        return CarbonImmutable::parse((string) $this->getRawOriginal('service_to'));
    }

    /** @return BelongsTo<PayrollRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return HasMany<PayrollItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }
}
