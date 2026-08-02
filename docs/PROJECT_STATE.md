# Project State

Terakhir diperbarui: 3 Agustus 2026 (Asia/Jakarta)

## Status

- Current phase: Phase 6 — Employee Self-Service, implementation/review candidate.
- Phase 0 sampai Phase 4 telah disetujui dan dikunci.
- Phase 5 telah disetujui dan dikunci melalui PR #15 dan PR #16. Tag anotasi
  `phase-5-complete` menunjuk main SHA
  `43c8162658e2d036b6af1d5970cbc14fdc7007a7`.
- Phase 6 branch: `feature/phase-6-employee-self-service`, dibuat tepat dari tag
  `phase-5-complete`.
- Git author/tagger wajib tetap persis `As'ad Alisabeni
  <sabeni706@gmail.com>`; apostrof tidak boleh hilang.
- Laravel Framework 13, PHP 8.3, MySQL 8, Blade, Tailwind CSS 4, Alpine.js, dan
  Vite tetap menjadi baseline.

## Phase 6 implementation candidate

- Employee portal memakai explicit `users.employee_id` self-scope dan tidak
  memberikan administrative legal-entity scope kepada employee.
- Profile menampilkan identity/current assignment serta bank/tax/BPJS dalam
  last-four masking.
- Phone/address/emergency dapat diperbarui langsung dengan encrypted,
  effective-dated history dan audit allowlist.
- Nama resmi, status perkawinan, bank, tax/PTKP, BPJS, keluarga, identity
  document, dan employment correction memakai encrypted change request.
- Request memakai anti-stale HMAC fingerprint, single-pending type guard, row
  lock, cancellation, approve/reject, reviewer notes, dan timestamp lifecycle.
- Review HR membutuhkan `ess.profile-change.review` dan manage scope efektif
  pada entity request; cross-company detail gagal aman sebagai 404.
- Evidence berada pada private employee document storage. Notification database
  memakai translation key dan public request ID tanpa sensitive payload.
- Employment correction yang disetujui ditandai manual follow-up dan tidak
  melewati effective-dated Core HR assignment workflow.

## Verification evidence saat ini

- Phase 6 route registration dan Blade compilation: PASS.
- Larastan/PHPStan: PASS — 0 error tanpa suppression.
- Pint: PASS.
- Phase 6 feature/security suite: PASS — 12 tests, 90 assertions.
- Full Pest suite: PASS — 61 tests, 530 assertions.
- Real MySQL upgrade: PASS — migration Phase 6 tercatat pada batch 4.
- Role/permission seed: PASS — 10 roles, 29 permissions, 144 mappings.
- Vite production build: PASS — 57 modules transformed.
- Composer strict validation dan audit: PASS — 0 vulnerability.
- npm audit: PASS — 0 vulnerability.
- Browser publik: PASS — login, forgot-password, ID/EN switch, dark-mode
  interaction, CSS/JavaScript assets, anonymous `/ess` redirect, dan console
  tanpa warning/error telah diverifikasi pada `127.0.0.1:8085`. Server QA
  sementara telah dihentikan; port `8877` dan project MES tidak disentuh.
- Authenticated ESS/reviewer browser QA dan GitHub Actions draft PR masih menjadi
  exit gate.

### Local QA recovery note Phase 5

Pada verifikasi 2 Agustus 2026, config production yang masih tercache membuat
satu invocation `RefreshDatabase` membangun ulang schema lokal PeopleHub.
Sebelum kejadian, `users`, `legal_entities`, dan `employees` masing-masing 0;
tidak ada account atau data HR yang hilang. Lima authentication event lokal,
empat guest session, serta cache/role seed lokal terhapus. Batch migration telah
dipulihkan tepat ke 1/2/3 dan RolePermissionSeeder dipulihkan. Guard di
`tests/TestCase.php` mencegah test berjalan saat config cache aktif. Database dan
project lain tidak disentuh.

## Architecture dan security invariants

- Public URL memakai ULID; numeric internal ID tidak menjadi employee/request/
  document URL.
- Self-scope dan administrative entity scope adalah dua jalur authorization yang
  terpisah. Tidak ada pencocokan account otomatis berdasarkan email.
- Capability, manage scope, row policy, field classification, encrypted storage,
  masking, dan audit harus tetap diterapkan berlapis.
- Restricted values tidak masuk audit, notification, URL, log, atau public
  storage. Notification ownership selalu difilter melalui user relation.
- Contact/profile memakai interval `[effective_from, effective_to)`; histori
  lama tidak dihapus saat nilai baru berlaku.
- Payroll, salary rule, PPh 21, BPJS calculation, attendance, dan leave belum
  diimplementasikan dan tidak boleh dianggap tersedia.

## Open gates

1. Password administrator lokal yang memenuhi policy minimal 12 karakter dengan
   huruf besar, huruf kecil, angka, dan simbol. Nilai terakhir ditolak karena
   tidak memenuhi seluruh policy dan tidak disimpan.
2. Real HR master data, explicit `users.employee_id` linking, serta authenticated
   employee/reviewer QA untuk contact update, request, review, notification,
   attachment, mobile, dan keyboard pada browser normal.
3. HR validation untuk request types, required evidence, bank duplicate policy,
   family relationship code, dan employment manual follow-up SOP.
4. Malware scanner/private object storage, retention/legal hold, dan named data
   owner sebelum staging.
5. GitHub Actions hijau, explicit stakeholder review, dan pernyataan lock sebelum
   merge/promote/tag Phase 6.

## Next authorized step

Commit/push implementation candidate ke draft PR dan verifikasi GitHub Actions.
Provision administrator hanya setelah credential compliant tersedia, lalu
selesaikan authenticated browser/UAT dengan HR master data yang valid. Jangan
merge atau membuat tag `phase-6-complete` sebelum lock eksplisit Project/UAT
Lead.
