# Phase 2 — Exit Review

Tanggal persetujuan: 2 Agustus 2026

Approver: Project/UAT Lead

## Keputusan

Phase 2 — Architecture dan database design disetujui selesai sebagai logical
baseline. Phase 2 tidak membuat migration domain atau mengklaim aturan bisnis
yang belum dikonfirmasi sebagai kebijakan production. Phase 3 — Design system
dan UI foundation diizinkan dimulai setelah PR, CI, merge, dan tag checkpoint
Phase 2 berhasil.

## Milestone coverage

| Milestone | Evidence | Status |
|---|---|---|
| 2.1 Architecture | [Architecture overview](architecture-overview.md), seven accepted ADRs | PASS |
| 2.1 Module ownership | [Module boundaries](module-boundaries.md), dependency direction, contracts, forbidden coupling | PASS |
| 2.2 Database rules | [Database design](database-design.md), identifiers, temporal, decimal, FK/delete/index/idempotency rules | PASS |
| 2.2 Logical model | [ERD](erd.md) split into Organization/Core HR, Time/Approval, Payroll/Operations | PASS |
| 2.2 Data dictionary | [Data dictionary](data-dictionary.md), all 47 required master entities plus control tables | PASS |
| 2.3 Multi-company/security | [Security model](multi-company-security-model.md), deny-by-default scope, matrix, classification, encryption, audit | PASS |
| 2.3 Database least privilege | Migrator/runtime/worker/report/backup/monitor identities and rights designed | PASS |
| 2.4 Automated consistency | Architecture document suite, required entities/ADR/invariants/link resolution | PASS |

## Accepted architecture baseline

- One Laravel modular monolith and one MySQL database.
- Layer direction Delivery → Application → Domain; Infrastructure implements
  inward-owned ports.
- Tenant-owned rows carry explicit `legal_entity_id`; authorization combines
  permission, entity scope, row Policy, field class, and lifecycle/SoD.
- Public ULID plus internal bigint; ULID does not replace authorization.
- Instant UTC `DATETIME(6)`, business `DATE`, presentation `Asia/Jakarta`.
- Effective intervals use `[effective_from, effective_to)` and transactional
  overlap prevention.
- Money/rate/unit use decimal types and decimal strings; float is forbidden.
- Approval definition and resolved steps are snapshotted; sensitive workflows
  never auto-approve.
- Audit/ledger/action/raw event/locked payroll records are append-only or
  corrected by reversal/version.
- Restricted fields/files use explicit controls, redaction, encryption/blind
  indexes where applicable, private storage, and view/export audit.

## Migration and data

Tidak ada migration atau perubahan data pada Phase 2. Tiga migration Laravel
baseline tetap batch 1. Tabel domain dibuat bertahap pada phase pemilik vertical
slice, menggunakan data dictionary sebagai contract dan ADR baru bila terjadi
perubahan invariant.

## Automated verification

Jalankan dari PowerShell:

```powershell
Set-Location -LiteralPath 'C:\laragon\www\DIESELINDO PEOPLEHUB'

composer validate --strict
composer check-platform-reqs
composer run quality
php artisan test --testsuite=Architecture
npm.cmd run check
php artisan migrate:status
```

Expected result:

- Pint dan Larastan level 8 lulus tanpa baseline/suppression.
- Seluruh Pest test lulus; Architecture suite memvalidasi dokumen, entitas,
  ADR, invariant, dan local links.
- Frontend build tetap lulus.
- Tiga baseline migration tetap `[1] Ran` dan tidak ada domain migration baru.
- Secret/artifact/diff hygiene lulus.

Hasil lokal aktual sebelum commit:

- Full Pest suite: PASS — 8 tests, 135 assertions.
- Architecture suite: PASS — 4 tests, 118 assertions.
- Pint: PASS.
- Larastan level 8: PASS — 0 error.

## Open implementation gates

Hal berikut tidak menghalangi logical architecture exit, tetapi menghalangi
phase implementasi terkait bila belum diselesaikan:

- Nama data owner, security owner, approver, dan risk owner.
- Legal entity master dan organization code lists.
- Employee-number/NIK/NPWP duplicate/canonicalization policy.
- SOP attendance/leave/overtime/payroll dan component dictionary.
- Statutory official sources/rules/validation.
- Retention, health/biometric privacy, legal hold, deletion, dan key ownership.
- Actual MySQL least-privilege grants sebelum staging.
- Fingerprint/BCA/object-storage protocol/provider evidence.

Tidak satu pun baseline assumption tersebut boleh di-hard-code sebagai final
production policy.

## Git checkpoint

- Feature branch: `feature/phase-2-architecture`.
- PR target: `develop`, kemudian promotion `develop` ke `main`.
- Recommended commit: `docs: complete phase 2 architecture baseline`.
- Checkpoint tag setelah merge dan CI: `phase-2-complete`.

## Next authorized step

Phase 2.1 telah selesai sebagai milestone internal Phase 2. Setelah checkpoint
Phase 2 dikunci, next roadmap phase adalah Phase 3.1: design tokens, responsive
application shell, component/accessibility states, dark mode, dan i18n
foundation—tanpa authentication/business module implementation.
