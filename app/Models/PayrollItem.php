<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLegalEntity;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PayrollItem extends Model
{
    use BelongsToLegalEntity, HasPublicId;

    protected $fillable = ['legal_entity_id', 'payroll_run_employee_id', 'salary_component_id', 'component_code', 'component_name', 'component_type', 'calculation_type', 'quantity', 'rate', 'base_amount', 'unrounded_amount', 'amount', 'currency', 'source_type', 'source_reference', 'calculation_metadata', 'sequence'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'rate' => 'decimal:4', 'base_amount' => 'decimal:4', 'unrounded_amount' => 'decimal:4', 'amount' => 'decimal:4', 'calculation_metadata' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(static fn (): never => throw new LogicException('Payroll items are immutable.'));
        static::deleting(static fn (): never => throw new LogicException('Payroll items are immutable.'));
    }

    /** @return BelongsTo<PayrollRunEmployee, $this> */
    public function runEmployee(): BelongsTo
    {
        return $this->belongsTo(PayrollRunEmployee::class, 'payroll_run_employee_id');
    }

    /** @return BelongsTo<SalaryComponent, $this> */
    public function component(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class, 'salary_component_id');
    }
}
