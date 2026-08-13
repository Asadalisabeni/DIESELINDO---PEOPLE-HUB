<?php

namespace App\Enums;

enum ApprovalActionType: string
{
    case Submit = 'submit';
    case Approve = 'approve';
    case Reject = 'reject';
    case RequestRevision = 'request_revision';
    case Cancel = 'cancel';
    case Escalate = 'escalate';
}
