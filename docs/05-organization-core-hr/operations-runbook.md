# Runbook Organization dan Core HR

## Upgrade lokal

Jalankan dari root Dieselindo PeopleHub. Jangan menjalankan perintah ini pada
project MES dan jangan menggunakan port 8877.

```powershell
Set-Location -LiteralPath 'C:\laragon\www\DIESELINDO PEOPLEHUB'
php artisan migrate --force
php artisan db:seed --force
php artisan migrate:status
php artisan config:clear
composer quality
npm.cmd run build
```

Expected result: migration
`2026_08_02_010000_create_organization_core_hr_tables` berstatus `Ran`, role
berjumlah 10, permission berjumlah 23, dan seluruh quality gate hijau.

Test feature mempunyai fail-fast guard terhadap `bootstrap/cache/config.php`.
Jangan menjalankan PHPUnit/Pest ketika config cache aktif karena nilai database
testing dari `phpunit.xml` dapat tertimpa; jalankan `php artisan config:clear`
terlebih dahulu. `composer quality` sudah melakukan langkah ini otomatis.

## Bootstrap data

1. Buat administrator dengan `php artisan iam:bootstrap-admin` dan password yang
   memenuhi policy minimal 12 karakter, mixed case, angka, dan simbol. Command
   meminta password secara tersembunyi dan tidak menerima password argument.
2. Masuk ke **Struktur organisasi**, buat legal entity pertama. Pembuat menerima
   scope manage otomatis.
3. Tambahkan branch, optional division, department, position, work location, dan
   cost center. Kode master menjadi immutable; perubahan operasional dilakukan
   melalui nama/status.
4. Di **Manajemen akses**, berikan user scope entity dengan level dan tanggal
   efektif. Akhiri akses melalui effective end; jangan hapus history.
5. Buat employee setelah hierarchy minimum tersedia.

## Pemeriksaan insiden

- 403 pada employee create/update: periksa permission role dan pastikan scope
  actor berlevel `manage`, aktif pada tanggal server.
- 404 pada detail/download: public ULID tidak ada atau berada di luar entity
  scope actor; jangan mengubahnya menjadi global lookup untuk troubleshooting.
- Hierarchy validation: pastikan branch/division/department/position berasal dari
  legal entity yang dipilih.
- File tidak ditemukan: periksa metadata dan private disk. Jangan memindahkan
  file ke public storage. Restore dari backup melalui prosedur terkontrol.
- Duplicate sensitive identifier: periksa blind-index conflict melalui service;
  jangan log atau query plaintext identifier.

## Rollback

Migration rollback hanya aman pada environment non-production yang telah
diverifikasi tidak mempunyai row Phase 5. Setelah employee/document/history
ada, gunakan forward-fix dan backup; jangan drop tabel atau kolom
`users.employee_id`. MySQL DDL tidak transactional. Jika migration schema baru
gagal sebelum dicatat, inventarisasi tabel dan row count terlebih dahulu sebelum
membersihkan hanya schema kosong dari migration tersebut.
