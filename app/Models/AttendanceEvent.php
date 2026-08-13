<?php

namespace App\Models;

use App\Casts\UtcImmutableDateTime;
use App\Enums\AttendanceEventStatus;
use App\Enums\AttendanceEventType;
use App\Models\Concerns\BelongsToLegalEntity;
use App\Models\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LogicException;

class AttendanceEvent extends Model
{
    use BelongsToLegalEntity, HasPublicId;

    protected $fillable = [
        'legal_entity_id', 'employee_id', 'attendance_source_id', 'external_event_id',
        'idempotency_hash', 'event_type', 'occurred_at', 'device_recorded_at', 'received_at',
        'latitude', 'longitude', 'gps_accuracy_meters', 'selfie_document_id', 'activity',
        'destination', 'notes', 'device_info', 'was_offline', 'status', 'anomaly_codes',
        'payload_hash', 'payroll_eligibility', 'created_by',
    ];

    protected $hidden = [
        'latitude', 'longitude', 'activity', 'destination', 'notes', 'device_info',
        'idempotency_hash', 'payload_hash',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => AttendanceEventType::class,
            'status' => AttendanceEventStatus::class,
            'occurred_at' => UtcImmutableDateTime::class,
            'device_recorded_at' => UtcImmutableDateTime::class,
            'received_at' => UtcImmutableDateTime::class,
            'latitude' => 'encrypted',
            'longitude' => 'encrypted',
            'activity' => 'encrypted',
            'destination' => 'encrypted',
            'notes' => 'encrypted',
            'device_info' => 'encrypted',
            'was_offline' => 'boolean',
            'anomaly_codes' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static fn (): never => throw new LogicException('Raw attendance events are immutable.'));
        static::deleting(static fn (): never => throw new LogicException('Raw attendance events are immutable.'));
    }

    public function eventType(): AttendanceEventType
    {
        return AttendanceEventType::from((string) $this->getRawOriginal('event_type'));
    }

    public function eventStatus(): AttendanceEventStatus
    {
        return AttendanceEventStatus::from((string) $this->getRawOriginal('status'));
    }

    public function occurredAt(): CarbonImmutable
    {
        $value = $this->getAttribute('occurred_at');
        if (! $value instanceof CarbonImmutable) {
            throw new LogicException('Attendance occurrence time is invalid.');
        }

        return $value;
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<AttendanceSource, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(AttendanceSource::class, 'attendance_source_id');
    }

    /** @return BelongsToMany<AttendanceRecord, $this> */
    public function records(): BelongsToMany
    {
        return $this->belongsToMany(AttendanceRecord::class, 'attendance_record_events');
    }
}
