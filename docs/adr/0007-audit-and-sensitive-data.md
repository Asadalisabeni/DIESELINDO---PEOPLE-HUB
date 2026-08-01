# ADR-0007 — Audit, Sensitive Data, and Private Files

- Status: Accepted
- Date: 2 Agustus 2026

## Decision

Audit record bersifat append-only dan menyimpan actor/impersonator, legal
entity, event, subject type/public ID, correlation/request ID, outcome, reason,
dan redacted before/after metadata. View restricted data dan export dicatat
sebagai action, bukan hanya mutation.

Restricted identifier yang perlu equality lookup menyimpan encrypted value,
masked suffix, dan keyed blind index dari canonical value. Password/2FA/recovery
code memakai mekanisme one-way/encryption khusus framework dan tidak pernah
masuk audit diff. Salary/payroll amounts memerlukan calculation/reporting;
perlindungannya menggunakan field permission, tenant isolation, encryption at
rest, restricted backup, and access/export audit—not opaque field encryption
yang menghalangi kalkulasi.

File disimpan pada private disk dengan generated path. Download hanya melalui
authorized controller atau short-lived signed URL setelah Policy check.

## Consequences

- Encryption key rotation dan blind-index key version harus direncanakan
  sebelum production.
- Audit payload memakai allowlist/redaction; serialisasi model penuh dilarang.
- Audit/payroll locked tidak memakai soft delete dan tidak dihapus oleh user
  application.
- Retention tetap configurable dan memerlukan legal/privacy approval; legal
  hold mengalahkan scheduled disposal.

## Alternatives rejected

- Plaintext restricted identifiers: meningkatkan breach impact.
- Encrypt semua kolom termasuk payroll amount: mematahkan calculation/report.
- Public storage dengan URL sulit ditebak: URL bukan authorization.
- Audit dari application log text: tidak cukup structured, immutable, atau
  searchable untuk control evidence.
