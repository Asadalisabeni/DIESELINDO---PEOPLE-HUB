<?php

namespace App\Enums;

enum OvertimeRequestStatus: string
{
    case PendingManager = 'pending_manager';
    case ApprovedWaitingActual = 'approved_waiting_actual';
    case PendingHrValidation = 'pending_hr_validation';
    case PendingPayroll = 'pending_payroll';
    case PayrollEligible = 'payroll_eligible';
    case Rejected = 'rejected';
    case RevisionRequested = 'revision_requested';
    case Cancelled = 'cancelled';
}
