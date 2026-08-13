<?php

namespace App\Enums;

enum OvertimeDayType: string
{
    case WorkingDay = 'working_day';
    case RestDay = 'rest_day';
    case NationalHoliday = 'national_holiday';
}
