# Phase 4 exit review

Status: Ready for stakeholder review; not locked until explicit approval.

## Delivered

- Fortify email/password login, email verification, reset/change password, and TOTP 2FA with recovery codes.
- Public registration and passkeys disabled.
- Strong password default, reset throttling, login rate limiting, and temporary account lock.
- Database session inventory, masked IP display, logout-other-devices, and automatic revocation for inactive accounts.
- Ten roles, fifteen granular permissions, Gate/Policy enforcement, idempotent role seeder, and secure interactive bootstrap-admin command.
- IAM provisioning and role/status management guarded by `iam.manage`.
- Separate append-only administrative and authentication audit streams.
- HMAC email lookup and encrypted IP, user-agent, and authentication context.
- Indonesian and English login, verification, 2FA, security, IAM, and audit interfaces.
- Security tests for feature exposure, authentication, lock, anti-enumeration, email verification, encrypted logging, 2FA setup, least privilege, provisioning, session revocation, and immutability.

## Deliberate Phase 5 boundary

Legal entity, company, branch, manager hierarchy, and employee ownership do not yet exist as tables. Phase 4 therefore exposes no employee/salary/payroll business endpoint. Roles express capabilities, while all future business controllers must combine those capabilities with the Phase 5 organization scope policy. This is a closed security boundary, not an omitted global-scope implementation.

## Review evidence required before lock

- Full automated quality gate is green.
- Fresh SQLite migration test and real MySQL migration/status are green.
- Browser QA covers Indonesian and English login, dashboard, security, IAM, audit, keyboard focus, responsive layout, and dark mode.
- GitHub Actions is green on the Phase 4 draft pull request.
- Reviewer confirms the role matrix and production cookie/password settings.

Once accepted, merge through `develop` to `main` and create the immutable `phase-4-complete` tag. Do not start Phase 5 implementation before that lock.
