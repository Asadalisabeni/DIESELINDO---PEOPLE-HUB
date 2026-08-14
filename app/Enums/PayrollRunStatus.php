<?php

namespace App\Enums;

enum PayrollRunStatus: string
{
    case Draft = 'draft';
    case Calculated = 'calculated';
    case Validated = 'validated';
    case Reviewed = 'reviewed';
    case Approved = 'approved';
    case Locked = 'locked';
    case Published = 'published';
    case Reopened = 'reopened';
    case Superseded = 'superseded';
}
