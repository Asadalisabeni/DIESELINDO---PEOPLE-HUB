# Audit and sensitive-data controls

## Two append-only streams

`activity_log` records administrative changes such as account provisioning, role changes, account activation/deactivation, and the initial admin bootstrap. `authentication_events` records login success/failure, limiter and temporary-lock events, logout, password changes/resets, email verification, TOTP lifecycle events, and session revocation.

Both project models reject update and delete operations. Production database credentials must add the database-level control below so application bugs or a compromised application process cannot bypass the Eloquent guard:

```sql
REVOKE UPDATE, DELETE ON peoplehub.activity_log FROM 'peoplehub_app'@'%';
REVOKE UPDATE, DELETE ON peoplehub.authentication_events FROM 'peoplehub_app'@'%';
```

Adapt the schema and principal names to the deployment. A separate break-glass retention principal may archive partitions under an approved retention procedure. Never grant the normal application principal those two privileges.

## Recorded and excluded data

Recorded authentication metadata is deliberately narrow: event type, nullable internal user ID, HMAC email lookup, encrypted IP, encrypted truncated user-agent, encrypted allow-listed context, and timestamps. Passwords, reset tokens, TOTP secrets, recovery codes, session payloads, request bodies, cookies, raw emails, salary values, and document content are excluded.

Activity-log properties contain role names and active/inactive state only. They do not contain target email addresses, passwords, or reset links. The audit UI displays actor/subject identity when authorized, but not encrypted network metadata.

## Access and retention

Only `audit.view` can open the audit screen. Authorization failures do not reveal the missing permission name. Default configuration retains records for ten years, while deletion remains disabled for the application model. Before production, Legal/HR/Security must approve the final retention period and the archival principal/process; that governance decision changes operations, not the append-only application contract.

All timestamps are stored in UTC by Laravel and rendered using `Asia/Jakarta`. Security investigations should correlate the event ID and UTC database timestamp, not copied UI text.
