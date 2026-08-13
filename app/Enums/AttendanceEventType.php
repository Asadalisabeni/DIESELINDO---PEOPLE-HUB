<?php

namespace App\Enums;

enum AttendanceEventType: string
{
    case CheckIn = 'check_in';
    case CheckOut = 'check_out';
}
