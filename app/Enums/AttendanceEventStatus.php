<?php

namespace App\Enums;

enum AttendanceEventStatus: string
{
    case Validated = 'validated';
    case Anomalous = 'anomalous';
    case Rejected = 'rejected';
}
