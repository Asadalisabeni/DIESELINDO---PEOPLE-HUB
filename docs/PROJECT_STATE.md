# Project State

Terakhir diperbarui: 2 Agustus 2026 (Asia/Jakarta)

## Status

- Current phase: Phase 5 — Organization dan Core HR, implementation/review
  candidate.
- Phase 0, Phase 1, dan Phase 2 telah disetujui serta dikunci.
- Phase 3 telah disetujui dan dikunci; tag `phase-3-complete` menunjuk main SHA
  `cd1b25bfd39b04c50b9611a90c1a6dc7db8163f5`.
- Phase 4 telah disetujui dan dikunci melalui PR #13 dan PR #14. Tag anotasi
  `phase-4-complete` menunjuk main SHA
  `8410dc38ebac7a8c0083825f6c985a548a0e35e8`.
- Phase 5 branch: `feature/phase-5-organization-core-hr`, dibuat tepat dari tag
  `phase-4-complete`.
- Git author/tagger wajib tetap persis `As'ad Alisabeni
  <sabeni706@gmail.com>`; apostrof tidak boleh hilang.
- Laravel Framework 13, PHP 8.3, MySQL 8, Blade, Tailwind CSS 4, Alpine.js, dan
  Vite tetap menjadi baseline.

## Phase 5 implementation candidate

- Organization: legal entity, branch, optional division, department, position,
  work location, dan cost center.
- Explicit effective-dated `user_legal_entity_access` dengan level
  `view/manage`; Super Admin tidak mempunyai row scope implisit.
- Employee identity, encrypted contact/emergency data, current assignment cache,
  dan effective-dated employment histories.
- Cross-entity transfer menulis history dan membutuhkan manage scope pada entity
  asal serta tujuan.
- Contract history/renewal, private employee documents, dan encrypted
  bank/tax/BPJS profiles berstatus pending verification.
- NIK dan identifier restricted memakai encryption, domain-separated HMAC blind
  index, last-four masking, serta audit allowlist.
- UI bilingual organization, employee directory/create/detail, IAM entity
  scope, dashboard metrics, dark mode, dan responsive application shell.

## Verification evidence saat ini

- Phase 5 migration MySQL: batch 3, `Ran`.
- Role/permission seeder lokal: 10 role, 23 permission, idempotent cache reset.
- Larastan/PHPStan: PASS — 0 error.
- Pint: PASS.
- Phase 5 feature/security suite: PASS — 18 tests, 135 assertions.
- Full Pest suite: PASS — 49 tests, 440 assertions.
- Blade compile dan route registration: PASS.
- Vite production build, Composer strict validation, Composer audit, dan npm
  audit: PASS; dependency audit menemukan 0 vulnerability.
- Public browser flow: PASS untuk render/login bilingual, native required-field
  validation, forgot-password copy, landmark, desktop geometry, dan console.
  In-app browser memblokir asset JavaScript lokal (`ERR_BLOCKED_BY_CLIENT`) dan
  mengabaikan viewport override, sehingga dark-mode/Alpine, mobile, serta
  authenticated browser QA tetap harus diuji di browser normal setelah admin
  tersedia.
- Test feature sekarang fail fast bila Laravel config cache aktif, sebelum trait
  database berjalan.

### Local QA recovery note

Pada verifikasi 2 Agustus 2026, config production yang masih tercache membuat
satu invocation `RefreshDatabase` membangun ulang schema lokal PeopleHub.
Sebelum kejadian, `users`, `legal_entities`, dan `employees` masing-masing 0;
tidak ada account atau data HR yang hilang. Lima authentication event lokal,
empat guest session, serta cache/role seed lokal terhapus. Batch migration telah
dipulihkan tepat ke 1/2/3 dan RolePermissionSeeder mengembalikan 10 role, 23
permission, serta 91 mapping. Guard di `tests/TestCase.php` kini mencegah test
berjalan saat config cache aktif. Database/project lain tidak disentuh.

## Architecture dan security invariants

- Public resource URL memakai ULID; numeric ID internal tidak menjadi URL
  employee/document.
- Capability, legal-entity scope, access level, row policy, dan field
  classification semuanya harus lolos. Penyembunyian menu bukan authorization.
- Scope `view` tidak boleh melakukan mutation walaupun role mempunyai update
  capability. Mutation membutuhkan effective scope `manage`.
- Assignment/contact/profile memakai interval `[effective_from, effective_to)`;
  history lama tidak dihapus ketika nilai baru berlaku.
- Restricted/confidential values tidak masuk log, audit properties, URL,
  fixtures production, atau public storage.
- Employee document hanya tersedia melalui authorized controller dan selalu
  diaudit saat download.
- Payroll, salary rule, PPh 21, dan BPJS calculation belum diimplementasikan;
  profil Phase 5 tidak boleh dianggap payroll-verified.

## Open gates

1. Password administrator lokal yang memenuhi policy minimal 12 karakter;
   credential sebelumnya ditolak policy dan tidak disimpan.
2. HR validation untuk legal entity resmi, organization code list, nomor kontrak,
   employment status, document types, dan initial master data.
3. Named data/security owner, retention/legal hold decision, dan production
   blind-index key ownership.
4. Malware scanner/private object storage decision sebelum staging.
5. Authenticated/dark/mobile browser QA di browser normal dan GitHub Actions
   evidence untuk Phase 5.
6. Explicit stakeholder review dan pernyataan lock sebelum merge/promote/tag.

## Next authorized step

Selesaikan full quality dan browser QA Phase 5, provision administrator setelah
credential compliant tersedia, commit/push ke draft PR, lalu tunggu review
Project/UAT Lead. Jangan mulai Phase 6 ESS sebelum Phase 5 dinyatakan locked dan
tag `phase-5-complete` dibuat pada main yang telah lulus CI.
