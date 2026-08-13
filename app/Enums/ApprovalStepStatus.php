<?php

namespace App\Enums;

enum ApprovalStepStatus: string
{
    case Waiting = 'waiting';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case RevisionRequested = 'revision_requested';
    case Cancelled = 'cancelled';
}
