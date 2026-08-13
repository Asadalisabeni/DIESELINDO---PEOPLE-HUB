# Attendance correction workflow

## Request types and evidence

Employees may request correction for missing check-in/out, wrong location, field
duty, work from home, business travel, late permission, early leave, holiday
attendance, or controlled HR manual adjustment. A request contains a reason,
proposed in/out timestamps, encrypted old and new snapshots, an HMAC snapshot
fingerprint, optional private evidence, requester identity, and lifecycle times.
Only the linked employee can create or cancel their pending-manager request.

## Two-stage approval

The first stage is the employee's direct manager from current effective employment
history. Capability alone is insufficient: the reviewer must be the current
manager employee linked to the account. Rejection ends the workflow. Approval
moves it to final HR review. HR must have `attendance.corrections.review` and an
effective manage scope for the request legal entity. A reviewer from another
company cannot resolve the row and receives a fail-closed response.

Before final approval the service locks and rechecks the current normalized row.
If the row is no longer current or its fingerprint differs, approval stops as a
stale request. An accepted correction marks the previous normalized row non-current
and creates a new version that references `supersedes_id`; linked raw punches are
copied to the new normalized version and remain untouched. Review notes and old/new
values are encrypted, while the audit trail records only correction and record
public IDs.

## Downstream boundary

Approval changes attendance normalization, not payroll. The replacement remains
`pending_review` and no salary deduction, allowance, overtime value, or tax effect
is calculated. Evidence download continues through the private document policy.
Production operations must define delegation, reviewer absence, SLA, reopening,
and retention before this workflow is used for statutory payroll decisions.
