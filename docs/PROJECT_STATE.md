# Project State

Terakhir diperbarui: 14 Agustus 2026 (Asia/Jakarta)

## Status

- Current phase: Phase 10 — Payroll Foundation, implementation/review candidate.
- Phase 0 sampai Phase 9 telah disetujui dan dikunci.
- Phase 9 dipromosikan melalui feature PR #27 ke `develop`, lalu lock PR #28 ke `main`.
  Main merge commit adalah `0966a96ba33e81607938ff530ba64ea5e18a6b74`.
- Main CI run `31683755528` lulus untuk PHP quality/MySQL migration dan frontend
  build/audit. Annotated tag `phase-9-complete` menunjuk tepat ke main commit tersebut;
  tagger terverifikasi persis `As'ad Alisabeni <sabeni706@gmail.com>` dan apostrof tidak hilang.
- Phase 10 branch: `feature/phase-10-payroll-foundation`, dibuat tepat dari tag
  `phase-9-complete`, dan dipublikasikan sebagai draft PR #29 ke `develop`.
- Laravel 13, PHP 8.3, MySQL 8, Blade, Tailwind CSS 4, Alpine.js, Vite, UTC storage,
  tampilan Asia/Jakarta, IDR, serta UI Indonesia/Inggris tetap baseline.

## Phase 10 implementation candidate

- Sepuluh tabel additive menyediakan salary component, salary history dan line,
  payroll group dan membership, period, versioned run, employee snapshot, item,
  serta validation finding. Seluruh nilai uang memakai `DECIMAL(19,4)`.
- Salary component dan payroll membership legal-entity scoped, effective-dated, dan
  menolak overlap. Employee salary memakai draft maker, checker independen, reason
  terenkripsi, checksum versi, serta approved history dan line yang immutable.
- Payroll group menyimpan monthly frequency, timezone, currency, proration basis,
  cutoff, payment day, dan adjustment yang configurable. Period menyimpan tanggal
  eksplisit serta snapshot konfigurasi kalender.
- Gross-to-net memakai fixed four-decimal string dan integer arithmetic tanpa float.
  Fixed-monthly/daily-rate, joiner/leaver, perubahan salary tengah periode, basis
  calendar days atau working days, unpaid leave, serta allowance meal/transport
  dari Overtime Phase 9 menjadi item dengan trace dan pembulatan eksplisit.
- Setiap run employee menyimpan snapshot employment/organization, encrypted bank
  evidence, salary version/checksum, service and basis days, detail item, total,
  finding, dan snapshot checksum. Snapshot, item, finding, dan validated run tidak
  dapat update/delete melalui application model.
- Missing/unverified/duplicate bank, blocked attendance, missing salary/employment,
  no payable day, negative net, dan empty group menjadi blocking error. Source harus
  diperbaiki melalui owner workflow lalu dibuat immutable run version berikutnya.
- Phase 10 sengaja mempertahankan PPh 21, BPJS, dan overtime wage pada nilai nol
  dengan warning eksplisit. Formula statutory tidak diklaim sebelum verified,
  effective-dated Phase 11 rules dari sumber resmi tersedia.
- Role matrix menerapkan least privilege: Payroll prepare/validate tanpa Finance
  review; Finance menyetujui salary dan review tanpa prepare; Company HR tidak
  prepare; Auditor read-only. Super Admin tetap tanpa implicit row-scope bypass.
- UI payroll administration/read-only dan audited CSV tersedia dalam Bahasa
  Indonesia/Inggris. Salary-only viewer tidak dapat memaksa run evidence melalui URL;
  export tidak memuat full bank account, confidential salary reason, atau finding detail.

## Verification evidence saat ini

- Phase 9 lock: PASS — PR #27/#28 merged, tag main terverifikasi, dan main CI run
  `31683755528` hijau.
