# Project State

Terakhir diperbarui: 2 Agustus 2026 (Asia/Jakarta)

## Status

- Current phase: Phase 3 — Design system dan UI foundation.
- Phase 0 gate: disetujui Project/UAT Lead pada 28 Juli 2026.
- Phase 1 gate: disetujui Project/UAT Lead pada 2 Agustus 2026; tag
  `phase-1-complete`.
- Phase 2 gate: disetujui Project/UAT Lead pada 2 Agustus 2026.
- Current checkpoint target: `phase-2-complete` setelah PR/CI/merge.
- Git workflow: `feature/phase-2-architecture` → `develop` → `main`.
- Laravel Framework: 13.23.0; PHP: 8.3.30.
- Pest: 4.7.5; Larastan: 3.10.0; PHPStan: 2.2.7 level 8.
- Database lokal: MySQL 8.4.3 `dieselindo_peoplehub`, tiga baseline migration
  batch 1. Phase 2 tidak menambah migration/data.

## Phase 2 baseline

- Layered modular monolith dan ownership 14 module ditetapkan.
- Cross-module contracts, transaction, outbox, idempotency, dan forbidden
  coupling ditetapkan.
- Tujuh ADR berstatus Accepted: modular monolith, entity isolation, temporal
  UTC, identifier/database convention, decimal/rounding, approval snapshot,
  audit/sensitive data.
- Logical ERD dibagi menjadi Organization/Core HR, Time/Approval, dan
  Payroll/Operations.
- Data dictionary mencakup seluruh 47 entitas minimum master prompt dan control
  tables yang diperlukan.
- Tenant authorization memakai permission + LegalEntityScope + Policy + field
  classification + lifecycle/separation-of-duty.
- Database least-privilege identity untuk staging/production sudah dirancang.
- Architecture documentation suite memeriksa kelengkapan, entity coverage,
  ADR/invariant, dan link resolution.

## Architecture invariants

- Tenant-owned row membawa `legal_entity_id`; deny by default.
- Instant UTC `DATETIME(6)`, business date `DATE`, presentation
  `Asia/Jakarta`.
- Effective interval `[effective_from, effective_to)` tanpa overlap.
- Internal bigint + public ULID; public ID bukan authorization.
- Uang/rate/unit memakai decimal dan PHP decimal string; float dilarang.
- Payroll locked, audit, approval action, ledger, dan raw event tidak diedit
  langsung; gunakan version/reversal/adjustment.
- Restricted field/file menggunakan least privilege, masking,
  encryption/blind index yang sesuai, private storage, dan access/export audit.

## Verification evidence

- `composer validate --strict`: PASS.
- `composer check-platform-reqs`: PASS.
- Pint: PASS.
- Larastan level 8: PASS — 0 error.
- Full Pest suite: PASS — 8 tests, 135 assertions.
- Architecture Pest suite: PASS — 4 tests, 118 assertions.
- `npm.cmd run check`: PASS.
- `php artisan migrate:status`: tiga migration `[1] Ran`.
- Secret/artifact/diff hygiene: PASS.

## Open implementation gates

1. Isi nama stakeholder, data/security owner, approver, dan risk owner.
2. Konfigurasi Composer signing public keys pada workstation.
3. Kumpulkan legal entity/organization master dan code lists.
4. Konfirmasi duplicate/canonicalization employee number, NIK, dan NPWP.
5. Validasi SOP serta policy attendance/leave/overtime/payroll.
6. Validasi payroll component dictionary, rounding, statutory source/rules.
7. Setujui retention/privacy/legal hold/key ownership.
8. Provision dan verifikasi actual MySQL least-privilege grants sebelum staging.

## Next authorized step

Setelah checkpoint Phase 2 ada di `main`, Phase 3.1: tetapkan design tokens dan
bangun responsive application shell/component foundation dengan light/dark,
`id`/`en`, accessibility, loading/empty/error/denied states. Jangan masuk ke
authentication atau modul HRIS sebelum Phase 3 exit gate.
