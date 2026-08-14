<?php

namespace App\Enums;

enum SalaryComponentType: string
{
    case Income = 'income';
    case Deduction = 'deduction';
    case Employer = 'employer';
}
