# Phase 5 exit review

Status: Ready for stakeholder review; belum locked sampai ada persetujuan
eksplisit Project/UAT Lead.

## Hasil yang dibuat

- Legal entity, branch, optional division, department, position, work location,
  dan cost center dengan ULID, tenant-first index, status lifecycle, serta audit.
- Effective-dated user legal-entity scope dengan level `view/manage`, grant/end,
  overlap prevention, dan tanpa bypass Super Admin.
- Employee identity, contact, emergency contact, effective employment history,
  direct manager, contract renewal, bank/tax/BPJS profile, dan account link.
- NIK, tax, bank, BPJS, contact, emergency, dan address encryption; HMAC blind
  index serta masked display.
- Private employee document upload/download dengan generated path, MIME/size
  validation, checksum, authorization, no-store response, dan audit access.
- Employee directory, search/filter, detail, live Core HR dashboard metrics,
  organization management, dan UI Indonesia/Inggris.
- Negative tests untuk IDOR/cross-company, cross-entity hierarchy, read-only
  scope mutation, self-manager, overlap scope, document download, sensitive
  exposure, serta encryption at rest.

## Migration dan data

Migration tunggal Phase 5 membuat seluruh foreign key dalam urutan dependency,
menambahkan `users.employee_id`, dan memiliki reverse-order `down()` untuk test
environment kosong. Pada upgrade lokal, migration berhasil sebagai batch 3.
Tidak ada legal entity atau dummy employee yang di-seed; master nyata harus
diinput dan divalidasi perusahaan.

## Verification sebelum lock

- `composer quality` hijau.
- Full Pest suite hijau, termasuk Phase 5 security suite.
- Fresh SQLite migration dan upgrade MySQL nyata hijau.
- `npm run build`, Composer validation, Composer audit, dan npm audit hijau.
- Browser QA mencakup login, dashboard, organization CRUD/deactivation,
  employee create/detail, bilingual, dark mode, responsive mobile, validation,
  private document authorization, serta console tanpa error.
- GitHub Actions hijau pada draft pull request Phase 5.
- HR memvalidasi code list legal entity/branch/division/department/position,
  employment status, document type, dan initial contract policy.

## Risiko dan batasan

- Maker-checker bank/tax/BPJS dilanjutkan pada generic approval/payroll phase;
  Phase 5 menyimpan version awal berstatus pending dan tidak menganggapnya valid
  untuk payroll.
- Malware scanning/object storage belum tersedia pada local baseline.
- Reminder contract/document belum dijadwalkan sampai notification phase.
- Full employee import, export, report, dan data reconciliation berada di phase
  berikutnya sesuai roadmap.
- Administrator lokal hanya boleh dibuat setelah password yang memenuhi policy
  locked Phase 4 tersedia; policy tidak boleh diturunkan atau dilewati.
- Public browser flow telah diperiksa. In-app browser memblokir JavaScript asset
  lokal dan tidak menerapkan viewport override; authenticated, Alpine/dark-mode,
  dan mobile QA harus diselesaikan di browser normal setelah administrator
  compliant tersedia. Ini tetap exit gate, bukan dianggap PASS implisit.
- Feature test menolak berjalan ketika config cache aktif agar `RefreshDatabase`
  tidak mungkin memakai koneksi lokal akibat environment testing tertimpa.

## Git checkpoint

- Branch: `feature/phase-5-organization-core-hr`
- Commit: `feat: implement phase 5 organization and core hr`
- Setelah review: merge ke `develop`, tunggu CI, promote ke `main`, tunggu CI,
  lalu buat annotated tag `phase-5-complete` dengan identitas Git yang disetujui.
