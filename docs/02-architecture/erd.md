# Phase 2.2 — Logical ERD

Diagram ini menunjukkan ownership dan relasi logis. Detail kolom, constraint,
index, retention, dan classification berada di
[data dictionary](data-dictionary.md). Generic approval/audit subjects memakai
type registry + public ID sehingga garis polymorphic tidak dianggap foreign key.

## Organization, identity, dan Core HR

```mermaid
erDiagram
    USERS ||--o| EMPLOYEES : "optional account"
    USERS ||--o{ USER_LEGAL_ENTITY_ACCESS : receives
    LEGAL_ENTITIES ||--o{ USER_LEGAL_ENTITY_ACCESS : scopes
    LEGAL_ENTITIES ||--o{ BRANCHES : owns
    LEGAL_ENTITIES ||--o{ DIVISIONS : owns
    LEGAL_ENTITIES ||--o{ DEPARTMENTS : owns
    LEGAL_ENTITIES ||--o{ POSITIONS : owns
    LEGAL_ENTITIES ||--o{ WORK_LOCATIONS : owns
    LEGAL_ENTITIES ||--o{ COST_CENTERS : owns
    BRANCHES ||--o{ DEPARTMENTS : groups
    DIVISIONS o|--o{ DEPARTMENTS : groups
    DEPARTMENTS ||--o{ POSITIONS : owns
    EMPLOYEES ||--o{ EMPLOYMENT_HISTORIES : has
    LEGAL_ENTITIES ||--o{ EMPLOYMENT_HISTORIES : employs
    BRANCHES ||--o{ EMPLOYMENT_HISTORIES : assigns
    DIVISIONS o|--o{ EMPLOYMENT_HISTORIES : assigns
    DEPARTMENTS ||--o{ EMPLOYMENT_HISTORIES : assigns
    POSITIONS ||--o{ EMPLOYMENT_HISTORIES : assigns
    WORK_LOCATIONS ||--o{ EMPLOYMENT_HISTORIES : locates
    COST_CENTERS o|--o{ EMPLOYMENT_HISTORIES : charges
    EMPLOYEES o|--o{ EMPLOYMENT_HISTORIES : manages
    EMPLOYEES ||--o{ EMPLOYEE_CONTACTS : has
    EMPLOYEES ||--o{ EMERGENCY_CONTACTS : has
    EMPLOYEES ||--o{ EMPLOYEE_DOCUMENTS : owns
    EMPLOYEES ||--o{ EMPLOYEE_BANK_ACCOUNTS : owns
    EMPLOYEES ||--o{ EMPLOYEE_TAX_PROFILES : owns
    EMPLOYEES ||--o{ EMPLOYEE_BPJS_PROFILES : owns
    EMPLOYEES ||--o{ CONTRACTS : has
```

`employees.current_legal_entity_id` adalah pointer scope/current state untuk
query operasional. Source of truth perpindahan tetap `employment_histories`;
transfer menutup interval lama dan membuat interval baru dalam transaction.

## Time, leave, overtime, dan approval

```mermaid
erDiagram
    LEGAL_ENTITIES ||--o{ WORK_SCHEDULES : owns
    WORK_SCHEDULES ||--o{ WORK_SCHEDULE_DAYS : defines
    EMPLOYEES ||--o{ EMPLOYEE_SCHEDULE_ASSIGNMENTS : receives
    WORK_SCHEDULES ||--o{ EMPLOYEE_SCHEDULE_ASSIGNMENTS : assigned
    LEGAL_ENTITIES ||--o{ HOLIDAYS : owns
    LEGAL_ENTITIES ||--o{ ATTENDANCE_SOURCES : owns
    ATTENDANCE_SOURCES ||--o{ ATTENDANCE_EVENTS : emits
    EMPLOYEES ||--o{ ATTENDANCE_EVENTS : produces
    EMPLOYEES ||--o{ ATTENDANCE_RECORDS : has
    ATTENDANCE_RECORDS ||--o{ ATTENDANCE_EVENTS : normalizes
    ATTENDANCE_RECORDS ||--o{ ATTENDANCE_CORRECTIONS : corrected_by
    EMPLOYEES ||--o{ LEAVE_ENTITLEMENTS : receives
    LEAVE_TYPES ||--o{ LEAVE_POLICIES : configured_by
    LEAVE_POLICIES ||--o{ LEAVE_ENTITLEMENTS : grants
    EMPLOYEES ||--o{ LEAVE_REQUESTS : requests
    LEAVE_TYPES ||--o{ LEAVE_REQUESTS : classifies
    LEAVE_ENTITLEMENTS ||--o{ LEAVE_LEDGER_ENTRIES : posts
    LEAVE_REQUESTS o|--o{ LEAVE_LEDGER_ENTRIES : references
    EMPLOYEES ||--o{ OVERTIME_REQUESTS : requests
    OVERTIME_REQUESTS ||--o{ OVERTIME_CALCULATIONS : calculates
    APPROVAL_DEFINITIONS ||--o{ APPROVAL_STEPS : defines
    APPROVAL_DEFINITIONS ||--o{ APPROVAL_INSTANCES : versions
    APPROVAL_INSTANCES ||--o{ APPROVAL_INSTANCE_STEPS : snapshots
    APPROVAL_INSTANCES ||--o{ APPROVAL_ACTIONS : records
    USERS ||--o{ APPROVAL_ACTIONS : acts
    USERS ||--o{ APPROVAL_DELEGATIONS : delegates
```

