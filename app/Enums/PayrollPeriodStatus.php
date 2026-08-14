<?php

namespace App\Enums;

enum PayrollPeriodStatus: string
{
    case Open = 'open';
    case Processing = 'processing';
    case Closed = 'closed';
}
