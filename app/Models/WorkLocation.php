<?php

namespace App\Models;

use App\Enums\MasterStatus;
use App\Models\Concerns\BelongsToLegalEntity;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkLocation extends Model
{
    use BelongsToLegalEntity, HasPublicId;

    protected $fillable = ['legal_entity_id', 'branch_id', 'code', 'name', 'address', 'timezone', 'status', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['status' => MasterStatus::class];
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
