<?php

namespace App\Enums;

enum DocumentClassification: string
{
    case Confidential = 'confidential';
    case Restricted = 'restricted';
}
