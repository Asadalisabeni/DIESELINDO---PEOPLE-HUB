<?php

namespace App\Models;

use App\Enums\LeaveLedgerEntryType;
use App\Models\Concerns\BelongsToLegalEntity;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class LeaveLedgerEntry extends Model
{
    use BelongsToLegalEntity, HasPublicId;

    protected $fillable = [
        'legal_entity_id', 'leave_entitlement_id', 'employee_id', 'leave_request_id', 'entry_type',
        'quantity', 'effective_date', 'reference_key', 'reversal_of_id', 'reason', 'created_by',
    ];

    protected $hidden = ['reason'];

    protected function casts(): array
    {
        return [
            'entry_type' => LeaveLedgerEntryType::class, 'quantity' => 'decimal:2',
            'effective_date' => 'date', 'reason' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static fn (): never => throw new LogicException('Leave ledger entries are immutable.'));
        static::deleting(static fn (): never => throw new LogicException('Leave ledger entries are immutable.'));
    }

    /** @return BelongsTo<LeaveEntitlement, $this> */
    public function entitlement(): BelongsTo
    {
        return $this->belongsTo(LeaveEntitlement::class, 'leave_entitlement_id');
    }

    /** @return BelongsTo<LeaveRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class, 'leave_request_id');
    }
}
