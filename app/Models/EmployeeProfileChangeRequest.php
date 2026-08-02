<?php

namespace App\Models;

use App\Enums\ProfileChangeStatus;
use App\Enums\ProfileChangeType;
use App\Models\Concerns\BelongsToLegalEntity;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeProfileChangeRequest extends Model
{
    use BelongsToLegalEntity, HasPublicId;

    protected $fillable = [
        'legal_entity_id', 'employee_id', 'requested_by', 'type', 'status',
        'current_values', 'proposed_values', 'snapshot_fingerprint', 'reason',
        'attachment_document_id', 'manual_follow_up_required', 'reviewed_by',
        'review_notes', 'submitted_at', 'reviewed_at', 'applied_at', 'cancelled_at',
    ];

    protected $hidden = ['snapshot_fingerprint'];

    protected function casts(): array
    {
        return [
            'type' => ProfileChangeType::class,
            'status' => ProfileChangeStatus::class,
            'current_values' => 'encrypted:array',
            'proposed_values' => 'encrypted:array',
            'reason' => 'encrypted',
            'review_notes' => 'encrypted',
            'manual_follow_up_required' => 'boolean',
            'submitted_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
            'applied_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return BelongsTo<EmployeeDocument, $this> */
    public function attachmentDocument(): BelongsTo
    {
        return $this->belongsTo(EmployeeDocument::class, 'attachment_document_id');
    }

    public function changeType(): ProfileChangeType
    {
        return ProfileChangeType::from((string) $this->getRawOriginal('type'));
    }

    public function changeStatus(): ProfileChangeStatus
    {
        return ProfileChangeStatus::from((string) $this->getRawOriginal('status'));
    }

    /** @return array<string, mixed> */
    public function currentValues(): array
    {
        $values = $this->getAttribute('current_values');

        return is_array($values) ? $values : [];
    }

    /** @return array<string, mixed> */
    public function proposedValues(): array
    {
        $values = $this->getAttribute('proposed_values');

        return is_array($values) ? $values : [];
    }
}
