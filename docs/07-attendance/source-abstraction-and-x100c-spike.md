# Attendance source abstraction and Solution X100C spike

## Source contract

`AttendanceSourceAdapter` converts a source payload into one canonical event
shape: external event ID, event type, occurrence/device times, optional GPS,
selfie document reference, field context, device information, and offline marker.
The ingestion service applies entity isolation, idempotency, anomaly rules,
encryption, and normalization after adaptation. Supported source types are
`fingerprint`, `mobile_gps`, `web`, `offline_mobile`, `manual_adjustment`, and
`import`. Source validation thresholds live in JSON configuration and connection
secrets are deliberately excluded.

## X100C technical spike result

The repository contains only a canonical CSV PoC adapter for the currently named
Solution X100C device. It does not claim real-time, ADMS, SDK, network protocol,
vendor API, direct database, or device push compatibility. Those paths remain
unproven until the exact model/firmware, vendor documentation, network topology,
available export format, stable employee identifier, timezone behavior, and an
approved sanitized sample are examined with the device owner.

The accepted PoC header is exactly
`employee_number,event_type,occurred_at,external_event_id`. Rows are resolved
inside the source legal entity, passed through the same idempotent ingestion
pipeline, and counted as imported, duplicate, or rejected. Error summaries retain
only row number and a generic code; they do not echo employee numbers or payloads.
The file checksum prevents accidental repeat processing.

## Production gate

Before selecting direct integration, run a controlled lab capture and document
clock/timezone, duplicate behavior, reconnect behavior, deletion/re-enrollment,
identifier mapping, authentication, encryption, rate limits, and reconciliation.
If direct integration cannot be proven secure and supportable, CSV/Excel batch
import remains the explicit fallback with maker/checker operations and archived
source files outside public storage.
