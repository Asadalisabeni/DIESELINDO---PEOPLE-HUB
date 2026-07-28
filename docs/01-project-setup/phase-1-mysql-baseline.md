# Phase 1.2 — MySQL Baseline Migration

Tanggal: 28 Juli 2026

## Tujuan

Memastikan Laravel terhubung ke database lokal yang benar, baseline migration
dapat diterapkan dan dibalik secara aman, serta hasil schema sesuai dengan
konfigurasi MySQL proyek.

## Target terverifikasi

| Properti | Nilai |
|---|---|
| Host | `127.0.0.1` |
| Port | `3306` |
| MySQL | 8.4.3 |
| Database | `dieselindo_peoplehub` |
| Character set | `utf8mb4` |
| Collation | `utf8mb4_unicode_ci` |

## Migrasi dan data

Baseline Laravel menjalankan:

1. `0001_01_01_000000_create_users_table`
2. `0001_01_01_000001_create_cache_table`
3. `0001_01_01_000002_create_jobs_table`

Sebelum rollback, seluruh tabel aplikasi diverifikasi memiliki 0 baris:
`users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`,
`job_batches`, dan `failed_jobs`.

Belum ada tabel domain HRIS atau data bisnis.

## Uji reversibility

1. Migration awal berada pada batch 1 dan berstatus `Ran`.
2. `php artisan migrate:rollback --step=3` berhasil membalik tepat tiga
   migration.
3. Setelah rollback, hanya tabel `migrations` tersisa dengan 0 baris.
4. Ketiga migration berubah menjadi `Pending`.
5. `php artisan migrate` berhasil menjalankan kembali ketiganya pada batch 1.

## Schema akhir

Schema memiliki 9 tabel:

- `cache`
- `cache_locks`
- `failed_jobs`
- `job_batches`
- `jobs`
- `migrations`
- `password_reset_tokens`
- `sessions`
- `users`

Seluruh tabel menggunakan InnoDB dan `utf8mb4_unicode_ci`. Index penting yang
terverifikasi mencakup:

- unique index `users.email`;
- index `sessions.user_id`;
- index `sessions.last_activity`;
- index `jobs.queue`;
- unique index `failed_jobs.uuid`;
- expiration indexes pada cache dan cache locks.

## Keamanan

- Pemeriksaan target database dilakukan sebelum rollback.
- Rollback hanya dijalankan setelah semua tabel aplikasi terbukti kosong.
- Perintah menggunakan `--step=3`, bukan rollback tanpa batas.
- Tidak ada database lain yang diubah.
- Akun `root` tanpa password hanya boleh digunakan pada Laragon lokal.
  Staging/production wajib menggunakan akun aplikasi least-privilege dan secret
  yang dikelola di luar repository.

## Cara pengujian

```powershell
Set-Location -LiteralPath 'C:\laragon\www\DIESELINDO PEOPLEHUB'

php artisan migrate:status
php artisan test
& '.\vendor\bin\pint.bat' --test
```

## Expected result

- Tiga migration berstatus `[1] Ran`.
- Pest menghasilkan 3 test dan 7 assertion yang lulus.
- Pint menghasilkan status `passed`.

## Hasil aktual

- Migration status: PASS.
- Rollback: PASS.
- Migrate ulang: PASS.
- Engine/collation/index review: PASS.
- Pest: PASS — 3 tests, 7 assertions.
- Pint: PASS.

## Batasan

- Baseline tests menggunakan SQLite in-memory untuk kecepatan dan isolasi;
  kompatibilitas MySQL divalidasi melalui migration/rollback aktual.
- Belum ada migration domain atau foreign key lintas domain.
- Belum ada dedicated database user lokal.

## Git checkpoint

- Branch: `main`.
- Recommended commit:
  `chore: verify MySQL baseline migrations`

## Checklist menuju milestone berikutnya

- [x] Koneksi menuju database yang benar.
- [x] Charset dan collation benar.
- [x] Migration dapat diterapkan.
- [x] Migration dapat di-rollback.
- [x] Migration dapat diterapkan ulang.
- [x] Schema dan index baseline ditinjau.
- [x] Regression tests lulus.
- [ ] Larastan terpasang.
- [ ] Quality scripts tersedia.
- [ ] GitHub Actions workflow tersedia.
