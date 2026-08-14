<?php

namespace App\Enums;

enum SalaryHistoryStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Superseded = 'superseded';
}
