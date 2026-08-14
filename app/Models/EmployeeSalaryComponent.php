<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLegalEntity;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class EmployeeSalaryComponent extends Model
{
    use BelongsToLegalEntity, HasPublicId;

    protected $fillable = ['legal_entity_id', 'salary_history_id', 'salary_component_id', 'sequence', 'amount', 'quantity', 'input_reference'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:4', 'quantity' => 'decimal:4'];
    }

    protected static function booted(): void
    {
        static::updating(static fn (): never => throw new LogicException('Salary component lines are immutable.'));
        static::deleting(static fn (): never => throw new LogicException('Salary component lines are immutable.'));
    }

    /** @return BelongsTo<SalaryHistory, $this> */
    public function salaryHistory(): BelongsTo
    {
        return $this->belongsTo(SalaryHistory::class);
    }

    /** @return BelongsTo<SalaryComponent, $this> */
    public function component(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class, 'salary_component_id');
    }
}
