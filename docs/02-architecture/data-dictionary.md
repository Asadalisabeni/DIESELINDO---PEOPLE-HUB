# Phase 2.2 — Data Dictionary

## Reading guide

Ini adalah logical migration contract. `id` berarti internal `BIGINT UNSIGNED`;
resource addressable juga memiliki `public_id CHAR(26)` ULID. Tenant table
memiliki `legal_entity_id`, `created_at_utc`, `updated_at_utc`, actor/provenance
field yang relevan, dan index tenant-first. Detail final panjang/nullable harus
ditinjau kembali pada migration vertical slice tanpa mengubah invariant.

Klasifikasi: `R` Restricted, `C` Confidential, `I` Internal, `P` Public. “No
SD” berarti soft delete dilarang; lifecycle memakai status/effective end,
reversal, version, atau controlled retention.

## Identity and access

| Table | Purpose and critical columns | Keys, constraints, and lifecycle | Class / retention |
|---|---|---|---|
| `users` | Login identity: `public_id`, `employee_id?`, `name`, `email`, `email_verified_at_utc`, `password`, `status`, `locked_until_utc`, `last_login_at_utc` | unique normalized `email`; optional unique employee link; deactivate instead of delete; password/2FA never audited as value | R/C; account + security policy; No SD after business use |
| `user_legal_entity_access` | Explicit entity scope: `user_id`, `legal_entity_id`, `access_level`, `effective_from`, `effective_to`, `granted_by`, `reason` | unique user/entity/from; no overlap for same access grant; revoke by effective end | C/I; audit retention; No SD |

Role/permission package tables are introduced in Phase 4 after package schema
compatibility review. Business ownership remains with IdentityAccess; entity
scope is not encoded only in role names.

## Organization

| Table | Purpose and critical columns | Keys, constraints, and lifecycle | Class / retention |
|---|---|---|---|
| `legal_entities` | Company master: `public_id`, `code`, `legal_name`, `display_name`, encrypted `tax_identifier`, addresses, timezone, currency, `status` | global unique `code`; unique blind index for tax ID when approved; deactivate, never cascade business records | P/I/R; permanent master history |
| `branches` | Entity branch: `legal_entity_id`, `public_id`, `code`, `name`, address, timezone, `status` | unique entity/code; entity/name index; restrict entity delete | I/C; effective/deactivate |
| `divisions` | Optional division: entity, `code`, `name`, `status` | unique entity/code; no assumption that every employee has division | I; effective/deactivate |
| `departments` | Department: entity, `branch_id`, `division_id?`, `code`, `name`, `status` | unique entity/code; branch/division must match entity; indexed hierarchy | I; effective/deactivate |
| `positions` | Position: entity, `department_id`, `code`, `name`, `level?`, `status` | unique entity/code; department same entity | I; effective/deactivate |
| `work_locations` | Work site/field base: entity, branch?, `code`, `name`, address, coordinates?, radius policy reference?, timezone, status | unique entity/code; precise coordinates C when present; no attendance rule hard-code | I/C; effective/deactivate |
| `cost_centers` | Optional accounting dimension: entity, `code`, `name`, external accounting code?, status | unique entity/code; no payroll journal assumption until Finance confirms | I; effective/deactivate |

## Employee and employment

