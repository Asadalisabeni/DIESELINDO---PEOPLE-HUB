# Phase 7 attendance scope and security

## Boundary

Phase 7 owns effective work schedules, holiday calendars, attendance source
configuration, immutable raw punches, normalized daily records, correction
approval, field-presence metadata, offline synchronization, and X100C batch
reconciliation. It does not calculate salary deductions, approve overtime, or
make payroll-ready decisions. `payroll_eligibility` remains `pending_review` or
`blocked`; no attendance value is converted into money in this phase.

## Authorization

An employee reaches attendance only through the explicit `users.employee_id`
link and can see or submit only their own rows. Administrative reads and writes
need both an attendance capability and an effective `manage` assignment in
`user_legal_entity_access`. Super Admin has every capability but has no implicit
row-scope bypass. Direct-manager review additionally verifies the current
effective employment history, while final HR review verifies managed entity
scope again. Cross-company identifiers are resolved inside the allowed query so
they fail closed as not found.

## Restricted data

Latitude, longitude, activity, destination, notes, device information, correction
reason, old/new values, and review notes use encrypted casts. Selfies and evidence
reuse private employee-document storage; filenames are not used as storage keys.
Audit properties contain public IDs, status, source type, counts, and anomaly
codes only. Raw coordinates, customer names, free text, device strings, file
paths, payload hashes, and snapshot fingerprints are excluded from logs and UI
tables. Retention, consent, malware scanning, legal hold, and production object
storage require named policy owners before production rollout.

## Integrity rules

Raw `attendance_events` are append-only at the model layer and have unique source
external IDs plus an idempotency hash. A correction never edits a raw event. Each
accepted correction supersedes the current normalized row with a new numbered
version. Device time is evidence, not authority: server receipt time is always
stored and delayed or inconsistent events are flagged. An anomalous or incomplete
record is blocked from downstream payroll use.
