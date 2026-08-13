<?php

namespace App\Models;

use App\Enums\AttendanceSourceType;
use App\Models\Concerns\BelongsToLegalEntity;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceSource extends Model
{
    use BelongsToLegalEntity, HasPublicId;

    protected $fillable = [
        'legal_entity_id', 'code', 'name', 'type', 'adapter', 'validation_rules', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return ['type' => AttendanceSourceType::class, 'validation_rules' => 'array'];
    }

    public function sourceType(): AttendanceSourceType
    {
        return AttendanceSourceType::from((string) $this->getRawOriginal('type'));
    }

    /** @return array<string, mixed> */
    public function validationRules(): array
    {
        $rules = $this->getAttribute('validation_rules');

        return is_array($rules) ? $rules : [];
    }

    /** @return HasMany<AttendanceEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(AttendanceEvent::class);
    }
}
