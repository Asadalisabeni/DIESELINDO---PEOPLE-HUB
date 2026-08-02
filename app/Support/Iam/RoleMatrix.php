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
        'organization.view',
        'organization.manage',
        'entity-access.manage',
        'documents.view',
        'documents.upload',
        'documents.download',
        'contracts.manage',
        'employee-financial.view',
    ];

    /** @var array<string, list<string>> */
    public const ROLES = [
        'Super Admin' => self::PERMISSIONS,
        'Group HR Admin' => [
            'employees.view', 'employees.create', 'employees.update', 'employees.view-sensitive',
            'salaries.view', 'salaries.update', 'payroll.prepare', 'payroll.review',
            'payslips.publish', 'reports.export', 'audit.view', 'iam.manage',
            'organization.view', 'organization.manage', 'entity-access.manage',
            'documents.view', 'documents.upload', 'documents.download', 'contracts.manage',
            'employee-financial.view',
        ],
        'Company HR Admin' => [
            'employees.view', 'employees.create', 'employees.update', 'employees.view-sensitive',
            'salaries.view', 'payroll.prepare', 'payslips.publish', 'reports.export',
            'organization.view', 'organization.manage',
            'documents.view', 'documents.upload', 'documents.download', 'contracts.manage',
            'employee-financial.view',
        ],
        'Payroll Administrator' => [
            'employees.view', 'employees.view-sensitive', 'salaries.view', 'salaries.update',
            'payroll.prepare', 'payslips.publish', 'reports.export',
            'organization.view', 'employee-financial.view',
        ],
        'Finance Reviewer' => [
            'salaries.view', 'payroll.review', 'reports.export', 'organization.view', 'employee-financial.view',
        ],
        'Final Payroll Approver' => [
            'salaries.view', 'payroll.approve', 'payroll.lock', 'reports.export', 'organization.view',
        ],
        'Branch Manager' => [
            'employees.view', 'reports.export', 'organization.view',
        ],
        'Supervisor' => [
            'employees.view', 'organization.view',
        ],
        'Employee' => [],
        'Auditor' => [
            'employees.view', 'employees.view-sensitive', 'salaries.view', 'reports.export', 'audit.view',
            'organization.view', 'documents.view', 'documents.download', 'employee-financial.view',
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