| Table | Purpose and critical columns | Keys, constraints, and lifecycle | Class / retention |
|---|---|---|---|
| `employees` | Stable employee aggregate: current entity pointer, `public_id`, current employee number cache, full name, encrypted NIK + blind index + last4, birth data, gender, marital status, company/personal email, photo file reference, status | unique entity/current employee number; NIK uniqueness policy pending company validation; current pointer must match active history | R/C; retain through employment/legal requirement; No SD |
| `employee_contacts` | Versioned address/phone/email: entity, employee, `type`, encrypted/canonical value fields, `is_primary`, effective interval | one primary per employee/type/date enforced transactionally; employee/entity match | C; employment + approved retention; No SD |
| `emergency_contacts` | Name, relationship, encrypted phone/address, priority, effective interval | unique employee/priority/from; employee/entity match | C; employment + approved retention; No SD |
| `employee_documents` | Private document metadata: entity, employee, `type`, generated storage path, original name, MIME, size, checksum, issue/expiry dates, classification, status | file private; checksum/index; no path from user input; expiry index; authorized download only | R/C; at least 5 years after exit baseline; controlled purge |
| `employee_bank_accounts` | Effective bank profile: entity, employee, bank code/name, encrypted account number, blind index, last4, encrypted holder name, verification status, interval, approval instance | no overlapping approved primary account; maker/HR/Finance separation | R; payroll/statutory retention where snapshotted; No SD |
| `employee_tax_profiles` | Effective tax identity: entity, employee, encrypted NPWP/tax ID, blind index/last4, PTKP code, tax method, residency/status, interval, approval/source | unique normalized tax ID policy; no overlap; rule meaning resolved by effective rule set | R; 10-year payroll/statutory baseline; No SD |
| `employee_bpjs_profiles` | Effective BPJS numbers/program enrollment/risk category: entity, employee, encrypted identifiers + blind indexes/last4, program status, interval, approval/source | program/employee/from unique; no overlap per program | R; 10-year statutory baseline; No SD |
| `employment_histories` | Source of truth assignment: entity, employee, employee number, branch/division/department/position/location/cost center, manager employee?, employment status, join/termination dates, effective interval, change reason/source | one active assignment per employee; all org/manager refs same allowed entity; transfer closes old and inserts new | C; permanent employment history; No SD |
| `contracts` | Contract/probation history: entity, employee, contract type/number, start/end/probation dates, private document link?, status, approval/source | unique entity/contract number where issued; interval/date checks; renewal creates row | R/C; at least 5 years after exit baseline; No SD |

## Schedule and attendance

| Table | Purpose and critical columns | Keys, constraints, and lifecycle | Class / retention |
|---|---|---|---|
| `work_schedules` | Effective schedule header: entity, `public_id`, code/name, timezone, work pattern type, interval, status, approved version | unique entity/code/from; no overlap per version policy | I; retain while referenced; No SD |
| `work_schedule_days` | Schedule day/sequence: schedule, day/sequence, start/end `TIME`, break minutes, crosses-midnight flag | unique schedule/day-or-sequence; positive duration/break checks; cascade only draft schedule | I; with schedule version |
| `employee_schedule_assignments` | Effective employee schedule: entity, employee, schedule, interval, source/reason | no overlap per employee; entity consistency | C/I; 5-year attendance baseline; No SD |
| `holidays` | Entity calendar: entity, holiday date/name, type, optional branch/location scope, source/status | unique entity/scope/date/code; date index | I; retained with payroll/attendance periods |
| `attendance_sources` | Source registry: entity, code, type (`web`,`gps_selfie`,`fingerprint`,`import`,`offline`), adapter/config reference, status | unique entity/code; secrets outside table/config encrypted | I/R config; retain while events exist |
| `attendance_events` | Raw immutable punch: entity, employee, source, external event ID/idempotency key, event type, `occurred_at_utc`, device offset/timezone, `received_at_utc`, coordinates/accuracy, selfie private file?, device metadata, validation/anomaly status, payload hash | unique source/external ID and idempotency hash; append-only; payload allowlist | C; biometric/restricted when present; 5-year baseline; No SD |
| `attendance_records` | Normalized employee work date: entity, employee, schedule assignment, work date, check-in/out UTC, actual/scheduled minutes, late/early/overtime units, validation/payroll eligibility status, normalization version | unique entity/employee/work date; source event links; recalculation versions/audit | C; 5-year baseline; No SD |
| `attendance_corrections` | Requested correction: entity, record, requester, reason, evidence private file?, old/new redacted structured values, approval instance, status, applied record/version | one idempotent apply; approved correction creates new normalized version, never edits raw event | C; 5-year baseline; No SD |

## Leave and permission

| Table | Purpose and critical columns | Keys, constraints, and lifecycle | Class / retention |
|---|---|---|---|
| `leave_types` | Entity leave code/name, paid/unpaid flag, unit, evidence class, status | unique entity/code; no hard-coded entitlement | I; deactivate |
| `leave_policies` | Effective version: entity, leave type, eligibility, accrual/expiry/carry-forward configuration, rounding, evidence requirement, interval, approval/status | unique entity/type/from; no overlap for approved versions; JSON only for validated rule parameters | C/I; retain while entitlement/payroll references; No SD |
| `leave_entitlements` | Employee grant bucket: entity, employee, type/policy, grant/valid dates, opening quantity, source/status | unique grant reference/idempotency; quantities decimal, non-negative opening | C; 5-year baseline; No SD |
| `leave_ledger_entries` | Append-only credit/debit/expiry/adjustment/reversal: entity, entitlement, employee, leave request?, entry type, quantity, effective date, reference/idempotency key, reversal-of?, reason/actor | unique reference key; reversal entry instead of update/delete; balance is sum ledger | C; 5-year baseline; No SD |
| `leave_requests` | Request interval/partial-day units, entity, employee, type, reason, evidence file?, status, approval instance, submitted/cancelled timestamps | date/unit checks; overlap policy evaluated service-side; unpaid flag snapshotted for payroll | C/R evidence; 5-year baseline; No SD |

