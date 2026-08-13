# Project State

Terakhir diperbarui: 13 Agustus 2026 (Asia/Jakarta)

## Status

- Current phase: Phase 7 — Attendance, implementation/review candidate.
- Phase 0 sampai Phase 6 telah disetujui dan dikunci.
- Phase 6 dipromosikan melalui PR #19 ke `main`; annotated tag
  `phase-6-complete` menunjuk tepat ke main SHA
  `f163652be8b35279309cf4f444ebf08844bfa03c`. Push checks run
  `30766693871` lulus untuk PHP/MySQL dan frontend build/audit.
- Phase 7 branch: `feature/phase-7-attendance`, dibuat tepat dari tag
  `phase-6-complete`.
- Git author/tagger wajib tetap persis `As'ad Alisabeni
  <sabeni706@gmail.com>`; apostrof tidak boleh hilang.
- Laravel Framework 13, PHP 8.3, MySQL 8, Blade, Tailwind CSS 4, Alpine.js, dan
  Vite tetap menjadi baseline.

## Phase 7 implementation candidate

- Effective schedule mendukung prioritas employee, department, branch, lalu
  legal entity; timezone, jam kerja, break, grace, hari kerja, dan hari libur
  merupakan konfigurasi, bukan production policy hardcoded.
- Attendance source abstraction mencakup fingerprint, mobile GPS, web, offline,
  manual adjustment, dan import dengan validation thresholds tanpa secret.
- Raw event append-only menyimpan server/device timestamps, unique external ID,
  idempotency hash, encrypted GPS/field/device metadata, anomaly, dan payload
  integrity hash.
- Daily normalized attendance mempertahankan actual punch, scheduled context,
  late/early/worked minutes, numbered versions, supersedes link, dan explicit
  payroll readiness. Tidak ada potongan payroll otomatis.
- Offline browser queue menyimpan payload minimum, tidak menyimpan selfie/note/
  customer text, menghapus item setelah sync, dan menggunakan retry idempoten.
- Correction membutuhkan direct-manager approval lalu scoped HR approval,
  anti-stale fingerprint, encrypted old/new/review data, private evidence, dan
  membuat normalized version baru tanpa mengubah raw event.
- Solution X100C tersedia sebagai canonical CSV reconciliation PoC. Project tidak
  mengklaim integrasi real-time, ADMS, SDK, protocol, atau direct DB sebelum
  technical spike dengan perangkat nyata terbukti.
- Employee, review, dan admin attendance UI tersedia dalam Indonesia/Inggris dan
  memakai permission plus explicit legal-entity scope.

## Verification evidence saat ini

- Phase 6 lock: PASS — PR #19 merged, main CI hijau, annotated tag
  `phase-6-complete` terverifikasi.
- Composer quality: PASS — Pint bersih, Larastan/PHPStan level 8 tanpa
  suppression dan 0 error, serta full Pest 72 tests / 608 assertions.
- Phase 7 feature/security suite: PASS — 11 tests / 78 assertions, termasuk
  encrypted raw GPS/field data, configurable grace, idempotency, immutability,
  anomaly block, correction versioning, cross-entity denial, dan X100C PoC.
- Real MySQL upgrade: PASS — migration Phase 7 batch 5; seluruh 10 tabel
  attendance terdeteksi. Tidak ada reset, fresh, rollback, atau penghapusan data.
- Role/permission seed: PASS — 10 roles, 38 permissions, 199 mappings.
- Route registration dan Blade compilation: PASS — 14 attendance routes dan
  semua template berhasil dicache.
- Vite production build: PASS — 58 modules transformed.
- Composer strict validation: PASS. Composer audit: PASS, 0 advisory setelah
  `league/commonmark` diperbarui dari 2.8.3 ke 2.10.0. npm audit: PASS, 0
  vulnerability setelah transitive `nanoid` diperbarui ke 3.3.18.
- Public browser QA: PASS — login ID/EN, theme interaction dan accessibility
  label, viewport 375x812 tanpa horizontal overflow, guest redirect dari
  attendance/admin, asset load, dan console tanpa warning/error. QA server
  sementara pada port 8086 telah dihentikan.
- Authenticated employee/manager/HR attendance browser UAT: PENDING karena
  database bisnis lokal masih memiliki 0 legal entity dan 0 employee; account
  administrator tidak diberi implicit entity atau employee scope.
- Secret scan dan diff whitespace check: PASS; password administrator tidak
  terdapat dalam source, docs, test, atau lockfile.
- Project MES dan port 8877 tidak disentuh.

## Architecture dan security invariants

- Public URL memakai ULID; numeric internal ID tidak menjadi employee,
  attendance, correction, document, atau import URL.
- Self-scope dan administrative entity scope tetap menjadi dua jalur
  authorization terpisah. Super Admin tidak memiliki implicit row-scope bypass.
- Capability, effective manage scope, linked employee/manager identity,
  encryption, private storage, idempotency, anomaly review, dan audit allowlist
  diterapkan berlapis.
- Raw attendance event tidak boleh di-update atau dihapus melalui application
  model. Koreksi membuat normalized version baru.
- Device time tidak dipercaya sendirian. Server receipt time selalu dicatat;
  delayed offline, clock mismatch, GPS lemah/hilang, atau selfie wajib yang
  hilang membuat anomaly dan payroll block.
- Attendance, late total, dan correction Phase 7 tidak menghitung salary
  deduction, overtime pay, tax, BPJS, atau final payroll eligibility.

## Open gates

1. Approved HR schedule, grace/rounding, holiday, correction delegation/SLA, dan
   downstream payroll eligibility policy.
2. Exact Solution X100C model/firmware, vendor documentation, protocol/SDK/export
   evidence, timezone/identifier behavior, dan sanitized device sample.
3. GPS/geofence owner and policy; selfie consent/legal basis, retention/deletion,
   malware scanning, private object storage, and incident response.
4. Real HR master data, explicit employee-account linking, serta authenticated
   employee/direct-manager/HR UAT pada desktop/mobile/keyboard dan offline flow.
5. GitHub Actions pada draft PR serta review eksplisit Project/UAT Lead sebelum
   merge atau lock Phase 7.

## Next authorized step

Quality gate lokal candidate telah hijau. Commit dengan identitas Git yang
disetujui, push branch, buka draft PR ke `develop`, dan tunggu GitHub Actions.
Jangan merge, promote, atau membuat tag `phase-7-complete` sebelum pernyataan
lock eksplisit dari Project/UAT Lead.
