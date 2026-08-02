<?php

namespace App\Models;

use App\Enums\MasterStatus;
use App\Models\Concerns\BelongsToLegalEntity;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use BelongsToLegalEntity, HasPublicId;

    protected $fillable = ['legal_entity_id', 'code', 'name', 'address', 'timezone', 'status', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['status' => MasterStatus::class];
    }

    /** @return HasMany<Department, $this> */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }
}
