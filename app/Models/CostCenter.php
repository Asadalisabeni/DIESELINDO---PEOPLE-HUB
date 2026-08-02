<?php

namespace App\Models;

use App\Enums\MasterStatus;
use App\Models\Concerns\BelongsToLegalEntity;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class CostCenter extends Model
{
    use BelongsToLegalEntity, HasPublicId;

    protected $fillable = ['legal_entity_id', 'code', 'name', 'external_code', 'status', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['status' => MasterStatus::class];
    }
}