Leave/attendance/overtime request terhubung ke approval secara polymorphic
melalui registered subject type + public ID. Business request menyimpan
`approval_instance_id` untuk lookup terkontrol setelah instance dibuat.

## Compensation, payroll, statutory, dan operations

```mermaid
erDiagram
    LEGAL_ENTITIES ||--o{ SALARY_COMPONENTS : owns
    EMPLOYEES ||--o{ SALARY_HISTORIES : has
    SALARY_HISTORIES ||--o{ EMPLOYEE_SALARY_COMPONENTS : contains
    SALARY_COMPONENTS ||--o{ EMPLOYEE_SALARY_COMPONENTS : classifies
    LEGAL_ENTITIES ||--o{ PAYROLL_GROUPS : owns
    PAYROLL_GROUPS ||--o{ PAYROLL_GROUP_MEMBERSHIPS : contains
    EMPLOYEES ||--o{ PAYROLL_GROUP_MEMBERSHIPS : joins
    PAYROLL_GROUPS ||--o{ PAYROLL_PERIODS : schedules
    PAYROLL_PERIODS ||--o{ PAYROLL_RUNS : runs
    TAX_RULE_SETS ||--o{ PAYROLL_RUNS : applies
    BPJS_RULE_SETS ||--o{ PAYROLL_RUNS : applies
    PAYROLL_RUNS ||--o{ PAYROLL_RUN_EMPLOYEES : snapshots
    EMPLOYEES ||--o{ PAYROLL_RUN_EMPLOYEES : snapshotted
    PAYROLL_RUN_EMPLOYEES ||--o{ PAYROLL_ITEMS : contains
    SALARY_COMPONENTS ||--o{ PAYROLL_ITEMS : classifies
    PAYROLL_RUN_EMPLOYEES ||--o{ PAYROLL_ADJUSTMENTS : adjusts
    PAYROLL_RUN_EMPLOYEES ||--o| PAYSLIPS : publishes
    IMPORT_BATCHES ||--o{ IMPORT_ROWS : contains
    USERS ||--o{ AUDIT_LOGS : acts
    USERS ||--o{ NOTIFICATIONS : receives
    REPORT_SNAPSHOTS }o--|| USERS : requested_by
    OUTBOX_MESSAGES }o--|| LEGAL_ENTITIES : scoped_to
```

Payroll run employee dan item adalah snapshot. Foreign key ke employee/component
dipertahankan untuk traceability, tetapi nama, organization, bank masked data,
rule version, quantity, rate, dan amount yang dipakai tersimpan pada snapshot
sehingga historical output tidak berubah.

## Cross-cutting polymorphic references

| Table | Reference | Integrity compensation |
|---|---|---|
| `approval_instances` | `subject_type` + `subject_public_id` | allowlisted registry, existence/entity validation, immutable summary snapshot, reconciliation test |
| `audit_logs` | `subject_type` + `subject_public_id` | event schema, no cascade delete, redacted snapshot |
| `outbox_messages` | aggregate type + public ID | event schema version, unique event public ID, consumer idempotency |
| `report_snapshots` | report type + parameter hash | authorized entity set snapshot, checksum, expiry/retention |
| `idempotency_keys` | scope + key hash | request fingerprint, result type/public ID, expiry |
