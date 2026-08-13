# Project State

Terakhir diperbarui: 13 Agustus 2026 (Asia/Jakarta)

## Status

- Current phase: Phase 8 — Leave dan izin, implementation/review candidate.
- Phase 0 sampai Phase 7 telah disetujui dan dikunci.
- Phase 7 dipromosikan melalui feature PR #23 ke `develop`, lalu lock PR #24 ke
  `main`. Annotated tag `phase-7-complete` menunjuk tepat ke main SHA
  `390a6b74f9a2f7901f4867e7cbdb12805d8dff0f`.
- Main CI run `31668248715` lulus untuk PHP quality/MySQL migration dan frontend
  build/audit. Tagger terverifikasi persis `As'ad Alisabeni
  <sabeni706@gmail.com>`; apostrof tidak hilang.
- Phase 8 branch: `feature/phase-8-leave`, dibuat tepat dari tag
  `phase-7-complete`.
- Laravel 13, PHP 8.3, MySQL 8, Blade, Tailwind CSS 4, Alpine.js, Vite, UTC
  storage, tampilan Asia/Jakarta, IDR, serta UI Indonesia/Inggris tetap baseline.

## Phase 8 implementation candidate

- Leave type configurable per legal entity menyimpan kategori, paid/unpaid,
  balance behavior, unit full-day, evidence threshold, dan payroll confirmation.
- Leave policy versioned dan effective-dated menyimpan eligibility, entitlement,
  validity, carry forward, notice, maximum days, reminder, dan escalation. Nilai
  12 hari/12 bulan hanyalah input configurable, bukan policy production hardcoded.
- Entitlement grant idempoten memakai unique reference. Saldo berasal dari SUM
  ledger append-only opening/entitlement/adjustment/usage/cancellation/expiry/
  carry-forward/reversal; tidak ada mutable balance column atau float arithmetic.
- Request menghitung hari kerja dari effective schedule dan holiday, lalu
  memvalidasi employment, eligibility, notice, maximum days, overlap, evidence,
  dan saldo sebelum write.
- Generic approval engine memakai definition, step, instance, resolved step
  snapshot, action, dan delegation. Approval engine tidak mengimpor model Leave.
- Paid leave: Employee → direct/delegated/upper manager → scoped HR. Unpaid leave
  menambah scoped Payroll confirmation. Tidak ada auto-approval.
- Final approval mem-lock saldo dan memposting usage secara atomik. Unpaid leave
  tidak memposting saldo atau menghitung potongan payroll.
- Request reason, approval notes/snapshots, delegation/adjustment reasons terenkripsi;
  evidence private; ledger/action immutable; export legal-entity scoped dan diaudit.
- Notification database+mail queued memakai `afterCommit`; lifecycle expiry dan
  reminder dijadwalkan 00:30 Asia/Jakarta dengan overlap protection.
- UI ESS, admin/read-only, review queue, ledger, delegation, report CSV tersedia
  dalam Bahasa Indonesia dan Inggris.

## Verification evidence saat ini

- Phase 7 lock: PASS — PR #23/#24 merged, tag main terverifikasi, CI run
  `31668248715` hijau.
- Composer quality: PASS — Pint bersih, Larastan/PHPStan level 8 tanpa
  suppression dan 0 error, full Pest 83 tests / 704 assertions.
- Phase 8 feature/security suite: PASS — 11 tests / 96 assertions untuk effective
  policy, immutable ledger, holiday calendar, 2/3-stage approval, delegation,
  encryption, validation rollback, cross-entity denial, expiry, queued
  after-commit notification, bilingual UI, read-only scope, dan audited export.
- Real MySQL upgrade: PASS — migration Phase 8 batch 6; sebelas tabel Leave dan
  Approval tersedia. Tidak ada reset, fresh, rollback, atau penghapusan data.
- Role/permission seed dijalankan ulang secara idempoten.
- Route/Blade/scheduler: PASS — 14 leave routes, seluruh Blade cached, command
  lifecycle dijadwalkan 00:30 Asia/Jakarta tanpa overlap.
- Generic Approval boundary scan: PASS — tidak ada concrete Leave model import.
- Frontend build: PASS — Vite production build menyelesaikan 58 module.
- Dependency audit: PASS — Composer tidak menemukan security advisory dan npm
  melaporkan 0 vulnerability.
- Browser smoke QA: PASS — route ESS/review/admin menolak guest ke login,
  pergantian ID/EN dan dark mode bekerja, viewport 375x812 tanpa horizontal
  overflow, serta browser console tanpa warning/error.
- Git/GitHub checkpoint: PASS — diff dan secret audit bersih, implementation
  commit `e826d1e11102f29a80ab9ec715c8a4ee65238866` ter-push ke
  `feature/phase-8-leave`, draft PR #25 menargetkan `develop`, dan CI run
  `31671997610` lulus untuk frontend serta PHP quality/MySQL migration.
- Database bisnis lokal masih 0 legal entity dan 0 employee; authenticated UAT
  memakai test suite isolated, bukan menyisipkan dummy data ke database bisnis.
- Project MES dan port 8877 tidak disentuh.

## Architecture dan security invariants

- Self-scope, assigned-current-approver, permission, dan effective legal-entity
  scope adalah lapisan terpisah. Super Admin tidak memiliki implicit row bypass.
- Approval subject memakai allowlisted scalar contract: type, public ID, entity,
  requester, employee snapshot, checksum, dan correlation ID.
- Requester tidak dapat approve request sendiri. Delegation selalu sementara,
  scoped, auditable, serta tidak memberi permission baru kepada delegate.
- Ledger dan approval action tidak dapat update/delete melalui application model.
  Koreksi menggunakan entry/action baru dengan idempotency key.
- Evidence dan catatan medis tidak muncul di notification, audit property, atau CSV.
- Phase 8 tidak menghitung payroll amount, salary deduction, PPh 21, BPJS, atau
  overtime. Output unpaid hanya downstream classification untuk phase payroll.

## Open production gates

1. Sign-off HR/Legal untuk leave type, eligibility, entitlement, expiry, carry
   forward, special leave, medical certificate threshold, notice, backdate,
   cancellation-after-approval, dan retention evidence.
2. Approval owner, delegation rules, reminder/escalation SLA, upper-manager/HR
   fallback, dan unpaid Payroll handoff contract.
3. Real legal entity/employee/manager/account/work schedule/holiday data dan UAT
   Employee, Manager, HR, Payroll, Auditor pada desktop/mobile/keyboard/email.
4. Malware scanning, private object storage, queued email sandbox, worker/scheduler
   monitoring, expiry rehearsal, and balance reconciliation with legacy data.
5. Review eksplisit Project/UAT Lead pada draft PR #25 sebelum merge, promotion,
   atau tag `phase-8-complete`; GitHub Actions kandidat awal telah lulus.

## Next authorized step

Project/UAT Lead meninjau draft PR #25 beserta UAT dan gate production di atas.
Jangan merge, promotion, atau membuat tag Phase 8 sebelum instruksi lock eksplisit.
