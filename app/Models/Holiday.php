<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLegalEntity;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use BelongsToLegalEntity, HasPublicId;

    protected $fillable = [
        'legal_entity_id', 'branch_id', 'holiday_date', 'name', 'type', 'source', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return ['holiday_date' => 'date'];
    }
}
