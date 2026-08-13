<?php

namespace App\Domain\Attendance;

use App\Models\AttendanceSource;

interface AttendanceSourceAdapter
{
    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function normalize(AttendanceSource $source, array $payload): array;
}
