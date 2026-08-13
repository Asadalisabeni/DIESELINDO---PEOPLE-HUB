<?php

namespace App\Enums;

enum AttendanceSourceType: string
{
    case Fingerprint = 'fingerprint';
    case MobileGps = 'mobile_gps';
    case Web = 'web';
    case OfflineMobile = 'offline_mobile';
    case ManualAdjustment = 'manual_adjustment';
    case Import = 'import';
}
