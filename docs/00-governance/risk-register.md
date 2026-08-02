# Initial Project Risk Register

Skala probability (P) dan impact (I): 1 rendah sampai 5 sangat tinggi.
Score = P × I. Score 15–25 adalah prioritas kritis, 8–14 tinggi, 4–7 sedang,
dan 1–3 rendah.

| ID | Risiko | P | I | Score | Owner awal | Mitigasi dan gate |
|---|---|---:|---:|---:|---|---|
| R-001 | Formula PPh 21/BPJS tidak sesuai aturan efektif terbaru. | 4 | 5 | 20 | Payroll + Tax adviser | Sumber resmi, rule set bertanggal efektif, golden test case, validasi tertulis sebelum parallel run. |
| R-002 | Data lintas legal entity bocor karena query tidak terscope. | 3 | 5 | 15 | Engineering + Security | Policy, legal-entity scope, negative authorization test, IDOR test, review export. |
| R-003 | Payroll salah karena data sumber atau formula tidak matang. | 4 | 5 | 20 | Payroll + Finance | Snapshot, validation findings, variance report, maker-checker, dua parallel run, tolerance sign-off. |
| R-004 | Data legacy tidak lengkap, duplikat, atau sulit dipetakan. | 5 | 4 | 20 | HR + Data migration lead | Source inventory, profiling, mapping, idempotent trial import, reconciliation dan sign-off. |
| R-005 | Integrasi Solution X100C tidak mendukung real-time/format stabil. | 4 | 3 | 12 | IT + Attendance owner | Technical spike, vendor evidence, adapter abstraction, CSV/Excel fallback. |
| R-006 | GPS/selfie/offline attendance dimanipulasi atau tersinkron ganda. | 3 | 4 | 12 | Security + HR | Idempotency key, device/server timestamps, accuracy/anomaly checks, review queue, audit. |
| R-007 | Scope rilis pertama terlalu besar sehingga kualitas turun. | 5 | 4 | 20 | Sponsor + Product owner | Milestone gate, vertical slice, change control, no out-of-scope modules. |
| R-008 | Approval berhenti karena struktur atasan/delegasi tidak lengkap. | 4 | 4 | 16 | HR | Data-quality rules, delegation, fallback hierarchy, SLA/escalation, no sensitive auto-approval. |
| R-009 | Perubahan employee master mengubah payroll historis. | 3 | 5 | 15 | Engineering + Payroll | Effective dates, immutable snapshot, lock, adjustment/version instead of edit. |
| R-010 | Akses dokumen, rekening, salary, tax, atau payslip tidak sah. | 3 | 5 | 15 | Security + Data owner | Private storage, authorization controller/signed URL, masking/encryption, access/export audit. |
| R-011 | Backup tersedia tetapi tidak dapat dipulihkan. | 3 | 5 | IT Infrastructure | Encrypted offsite backup, quarterly restore drill, evidence dan recovery runbook; go-live gate. |
| R-012 | Email/queue/scheduler gagal dan proses tertunda diam-diam. | 3 | 3 | 9 | IT + Engineering | Async jobs, retries, dead-letter handling, failure alert, health monitoring. |
| R-013 | Dependency Laravel 13 belum kompatibel atau tidak terawat. | 3 | 4 | 12 | Engineering | Compatibility matrix, minimal dependencies, lock file, security audit, PoC before adoption. |
| R-014 | Infrastruktur/domain/SSL/SSH terlambat tersedia. | 4 | 4 | 16 | Sponsor + IT | Procurement milestone, owner/date, staging readiness gate, alternative provider plan. |
| R-015 | UAT tidak mewakili skenario payroll/security kritis. | 3 | 5 | 15 | UAT Lead | Traceability requirement-to-test, role-based testers, anonymized realistic data, defect gates. |
| R-016 | Kebijakan perusahaan belum tertulis sehingga konfigurasi dianggap final secara keliru. | 5 | 4 | 20 | HR + Management | Assumption log, effective-dated approval, configuration status (`draft/approved/retired`). |
| R-017 | Secrets atau data production masuk repository/staging. | 3 | 5 | 15 | Engineering + IT | `.env` ignored, secret scanning, masked staging data, least-privilege deployment identity. |
| R-018 | Performa report/payroll turun saat histori lima tahun dimigrasi. | 3 | 3 | 9 | Engineering + DBA | Volume baseline, indexes, query review, chunked jobs, performance test with representative data. |
| R-019 | Effective-dated row overlap atau boundary tanggal salah sehingga assignment/rule ganda aktif. | 3 | 5 | 15 | Engineering + HR/Payroll | Half-open interval, date checks, unique natural key/from, transactional overlap lock, boundary/concurrency tests. |
| R-020 | Generic approval subject menjadi orphan atau salah entity karena tidak memiliki FK langsung ke seluruh tabel bisnis. | 2 | 4 | 8 | Engineering + Process owner | Allowlisted subject registry, existence/entity validation, immutable summary snapshot, reconciliation dan integration tests. |
| R-021 | Encryption/blind-index key salah kelola sehingga data bocor atau tidak dapat dicari/dipulihkan. | 3 | 5 | 15 | Security + IT | External versioned keys, keyed blind index, rotation/restore plan, environment separation, audit dan break-glass control. |
| R-022 | Queue, export, report, atau CLI melewati legal-entity scope meski UI/HTTP sudah aman. | 3 | 5 | 15 | Engineering + Security | Explicit `LegalEntityScope`, service identity capability, scope snapshot/recheck, no serialized actor/model, negative tests semua entry point. |
| R-023 | Tim menganggap menu disabled/hidden sebagai authorization sehingga endpoint Phase 4 tidak terlindungi. | 3 | 5 | 15 | Engineering + Security | Dokumentasikan UI bukan security control; wajib Gate/Policy/permission dan negative test pada server. |
| R-024 | Translation key, focus behavior, atau responsive layout regresi saat modul baru menambah komponen. | 3 | 3 | 9 | Engineering + QA | Shared Blade component, locale-key parity test, accessibility contract test, visual QA desktop/mobile setiap milestone. |
| R-025 | Baseline visual dianggap brand final padahal logo/brand guide resmi belum disetujui. | 2 | 3 | 6 | Product owner + Management | Tandai configurable, gunakan semantic tokens/code-native mark, lakukan visual sign-off dan asset replacement review. |

