# Phase 7 attendance operations runbook

## Configuration order

Seed the current role matrix, grant HR an explicit effective manage scope, then
configure sources and approved schedule layers. Create the legal-entity default
first, followed by branch or department overrides and employee assignments only
where needed. Record policy references in schedule names/reasons. Enter the
approved holiday calendar and verify timezone, effective dates, weekdays, start,
end, break, and grace settings with HR before enabling employee punch sources.

For the X100C PoC, create a fingerprint source using `x100c_csv_v1`. Verify the
canonical header and upload only an approved sanitized export. Compare row,
imported, duplicate, and rejected totals to the source control total. A batch with
rejections needs investigation; generic row codes intentionally do not expose
employee data. Never describe this adapter as real-time.

## Daily monitoring

Review anomalous events, incomplete current records, delayed offline sync, weak
GPS accuracy, missing required selfie, correction queues, and import reconciliation
counts. Do not edit `attendance_events` directly. Correct an accepted business
exception through the two-stage correction workflow, producing a new normalized
version. Do not manually flip `payroll_eligibility` or calculate deductions from
late totals during Phase 7.

## Recovery and security

Retrying the same source external ID is safe and must return the existing event.
If source identifiers are unstable, disable ingestion and preserve the source
export for controlled reconciliation. Rotate or disable a compromised source;
no credentials belong in `validation_rules`. Restrict private selfies/evidence,
review document access logs, and follow the incident process for unauthorized
location or image exposure.

Before deployment run Pint, Larastan, the full Pest suite, real MySQL migration,
role seeding, Vite build, dependency audits, and browser checks. Back up the
PeopleHub database before upgrade. The MES project and port 8877 are outside this
runbook and must not be accessed or modified.
