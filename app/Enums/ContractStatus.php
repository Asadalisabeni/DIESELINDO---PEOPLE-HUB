<?php

namespace App\Enums;

enum ContractStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Superseded = 'superseded';
    case Terminated = 'terminated';
}
