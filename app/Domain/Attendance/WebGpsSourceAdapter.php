<?php

namespace App\Domain\Attendance;

use App\Models\AttendanceSource;

final class WebGpsSourceAdapter implements AttendanceSourceAdapter
{
    public function normalize(AttendanceSource $source, array $payload): array
    {
        return [
            'external_event_id' => (string) $payload['external_event_id'],
            'event_type' => (string) $payload['event_type'],
            'occurred_at' => (string) $payload['occurred_at'],
            'device_recorded_at' => $payload['device_recorded_at'] ?? null,
            'latitude' => $payload['latitude'] ?? null,
            'longitude' => $payload['longitude'] ?? null,
            'gps_accuracy_meters' => $payload['gps_accuracy_meters'] ?? null,
            'selfie_document_id' => $payload['selfie_document_id'] ?? null,
            'activity' => $payload['activity'] ?? null,
            'destination' => $payload['destination'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'device_info' => $payload['device_info'] ?? null,
            'was_offline' => (bool) ($payload['was_offline'] ?? false),
        ];
    }
}
