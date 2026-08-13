# Project State

Terakhir diperbarui: 13 Agustus 2026 (Asia/Jakarta)

## Status

- Current phase: Phase 9 — Overtime, implementation/review candidate.
- Phase 0 sampai Phase 8 telah disetujui dan dikunci.
- Phase 8 dipromosikan melalui feature PR #25 ke `develop`, lalu lock PR #26
  ke `main`. Main merge commit adalah
  `50221de42c2a6a094d00efc530fff3ba9d884f47`.
- Main CI run `31676811593` lulus untuk PHP quality/MySQL migration dan
  frontend build/audit. Annotated tag `phase-8-complete` menunjuk tepat ke
  main commit tersebut dan tagger terverifikasi persis `As'ad Alisabeni
  <sabeni706@gmail.com>`; apostrof tidak hilang.
- Phase 9 branch: `feature/phase-9-overtime`, dibuat tepat dari tag
  `phase-8-complete`.
- Laravel 13, PHP 8.3, MySQL 8, Blade, Tailwind CSS 4, Alpine.js, Vite, UTC
  storage, tampilan Asia/Jakarta, IDR, serta UI Indonesia/Inggris tetap baseline.

## Phase 9 implementation candidate

- Overtime rule legal-entity scoped, effective-dated, dan non-overlap per day
  type. Minimum, rounding, maximum, segment multiplier, meal, transport, dan
  eligibility sepenuhnya configurable; tidak ada koefisien hukum hardcoded.
- Employee dapat mengajukan sendiri dan Supervisor hanya untuk direct report.
  Request wajib dibuat sebelum pekerjaan dimulai, dalam satu tanggal lokal,
  tanpa overlap, untuk employee aktif dan employment yang eligible.
- Working day, rest day, dan national holiday diturunkan dari effective schedule
  dan holiday. Regular dan emergency adalah klasifikasi request; night shift
  bukan tipe perhitungan terpisah.
- Generic approval engine menjalankan Manager lalu scoped HR lalu scoped Payroll.
  Requester tidak dapat approve request sendiri; delegation bersifat subject
  specific, sementara, scoped, dan tidak memberikan permission baru.
- Manager hanya dapat mempertahankan atau mengurangi planned minutes dan harus
  approve sebelum start. HR memvalidasi actual dari current normalized attendance
  yang lengkap dan non-anomalous. Payroll hanya mengonfirmasi period eligibility.
- Perhitungan memakai integer minutes, explicit rounding, cap, minimum, weighted
  segment hundredths, dan configurable meal/transport. Tidak ada wage amount,
  payroll amount, PPh 21, BPJS, atau kompensasi cuti pada Phase 9.
- Calculation, rule snapshot, checksum, dan trace immutable. Reason, work
  description, dan validation note terenkripsi; audit/CSV tidak mengeksposnya.
- Notification database+mail queued memakai `afterCommit`; UI ESS, review,
  administration/read-only, delegation, rule, dan audited scoped CSV tersedia
  dalam Bahasa Indonesia dan Inggris.

## Verification evidence saat ini

- Phase 8 lock: PASS — PR #25/#26 merged, tag main terverifikasi, dan main CI
  run `31676811593` hijau.
- Composer quality: PASS — Pint bersih, Larastan/PHPStan level 8 tanpa
  suppression dan 0 error, full Pest 93 tests / 792 assertions.
- Phase 9 feature/security suite: PASS — 10 tests / 88 assertions untuk schema,
  least privilege, configurable rule, overlap, active employee, encryption,
  before-work submission, Manager/HR/Payroll workflow, immutable calculation,
  Attendance reconciliation, holiday, self-approval denial, delegation,
  cross-entity denial, bilingual/read-only UI, audited export, dan dokumentasi.
- Phase 8 regression setelah refactor Approval: PASS — 11 tests / 96 assertions.
- Real MySQL upgrade: PASS — migration Phase 9 batch 7; tiga tabel Overtime
  tersedia. Tidak ada reset, fresh, rollback, atau penghapusan data.
- Role/permission seed dijalankan ulang secara idempoten.
- Route/Blade: PASS — 10 overtime routes dan seluruh Blade cached.
- Generic Approval boundary scan: PASS — tidak ada import model konkret Leave
  atau Overtime pada Approval engine.
- Frontend build: PASS — Vite production build menyelesaikan 58 module.
- Dependency audit: PASS — Composer tidak menemukan security advisory dan npm
  melaporkan 0 vulnerability.
- Browser smoke QA: PASS — route ESS/review/admin menolak guest ke login,
  pergantian ID/EN dan dark mode bekerja, viewport 375x812 tanpa horizontal
  overflow, serta browser console tanpa warning/error.
- Git/GitHub checkpoint Phase 9: menunggu commit, push, draft PR ke `develop`,
  dan GitHub Actions dari candidate yang sama.
- Database bisnis lokal masih 0 legal entity dan 0 employee; authenticated UAT
  memakai test suite isolated, bukan menyisipkan dummy data ke database bisnis.
- Project MES dan port 8877 tidak disentuh.

## Architecture dan security invariants

- Self-scope, assigned-current-approver, permission, dan effective legal-entity
  scope adalah lapisan terpisah. Super Admin tidak memiliki implicit row bypass.
- Approval subject memakai allowlisted scalar contract: type, public ID, entity,
  requester, employee snapshot, checksum, dan correlation ID.
- Requester tidak dapat approve request sendiri. Delegation selalu sementara,
  subject specific, scoped, auditable, dan tidak memberi permission baru.
- Overtime calculation dan approval action tidak dapat update/delete melalui
  application model. Koreksi produksi harus memakai rancangan superseding baru.
- Confidential request detail tidak muncul di notification, audit property,
  rule/calculation trace, atau CSV.
- Phase 9 hanya menghasilkan downstream payroll eligibility dan unit lembur;
  salary rate dan nilai uang payroll tetap di luar scope.

## Open production gates

1. Sign-off HR/Legal atas seluruh konfigurasi rule serta verifikasi koefisien
   berbasis regulasi dari sumber resmi yang berlaku pada effective date.
2. Sign-off approval owner, delegation, SLA, fallback, planned-versus-actual,
   rounding, cap, meal/transport, dan kontrak input Payroll.
3. Real legal entity/employee/hierarchy/schedule/holiday/attendance data serta UAT
   Employee, Supervisor, Manager, HR, Payroll, dan Auditor pada desktop/mobile/
   keyboard/email.
4. Rancangan correction/superseding calculation, rekonsiliasi legacy overtime,
   retention/legal hold, queue/worker monitoring, backup/restore rehearsal,
   accessibility, dan rollback criteria.
5. Review eksplisit Project/UAT Lead pada draft PR Phase 9 sebelum merge,
   promotion, atau pembuatan tag `phase-9-complete`.

## Next authorized step

Commit dan push kandidat Phase 9, buka draft PR ke `develop`, tunggu seluruh
GitHub Actions, lalu serahkan kepada Project/UAT Lead untuk review. Jangan merge,
promote, atau membuat tag Phase 9 sebelum instruksi lock eksplisit.
