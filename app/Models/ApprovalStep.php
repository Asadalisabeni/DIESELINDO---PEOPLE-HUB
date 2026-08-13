<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalStep extends Model
{
    protected $fillable = [
        'approval_definition_id', 'step_order', 'name', 'resolver_type', 'required_permission',
        'minimum_approvals', 'due_after_hours', 'conditions',
    ];

    protected function casts(): array
    {
        return [
            'step_order' => 'integer', 'minimum_approvals' => 'integer',
            'due_after_hours' => 'integer', 'conditions' => 'array',
        ];
    }

    /** @return BelongsTo<ApprovalDefinition, $this> */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(ApprovalDefinition::class, 'approval_definition_id');
    }
}
