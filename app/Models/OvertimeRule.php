<?php

namespace App\Models;

use App\Enums\OvertimeDayType;
use App\Models\Concerns\BelongsToLegalEntity;
use App\Models\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OvertimeRule extends Model
{
    use BelongsToLegalEntity, HasPublicId;

    protected $fillable = [
        'legal_entity_id', 'code', 'name', 'day_type', 'calculation_method', 'minimum_minutes',
        'rounding_increment_minutes', 'rounding_mode', 'maximum_minutes', 'segment_rules',
        'meal_threshold_minutes', 'meal_allowance_idr', 'transport_threshold_minutes',
        'transport_allowance_idr', 'eligibility', 'effective_from', 'effective_to', 'status',
        'approved_by', 'approved_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'day_type' => OvertimeDayType::class,
            'minimum_minutes' => 'integer', 'rounding_increment_minutes' => 'integer',
            'maximum_minutes' => 'integer', 'segment_rules' => 'array',
            'meal_threshold_minutes' => 'integer', 'meal_allowance_idr' => 'integer',
            'transport_threshold_minutes' => 'integer', 'transport_allowance_idr' => 'integer',
            'effective_from' => 'date', 'effective_to' => 'date', 'approved_at' => 'immutable_datetime',
        ];
    }

    public function dayType(): OvertimeDayType
    {
        return OvertimeDayType::from((string) $this->getRawOriginal('day_type'));
    }

    public function effectiveFrom(): CarbonImmutable
    {
        $value = $this->getAttribute('effective_from');
        if (! $value instanceof CarbonInterface) {
            throw new \LogicException('Overtime rule effective date is invalid.');
        }

        return CarbonImmutable::instance($value);
    }

    /** @return list<array{up_to_minutes: ?int, multiplier_hundredths: int}> */
    public function segmentRules(): array
    {
        $value = $this->getAttribute('segment_rules');
        if (! is_array($value)) {
            throw new \LogicException('Overtime segment rules are invalid.');
        }

        return array_values(array_map(static fn (array $segment): array => [
            'up_to_minutes' => $segment['up_to_minutes'] === null ? null : (int) $segment['up_to_minutes'],
            'multiplier_hundredths' => (int) $segment['multiplier_hundredths'],
        ], $value));
    }

    /** @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeEffectiveOn(Builder $query, string $date): Builder
    {
        return $query->whereDate('effective_from', '<=', $date)
            ->where(fn (Builder $period) => $period->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date));
    }

    /** @return HasMany<OvertimeRequest, $this> */
    public function requests(): HasMany
    {
        return $this->hasMany(OvertimeRequest::class);
    }
}
