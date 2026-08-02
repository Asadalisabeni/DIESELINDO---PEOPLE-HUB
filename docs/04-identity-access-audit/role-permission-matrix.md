# Role and permission matrix

## Authorization model

Permission checks use Spatie Laravel Permission through Laravel Gate and project policies. `Super Admin` receives a Gate-level override; every other role is restricted to its explicitly assigned permissions. Role and permission seeding is idempotent.

The Phase 4 role grant answers only **what** an actor may do. Legal-entity, company, branch, manager-report, and employee-self scope answer **which records** the actor may use. Those organization relations are introduced in Phase 5, so no employee, salary, payroll, or report controller is exposed in Phase 4. Future controllers must require both the permission and the organization-scope policy described in ADR 0002. This prevents a temporary global role grant from becoming accidental cross-company access.

## Matrix

| Role | Granted permissions |
|---|---|
| Super Admin | All Phase 4 permissions |
| Group HR Admin | `employees.view`, `employees.create`, `employees.update`, `employees.view-sensitive`, `salaries.view`, `salaries.update`, `payroll.prepare`, `payroll.review`, `payslips.publish`, `reports.export`, `audit.view`, `iam.manage` |
| Company HR Admin | `employees.view`, `employees.create`, `employees.update`, `employees.view-sensitive`, `salaries.view`, `payroll.prepare`, `payslips.publish`, `reports.export` |
| Payroll Administrator | `employees.view`, `employees.view-sensitive`, `salaries.view`, `salaries.update`, `payroll.prepare`, `payslips.publish`, `reports.export` |
| Finance Reviewer | `salaries.view`, `payroll.review`, `reports.export` |
| Final Payroll Approver | `salaries.view`, `payroll.approve`, `payroll.lock`, `reports.export` |
| Branch Manager | `employees.view`, `reports.export` |
| Supervisor | `employees.view` |
| Employee | No administrative permission; self-service policies arrive with employee ownership in later phases |
| Auditor | `employees.view`, `employees.view-sensitive`, `salaries.view`, `reports.export`, `audit.view` |

`payroll.reopen` is restricted to `Super Admin` because reopening a locked payroll is an exceptional administrative control. `iam.manage` is restricted to `Super Admin` and `Group HR Admin`, but it is not an unrestricted role-escalation permission. Group HR Admin may delegate only Company HR Admin, Branch Manager, Supervisor, or Employee. Only Super Admin may grant Super Admin, Group HR Admin, payroll/finance/approval, or Auditor roles. Non-super administrators cannot list or modify accounts that already hold a protected role. A signed-in administrator cannot deactivate their own account or remove their own Super Admin role through the UI.

## Complete permission catalog

`employees.view`, `employees.create`, `employees.update`, `employees.view-sensitive`, `salaries.view`, `salaries.update`, `payroll.prepare`, `payroll.review`, `payroll.approve`, `payroll.lock`, `payroll.reopen`, `payslips.publish`, `reports.export`, `audit.view`, and `iam.manage`.

## Provisioning

The IAM screen creates an active, unverified account with a cryptographically random unusable password, assigns one approved role, and starts the password-reset notification flow. The user must set a compliant password and verify their corporate email before reaching protected pages. No default user or known password is inserted by `DatabaseSeeder`.
