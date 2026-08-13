# Schedules and attendance normalization

## Effective schedule hierarchy

Schedules belong to one legal entity and may be configured as an entity default,
a branch override, or a department override. An effective-dated employee schedule
assignment is the highest-priority override. Resolution order is employee,
department, branch, then legal entity. Every schedule stores its timezone,
effective range, working-day rows, start/end time, break minutes, late grace, and
early-leave grace. The service rejects end times that are not after start times
because night shift is outside the approved scope. Holidays can be national or
company-wide, or scoped to a branch.

No production schedule is embedded in application code. The often-used Monday to
Friday 08:00–17:00, 60-minute break, and 15-minute grace are acceptable test or
development data only. HR must enter approved schedules and grace policies as
effective-dated configuration. Changes create new schedule/assignment data rather
than silently rewriting historic policy context.

## Normalization

The event timestamp is converted to the legal-entity timezone to determine the
work date. All employee raw events within that local day are ordered. The first
check-in and last check-out are retained as actual punches. Scheduled timestamps
come from the resolved schedule. Late minutes begin only after configured late
grace; early-leave minutes use the configured early grace. Monthly totals sum the
current normalized version and are explicitly informational.

Each new event produces a new normalization version for the day and marks the
previous normalized version non-current. A holiday punch is labelled separately.
Missing pairs are incomplete. Any contributing anomalous event makes the record
anomalous. Present records remain `pending_review`; incomplete, holiday-policy,
or anomalous records are `blocked`. Phase 7 does not calculate deductions,
overtime pay, or final payroll eligibility.
