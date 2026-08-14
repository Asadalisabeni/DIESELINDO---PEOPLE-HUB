<?php

namespace App\Models;

use App\Enums\PayrollPeriodStatus;
use App\Models\Concerns\BelongsToLegalEntity;
use App\Models\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    use BelongsToLegalEntity, HasPublicId;

    protected $fillable = ['legal_entity_id', 'payroll_group_id', 'period_key', 'period_type', 'payroll_start', 'payroll_end', 'attendance_cutoff_start', 'attendance_cutoff_end', 'payment_date', 'status', 'calendar_snapshot', 'created_by'];

    protected function casts(): array
    {
        return ['payroll_start' => 'date', 'payroll_end' => 'date', 'attendance_cutoff_start' => 'date', 'attendance_cutoff_end' => 'date', 'payment_date' => 'date', 'status' => PayrollPeriodStatus::class, 'calendar_snapshot' => 'array'];
    }

    public function periodStatus(): PayrollPeriodStatus
    {
        return PayrollPeriodStatus::from((string) $this->getRawOriginal('status'));
    }

    public function payrollStart(): CarbonImmutable
    {
        return CarbonImmutable::parse((string) $this->getRawOriginal('payroll_start'));
    }

    public function payrollEnd(): CarbonImmutable
    {
        return CarbonImmutable::parse((string) $this->getRawOriginal('payroll_end'));
    }

    public function cutoffStart(): CarbonImmutable
    {
        return CarbonImmutable::parse((string) $this->getRawOriginal('attendance_cutoff_start'));
    }

    public function cutoffEnd(): CarbonImmutable
    {
        return CarbonImmutable::parse((string) $this->getRawOriginal('attendance_cutoff_end'));
    }

    public function paymentDate(): CarbonImmutable
    {
        return CarbonImmutable::parse((string) $this->getRawOriginal('payment_date'));
    }

    /** @return BelongsTo<PayrollGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(PayrollGroup::class, 'payroll_group_id');
    }

    /** @return HasMany<PayrollRun, $this> */
    public function runs(): HasMany
    {
        return $this->hasMany(PayrollRun::class);
    }
}