## Overtime

| Table | Purpose and critical columns | Keys, constraints, and lifecycle | Class / retention |
|---|---|---|---|
| `overtime_requests` | Entity, employee, requester, planned start/end UTC, type, reason, status, approval instance, actual attendance record?, payroll inclusion status | positive interval; duplicate/overlap validation; no auto approval | C; 5-year baseline; No SD |
| `overtime_calculations` | Immutable calculation version: request, rule/version reference, actual/eligible units, rate inputs, decimal amount?, rounding evidence, validation findings, calculated timestamp/actor | unique request/version; approved version referenced by payroll item | R/C; payroll-linked 10 years, otherwise 5; No SD |

## Compensation, payroll, and statutory

| Table | Purpose and critical columns | Keys, constraints, and lifecycle | Class / retention |
|---|---|---|---|
| `salary_components` | Entity component code/name, type income/deduction/employer, taxable/BPJS flags, calculation/input type, currency, rounding config, effective interval, status | unique entity/code/from; approved version immutable | R/I metadata; retain with payroll; No SD |
| `salary_histories` | Approved compensation version header: entity, employee, currency, effective interval, status, reason, approval instance, maker/checker/final approver, total evidence | no overlap per employee/compensation profile; approved row immutable | R; 10-year payroll baseline; No SD |
| `employee_salary_components` | Lines of salary version: entity, salary history, component, decimal amount/rate/quantity, formula/input reference, effective interval inherited/validated | unique history/component/sequence; same entity; no float | R; 10-year payroll baseline; No SD |
| `payroll_groups` | Entity payroll calendar/group: code/name, frequency, timezone, cutoff/payment rule configuration, currency, status | unique entity/code; dates configurable, not assumptions | R/I; retain while periods exist |
| `payroll_group_memberships` | Effective employee membership: entity, group, employee, interval, reason/source | no overlapping active membership for required payroll scope | R/C; 10-year payroll baseline; No SD |
| `payroll_periods` | Entity/group, period type, attendance cutoff dates, payroll start/end, payment date, status, calendar version | unique group/type/start/end; date ordering checks; close/lock lifecycle | R; 10 years; No SD |
| `payroll_runs` | Entity, period, run type/version, status, maker/checker/final approver, tax/BPJS rule sets, snapshot timestamp, totals, currency, validation summary, locked/published timestamps, reopen parent/reason | unique period/type/version; maker-checker constraints; locked immutable | R; 10 years; No SD |
| `payroll_run_employees` | Employee snapshot: run, entity, employee, employment/salary version refs, identity/org/bank masked snapshot, join/leaver data, gross/deductions/tax/BPJS/net totals, validation status | unique run/employee; all amounts decimal; checksum; immutable after lock | R; 10 years; No SD |
| `payroll_items` | Component result line: run employee, entity, component, category, quantity/rate/base/unrounded/rounded amount, currency, rule/source refs, calculation metadata | unique run employee/component/sequence; decimal/check constraints; immutable after lock | R; 10 years; No SD |
| `payroll_adjustments` | Entity, employee/run employee, source period/run, target run?, component, amount, reason, status, approval, created/posted actor/time | idempotency/reference unique; posted immutable; no direct locked edit | R; 10 years; No SD |
| `tax_rule_sets` | Jurisdiction/entity scope?, code/version, effective interval, official source metadata, verified date/actor, parameters, rounding, status | unique scope/code/version/from; approved immutable; no unverified production use | R/I; 10 years after use; No SD |
| `bpjs_rule_sets` | Entity/global scope, program/version, effective interval, official source, wage limits/rates/program parameters, rounding/status | unique scope/program/version/from; approved immutable | R/I; 10 years after use; No SD |
| `payslips` | Entity, run employee, document public ID, private path/checksum, language, generated/published timestamps, status/version | one active version per run employee/language; regenerate creates version; authorized self/payroll access | R; 10-year payroll baseline; No SD |

## Approval