- Composer quality: PASS — Pint bersih, Larastan/PHPStan level 8 tanpa suppression
  dan 0 error, full Pest 104 tests / 910 assertions.
- Phase 10 feature/security suite: PASS — 11 tests / 113 assertions untuk schema,
  least privilege, decimal math, overlap, maker-checker, encryption, checksum,
  immutability, gross-to-net, joiner, salary change, working-day schedule, unpaid
  leave, overtime allowance, Phase 11 warning, bank/attendance blockers, versioned
  rerun, cross-entity denial, bilingual/read-only UI, audited export, dan dokumentasi.
- Real MySQL upgrade: PASS — migration Phase 10 batch 8; sepuluh tabel payroll tersedia
  dan RolePermissionSeeder dijalankan idempoten. QA awal menemukan identifier index
  otomatis melebihi batas MySQL; nama index diperpendek eksplisit dan tiga tabel
  Phase 10 orphan yang terverifikasi kosong dibersihkan sebelum rerun. Tidak ada tabel
  atau data Phase 0–9 yang dihapus.
- Route/Blade: PASS — 11 payroll routes dan seluruh Blade cached.
- Frontend build: PASS — Vite production build menyelesaikan 58 module.
- Dependency audit: PASS — Composer tidak menemukan security advisory dan npm
  melaporkan 0 vulnerability.
- Browser smoke QA: PASS — route payroll menolak guest ke login, locale Inggris,
  dark mode, viewport 375x812 tanpa horizontal overflow, dan console tanpa warning/error.
  Authenticated payroll evidence juga diverifikasi melalui isolated feature test tanpa
  memakai atau menyimpan password administrator.
- Database bisnis lokal tetap 0 legal entity dan 0 employee; authenticated QA memakai
  test suite isolated, bukan menyisipkan dummy data ke database bisnis.
- Project MES dan port 8877 tidak disentuh. Server QA PeopleHub pada port 8086 sudah
  dihentikan dan port dilepas.

## Architecture dan security invariants

- Permission dan effective legal-entity scope adalah lapisan terpisah; semua mutation
  memerlukan capability dan effective `manage` scope.
- Approved salary version memakai logical segmentation oleh effective start versi
  berikutnya tanpa mengubah checksum evidence versi sebelumnya.
- Payroll run dikoreksi melalui versi baru, bukan edit snapshot lama. `validated`
  adalah batas Phase 10 dan bukan authorization untuk payment.
- Bank snapshot dan finding details terenkripsi; salary reason tidak masuk notification,
  audit property, UI evidence, atau CSV. Audit menggunakan public ID dan metadata aman.
- PPh 21/BPJS/overtime wage tidak hardcoded atau dihitung secara semu. Review/approve/
  lock/reopen, bank file, accounting journal, payslip, dan statutory reporting tetap gate fase lanjut.

## Open production gates

1. Sign-off HR/Finance atas component ownership, salary migration, membership,
   proration, cutoff, payment date, rounding, unpaid leave, dan overtime input.
2. Phase 11 rule set PPh 21, BPJS, dan overtime wage yang diverifikasi terhadap sumber
   resmi terbaru, effective-dated, diuji, dan disetujui HR/Legal/Finance.
3. Independent payroll review, final approval, lock dan controlled reopen, bank file,
   accounting posting, payslip, reporting, retention/legal hold, serta audit monitoring.
4. Real UAT dengan entity/employee/salary/bank/attendance/leave/overtime data, seluruh
   role terkait, desktop/mobile/keyboard, variance reconciliation, dan rollback criteria.
5. Review eksplisit Project/UAT Lead pada draft PR Phase 10 sebelum merge, promotion,
   atau pembuatan tag `phase-10-complete`.

## Next authorized step

Tunggu GitHub Actions draft PR #29 hijau, lalu Project/UAT Lead meninjau hasil dan
gate di atas.
Jangan merge, promote, atau membuat tag Phase 10 sebelum instruksi lock eksplisit.
