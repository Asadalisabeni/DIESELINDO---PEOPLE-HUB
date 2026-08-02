<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case PendingReview = 'pending_review';
    case Valid = 'valid';
    case Expired = 'expired';
    case Replaced = 'replaced';
    case Archived = 'archived';
}
