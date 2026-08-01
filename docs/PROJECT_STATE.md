# Project State

Terakhir diperbarui: 2 Agustus 2026 (Asia/Jakarta)

## Status

- Current phase: Phase 2 — Architecture dan database design.
- Phase 0 gate: disetujui Project/UAT Lead pada 28 Juli 2026.
- Phase 1 gate: disetujui Project/UAT Lead pada 2 Agustus 2026.
- Current checkpoint: Phase 1 complete; tag `phase-1-complete`.
- Laravel Framework: 13.23.0.
- PHP/runtime resolution: 8.3.30.
- Pest: 4.7.5; Pest Laravel plugin: 4.1.0.
- Larastan: 3.10.0; PHPStan: 2.2.7; analysis level: 8.
- Git: branch baseline `main`; canonical remote
  `https://github.com/Asadalisabeni/DIESELINDO---PEOPLE-HUB.git`.
- Database: `dieselindo_peoplehub` pada MySQL 8.4.3, port 3306,
  `utf8mb4_unicode_ci`.
- Tiga baseline migration Laravel berstatus `Ran` pada batch 1.

## Hasil Phase 1

- Scaffold Laravel resmi digabung tanpa menimpa dokumen Phase 0.
- Dependency backend/frontend dikunci melalui `composer.lock` dan
  `package-lock.json`.
- Identitas aplikasi, timezone `Asia/Jakarta`, dan locale `id`/fallback `en`
  telah dikonfigurasi.
- Pest dan baseline configuration/layout tests tersedia.
- MySQL migration telah diuji apply, rollback tiga langkah, dan apply ulang.
- Larastan level 8 menganalisis `app`, `bootstrap`, `config`, `database`, dan
  `routes` tanpa baseline atau ignored error.
- Composer quality scripts menjalankan Pint, Larastan, dan Pest secara serial.
- GitHub Actions memvalidasi backend pada PHP 8.3/MySQL 8.4 dan frontend pada
  Node.js 24.
- Dependabot dijadwalkan mingguan untuk Composer, npm, dan GitHub Actions.
- Base Blade layout menyediakan title, locale, Vite assets, skip link, dan
  semantic landmarks `header`, `main`, serta `footer`.

## Verification evidence

- `composer validate --strict`: PASS.
- `composer check-platform-reqs`: PASS.
- `composer run quality`: PASS.
- `composer run analyse`: PASS — 0 error.
- `php artisan test`: PASS — 4 tests, 17 assertions.
- Laravel Pint: PASS.
- `npm.cmd ci --ignore-scripts --dry-run`: PASS.
- `npm.cmd run check`: PASS.
- `php artisan migrate:status`: 3 migration `[1] Ran`.
- Composer audit: 0 security advisories.
- npm audit: 0 vulnerabilities.
- GitHub Actions workflow: static validation PASS dengan actionlint 1.7.12.
- Workflow dan Dependabot YAML: syntax/structure PASS.
- Secret pattern scan: PASS.
- Browser desktop 1280×720 dan mobile 375×812: PASS; tidak ada horizontal
  overflow atau console error.

## Security baseline

- `.env`, `vendor`, `node_modules`, dan `public/build` tidak di-track.
- Local debug aktif hanya pada environment local.
- Session payload encryption aktif.
- Dependency resolution menggunakan minimum stability `stable`.
- Composer dipatok ke platform PHP 8.3.30 untuk mencegah dependency yang hanya
  kompatibel dengan PHP lebih tinggi masuk tanpa sengaja.
- CI permissions hanya `contents: read` dan memakai test-only MySQL credential.
- Frontend CI memakai `npm ci --ignore-scripts`.
- Akun MySQL `root` tanpa password hanya untuk Laragon lokal dan dilarang pada
  staging/production.

## Open gates

1. Isi nama stakeholder dan owner risiko secara formal.
2. Konfigurasi Composer signing public keys pada mesin pengguna.
3. Rancang database user least-privilege untuk staging dan production.
4. Verifikasi eksekusi GitHub Actions aktual pada commit checkpoint setelah
   initial push.

## Next authorized step

Phase 2.1: dokumentasikan module boundaries dan architecture decision records
untuk modular monolith, legal-entity isolation, authorization boundary,
effective dating, timestamp, audit, approval, dan data sensitif. Jangan membuat
seluruh tabel HRIS sebelum model tersebut direview.
