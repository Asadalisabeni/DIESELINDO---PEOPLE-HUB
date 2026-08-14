<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLegalEntity;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PayrollValidationFinding extends Model
{
    use BelongsToLegalEntity, HasPublicId;

    protected $fillable = ['legal_entity_id', 'payroll_run_id', 'payroll_run_employee_id', 'severity', 'code', 'message_key', 'details', 'status'];

    protected $hidden = ['details'];

    protected function casts(): array
    {
        return ['details' => 'encrypted:array'];
    }

    protected static function booted(): void
    {
        static::updating(static fn (): never => throw new LogicException('Payroll validation findings are immutable.'));
        static::deleting(static fn (): never => throw new LogicException('Payroll validation findings are immutable.'));
    }

    /** @return BelongsTo<PayrollRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    /** @return BelongsTo<PayrollRunEmployee, $this> */
    public function runEmployee(): BelongsTo
    {
        return $this->belongsTo(PayrollRunEmployee::class, 'payroll_run_employee_id');
    }
}
