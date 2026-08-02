<?php

namespace App\Support\Iam;

use App\Models\User;

final class RoleMatrix
{
    /** @var list<string> */
    public const GROUP_HR_DELEGABLE_ROLES = [
        'Company HR Admin',
        'Branch Manager',
        'Supervisor',
        'Employee',
    ];

    /** @var list<string> */
    public const PERMISSIONS = [
        'employees.view',
        'employees.create',
        'employees.update',
        'employees.view-sensitive',
        'salaries.view',
        'salaries.update',
        'payroll.prepare',
        'payroll.review',
        'payroll.approve',
        'payroll.lock',
        'payroll.reopen',
        'payslips.publish',
        'reports.export',
        'audit.view',
        'iam.manage',
    ];

    /** @var array<string, list<string>> */
    public const ROLES = [
        'Super Admin' => self::PERMISSIONS,
        'Group HR Admin' => [
            'employees.view', 'employees.create', 'employees.update', 'employees.view-sensitive',
            'salaries.view', 'salaries.update', 'payroll.prepare', 'payroll.review',
            'payslips.publish', 'reports.export', 'audit.view', 'iam.manage',
        ],
        'Company HR Admin' => [
            'employees.view', 'employees.create', 'employees.update', 'employees.view-sensitive',
            'salaries.view', 'payroll.prepare', 'payslips.publish', 'reports.export',
        ],
        'Payroll Administrator' => [
            'employees.view', 'employees.view-sensitive', 'salaries.view', 'salaries.update',
            'payroll.prepare', 'payslips.publish', 'reports.export',
        ],
        'Finance Reviewer' => [
            'salaries.view', 'payroll.review', 'reports.export',
        ],
        'Final Payroll Approver' => [
            'salaries.view', 'payroll.approve', 'payroll.lock', 'reports.export',
        ],
        'Branch Manager' => [
            'employees.view', 'reports.export',
        ],
        'Supervisor' => [
            'employees.view',
        ],
        'Employee' => [],
        'Auditor' => [
            'employees.view', 'employees.view-sensitive', 'salaries.view', 'reports.export', 'audit.view',
        ],
    ];

    /** @return list<string> */
    public static function roleNames(): array
    {
        return array_keys(self::ROLES);
    }

    /** @return list<string> */
    public static function assignableRoleNames(?User $actor): array
    {
        return $actor?->hasRole('Super Admin') === true
            ? self::roleNames()
            : self::GROUP_HR_DELEGABLE_ROLES;
    }
}