| Table | Purpose and critical columns | Keys, constraints, and lifecycle | Class / retention |
|---|---|---|---|
| `approval_definitions` | Entity/global scope, key, subject type, version, effective interval, risk class, status, SLA/escalation policy | unique scope/key/version; approved immutable; no overlap active | C/I; retain while instances exist; No SD |
| `approval_steps` | Definition step order, resolver type/config, required permission/role, minimum approvals, SLA/escalation, conditions | unique definition/order; config schema validated; cascade only unused draft definition | C/I; with definition |
| `approval_instances` | Entity, definition/version, subject type/public ID, subject snapshot/checksum, requester, status/current step, submitted/completed timestamps, correlation ID | unique subject/version active rule; registered type/existence/entity validation | C/R by subject; subject retention; No SD |
| `approval_instance_steps` | Resolved immutable step snapshot: instance/order, resolver snapshot, assigned approver user/role/scope, status, due/escalated timestamps | unique instance/order; separation-of-duty checks; reassignment through action | C/R by subject; subject retention; No SD |
| `approval_actions` | Instance/step, actor/acting-for, action, note, attachment private file?, reason, idempotency key, `acted_at_utc`, IP/request metadata redacted | unique instance/idempotency key; append-only; action transition validated | C/R by subject; subject retention; No SD |
| `approval_delegations` | Entity, delegator, delegate, scope/subject types, interval, reason, status, granted/revoked actor | no overlap per delegator/scope; cannot bypass restricted separation of duty | C; audit baseline; No SD |

## Reporting, notification, import, reliability, and audit

| Table | Purpose and critical columns | Keys, constraints, and lifecycle | Class / retention |
|---|---|---|---|
| `notifications` | Entity?, recipient user, template key/version, channel, non-sensitive context, status, scheduled/sent/read timestamps, retry count, sanitized error | idempotency key unique; payload allowlist; delivery outside business transaction | classification of source; controlled operational retention |
| `report_snapshots` | Requester, authorized entity set snapshot, report key/version, parameter hash/redacted parameters, private output path/checksum, status, generated/expiry timestamps | unique request/idempotency where needed; authorized download; no raw secret in parameters | R/C by report; source retention or shorter approved window |
| `import_batches` | Entity/scope, source type/file checksum/private path, template/version, idempotency key, status, row counts, mapping version, uploader, timestamps | unique scope/source checksum/idempotency policy; no direct domain writes | R/C by source; through reconciliation/sign-off + migration retention |
| `import_rows` | Batch, source row number/key/hash, sanitized raw snapshot/private reference, mapped public IDs, status, error codes/messages, imported result type/public ID | unique batch/row; rerun idempotent; errors exclude restricted plaintext | R/C by source; controlled retention after reconciliation |
| `idempotency_keys` | Scope, key hash, request fingerprint, actor/entity, status, result type/public ID, started/completed/expiry timestamps | unique scope/key hash; fingerprint conflict rejects reuse; no request secret/payload | I/C metadata; short operational retention unless business reference |
| `outbox_messages` | Event public ID, entity?, aggregate type/public ID, event type/schema version, redacted payload, occurred/available/processed timestamps, attempts/sanitized error | unique event public ID; append-only until processed retention; consumer dedupe | classification of event; operational/audit policy |
| `audit_logs` | Entity?, event/category, actor/impersonator, subject type/public ID, outcome/reason, redacted before/after/metadata, correlation/request ID, IP/UA hash or controlled fields, `occurred_at_utc` | append-only; tenant/time/subject/actor indexes; no secrets; restricted access/export audit | R/C/I; minimum 7-year baseline; No SD |

## Required master-entity coverage

Seluruh entitas minimal pada master prompt tercakup. Tabel tambahan dipakai
untuk scope (`user_legal_entity_access`), effective schedule/policy, runtime
approval snapshot, payroll group, idempotency, dan transactional outbox—bukan
untuk memperluas scope fitur.

## Fields requiring business confirmation before migration

- Legal entity legal identity, tax identity, address, currency, bank, and logo.
- Employee number uniqueness across transfer/re-hire.
- NIK/NPWP duplicate handling and canonicalization.
- Organization code lists, Division/cost center requirements.
- Contract/employment status code lists.
- Attendance schedule/grace/cutoff and anomaly rules.
- Leave entitlement/evidence/carry-forward policy.
- Payroll component dictionary, calendar, journal, and rounding per component.
- Approved statutory rule sources and effective dates.
- Retention/legal hold and encryption key ownership.
