<?php

namespace App\Enums;

enum LeaveLedgerEntryType: string
{
    case Opening = 'opening';
    case Entitlement = 'entitlement';
    case Adjustment = 'adjustment';
    case Usage = 'usage';
    case Cancellation = 'cancellation';
    case Expiry = 'expiry';
    case CarryForward = 'carry_forward';
    case Reversal = 'reversal';
}
