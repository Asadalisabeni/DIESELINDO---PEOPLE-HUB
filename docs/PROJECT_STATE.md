# Project State

Terakhir diperbarui: 28 Juli 2026 (Asia/Jakarta)

## Status

- Current phase: Phase 1 — Project setup.
- Phase 0 gate: disetujui Project/UAT Lead pada 28 Juli 2026.
- Current checkpoint: Phase 1.2 MySQL preflight selesai; baseline migration
  menunggu eksekusi.
- Laravel Framework: 13.23.0.
- PHP: 8.3.30.
- Pest: 4.7.5.
- Pest Laravel plugin: 4.1.0.
- Git: repository lokal pada branch `main`, commit awal belum dibuat.
- Database aplikasi: `dieselindo_peoplehub` sudah dibuat pada MySQL 8.4.3
  port 3306 dengan charset `utf8mb4` dan collation `utf8mb4_unicode_ci`.
- Migration aplikasi belum dijalankan; tabel `migrations` belum tersedia.

## Hasil Phase 1.1

- Scaffold Laravel resmi digabung tanpa menimpa dokumen Phase 0.
- Package PHP dan frontend dikunci melalui `composer.lock` dan
  `package-lock.json`.
- Identitas aplikasi ditetapkan menjadi Dieselindo PeopleHub.
- Timezone aplikasi `Asia/Jakarta`; locale `id`; fallback `en`.
- `.env.example` menggunakan MySQL database `dieselindo_peoplehub`.
- `.env` lokal memiliki application key dan di-ignore Git.
- Pest 4 dan plugin Laravel resmi terpasang.
- Baseline configuration test ditambahkan.
- Repository Git lokal diinisialisasi.

## Verification evidence

- `composer validate --strict`: PASS.
- `composer check-platform-reqs`: PASS.
- `php artisan test`: PASS, 3 tests dan 7 assertions.
- `pint --test`: PASS.
- `npm.cmd run build`: PASS.
- `composer audit --locked`: 0 security advisories.
- `npm.cmd audit --audit-level=high`: 0 vulnerabilities.
- Koneksi MySQL `127.0.0.1:3306`: PASS.
- Database `dieselindo_peoplehub`: PASS.
- Charset/collation: `utf8mb4` / `utf8mb4_unicode_ci`.
- `php artisan migrate:status`: koneksi PASS dan menghasilkan
  `Migration table not found`, sesuai kondisi database Laravel yang masih baru.

## Security baseline

- `.env`, `vendor`, `node_modules`, dan `public/build` harus tetap tidak
  ter-track.
- Local debug aktif hanya untuk environment local.
- Session payload encryption diaktifkan melalui `SESSION_ENCRYPT=true`.
- Credential database lokal tidak dimasukkan ke `.env.example`.

## Open gates

1. Isi nama stakeholder dan owner risiko secara formal.
2. Jalankan baseline migration pada database lokal dan verifikasi rollback.
3. Selesaikan quality toolchain Phase 1: Larastan dan CI workflow.
4. Buat base application layout pada milestone terpisah setelah quality gate.
5. Konfigurasi Composer signing public keys pada mesin pengguna.

## Next authorized step

Phase 1.2 berikutnya: jalankan baseline migration, periksa schema MySQL, lalu
uji rollback/migrate ulang sebelum milestone dinyatakan selesai.