## Review cadence

- Review mingguan selama delivery dan setiap milestone gate.
- Review segera setelah scope, regulasi, dependency, atau arsitektur berubah.
- Owner, due date, residual score, status, dan evidence akan ditambahkan setelah
  stakeholder ditetapkan.
- Risiko kritis yang belum memiliki treatment yang disetujui menghalangi
  milestone terkait.

## Phase 2 treatment review

- R-002/R-010: architecture treatment ditetapkan dalam ADR-0002, ADR-0007, dan
  multi-company security model; implementation/security-test evidence tetap
  gate Phase 4/5/10/12.
- R-008: generic approval snapshot/fallback/separation-of-duty ditetapkan dalam
  ADR-0006; workflow test tetap gate module terkait.
- R-009: effective history dan payroll snapshot/immutability ditetapkan dalam
  ADR-0003/ADR-0005; calculation evidence tetap gate Phase 10.
- R-017: repository hygiene tetap aktif; key/secret provisioning menjadi
  operational gate.
- R-019–R-022: treatment desain tersedia, tetapi residual score baru boleh
  diturunkan setelah implementation, concurrency/negative tests, key rotation,
  dan owner sign-off.

## Phase 3 treatment review

- R-023: seluruh future navigation nonaktif dan dokumentasi/test menegaskan
  bahwa UI bukan authorization; residual tetap sampai Phase 4 negative tests.
- R-024: shared component, `id`/`en` key parity, accessibility assertions, dan
  desktop/mobile browser QA sudah menjadi baseline.
- R-025: configurable brand token dan code-native mark menjaga perubahan brand
  tetap terisolasi; visual sign-off masih open gate.
