<?php

namespace App\Support\Iam;

use App\Models\User;

final class RoleMatrix
{
    /** @var list<string> */
    public const SELF_SERVICE_PERMISSIONS = [
        'ess.access',
        'ess.profile.update',
        'ess.profile-change.request',
        'ess.documents.download',
        'notifications.view',
        'attendance.access',
        'attendance.clock',
        'attendance.corrections.request',
        'leave.access',
        'leave.request',
    ];

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
        ...self::SELF_SERVICE_PERMISSIONS,
        'ess.profile-change.review',
        'attendance.team.view',
        'attendance.corrections.approve-manager',
        'attendance.view',
        'attendance.manage',
        'attendance.corrections.review',
        'attendance.import',
        'leave.team.view',
        'leave.approve-manager',
        'leave.view',
        'leave.manage',
        'leave.review',
        'leave.adjust',
        'leave.report',
        'leave.confirm-payroll',
    ];

    /** @var array<string, list<string>> */
    public const ROLES = [
        'Super Admin' => self::PERMISSIONS,
        'Group HR Admin' => [
            ...self::SELF_SERVICE_PERMISSIONS,
            'employees.view', 'employees.create', 'employees.update', 'employees.view-sensitive',
            'salaries.view', 'salaries.update', 'payroll.prepare', 'payroll.review',
            'payslips.publish', 'reports.export', 'audit.view', 'iam.manage',
            'organization.view', 'organization.manage', 'entity-access.manage',
            'documents.view', 'documents.upload', 'documents.download', 'contracts.manage',
            'employee-financial.view',
            'ess.profile-change.review',
            'attendance.team.view', 'attendance.corrections.approve-manager',
            'attendance.view', 'attendance.manage', 'attendance.corrections.review', 'attendance.import',
            'leave.team.view', 'leave.approve-manager', 'leave.view', 'leave.manage',
            'leave.review', 'leave.adjust', 'leave.report',
        ],
        'Company HR Admin' => [
            ...self::SELF_SERVICE_PERMISSIONS,
            'employees.view', 'employees.create', 'employees.update', 'employees.view-sensitive',
            'salaries.view', 'payroll.prepare', 'payslips.publish', 'reports.export',
            'organization.view', 'organization.manage',
            'documents.view', 'documents.upload', 'documents.download', 'contracts.manage',
            'employee-financial.view',
            'ess.profile-change.review',
            'attendance.team.view', 'attendance.corrections.approve-manager',
            'attendance.view', 'attendance.manage', 'attendance.corrections.review', 'attendance.import',
            'leave.team.view', 'leave.approve-manager', 'leave.view', 'leave.manage',
            'leave.review', 'leave.adjust', 'leave.report',
        ],
        'Payroll Administrator' => [
            ...self::SELF_SERVICE_PERMISSIONS,
            'employees.view', 'employees.view-sensitive', 'salaries.view', 'salaries.update',
            'payroll.prepare', 'payslips.publish', 'reports.export',
            'organization.view', 'employee-financial.view',
            'attendance.view',
            'leave.view', 'leave.report', 'leave.confirm-payroll',
        ],
        'Finance Reviewer' => [
            ...self::SELF_SERVICE_PERMISSIONS,
            'salaries.view', 'payroll.review', 'reports.export', 'organization.view', 'employee-financial.view',
        ],
        'Final Payroll Approver' => [
            ...self::SELF_SERVICE_PERMISSIONS,
            'salaries.view', 'payroll.approve', 'payroll.lock', 'reports.export', 'organization.view',
        ],
        'Branch Manager' => [
            ...self::SELF_SERVICE_PERMISSIONS,
            'employees.view', 'reports.export', 'organization.view',
            'attendance.team.view', 'attendance.corrections.approve-manager', 'attendance.view',
            'leave.team.view', 'leave.approve-manager', 'leave.view', 'leave.report',
        ],
        'Supervisor' => [
            ...self::SELF_SERVICE_PERMISSIONS,
            'employees.view', 'organization.view',
            'attendance.team.view', 'attendance.corrections.approve-manager',
            'leave.team.view', 'leave.approve-manager',
        ],
        'Employee' => self::SELF_SERVICE_PERMISSIONS,
        'Auditor' => [
            ...self::SELF_SERVICE_PERMISSIONS,
            'employees.view', 'employees.view-sensitive', 'salaries.view', 'reports.export', 'audit.view',
            'organization.view', 'documents.view', 'documents.download', 'employee-financial.view',
            'attendance.view',
            'leave.view', 'leave.report',
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
