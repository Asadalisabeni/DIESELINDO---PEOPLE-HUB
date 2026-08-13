<?php

namespace App\Enums;

enum LeaveRequestStatus: string
{
    case PendingManager = 'pending_manager';
    case PendingHr = 'pending_hr';
    case PendingPayroll = 'pending_payroll';
    case RevisionRequested = 'revision_requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function isPending(): bool
    {
        return in_array($this, [self::PendingManager, self::PendingHr, self::PendingPayroll], true);
    }
}
