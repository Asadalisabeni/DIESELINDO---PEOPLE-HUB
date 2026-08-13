# Phase 8 exit review

Status: implementation/review candidate. Phase 8 belum locked sampai Project/UAT Lead
meninjau hasil, GitHub CI lulus, dan memberikan instruksi lock eksplisit.

## Delivered candidate

- Configurable leave type dan effective-dated policy per legal entity.
- Entitlement bucket dengan grant idempotency dan append-only decimal ledger.
- Full-day request berdasarkan schedule/holiday, eligibility, notice, maximum days,
  overlap, evidence threshold, dan balance validation.
- Generic sequential approval definition/instance/step/action engine; active delegation,
  upper-manager/HR fallback, revision/reject/cancel, dan unpaid Payroll confirmation.
- Private encrypted request/evidence/review data, least-privilege scope, notification
  database+queued mail, expiry lifecycle, audit, bilingual ESS/admin/review, dan CSV.

## Verification evidence saat ini

- PASS: Pint dan Larastan/PHPStan level 8, tanpa suppression dan 0 error.
- PASS: full Pest 83 tests / 704 assertions; Phase 8 memberi 11 tests / 96
  assertions untuk policy, ledger, calendar, approval, delegation, scope,
  encryption, expiry, queued after-commit notification, UI, export, dan rollback.
- PASS: real MySQL additive migration batch 6; sebelas tabel Leave/Approval
  tersedia tanpa reset, fresh, rollback, atau penghapusan data.
- PASS: role seeder idempoten, 14 route Leave, Blade compilation, scheduler
  lifecycle 00:30 Asia/Jakarta, dan Approval boundary scan.
- PASS: Vite final build (58 module), Composer audit tanpa advisory, dan npm audit
  dengan 0 vulnerability.
- PASS: browser smoke QA guest protection, ID/EN, dark mode, mobile 375x812 tanpa
  horizontal overflow, dan console tanpa warning/error.
- PENDING: Git diff/secret audit, commit/push/draft PR, dan GitHub Actions.

## Open production gates

HR/Legal harus mengesahkan seluruh jenis dan angka policy, kebutuhan surat dokter,
carry forward, expiry, cuti khusus, evidence retention, cancellation after approval,
backdated request, delegation owner, reminder/escalation SLA, serta downstream unpaid
payroll contract. Data bisnis dan employee-account linking diperlukan untuk UAT nyata.
Phase 8 tidak menghitung salary deduction, tax, BPJS, overtime, atau payroll amount.
Lock kelak hanya menetapkan baseline source; readiness production tetap tunduk pada
UAT, security/privacy, migration reconciliation, backup/restore, dan go-live criteria.
