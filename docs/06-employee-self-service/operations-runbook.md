# Runbook operasional Phase 6 ESS

## Migration dan seed

Jalankan dari PowerShell pada root project:

```powershell
Set-Location -LiteralPath 'C:\laragon\www\DIESELINDO PEOPLEHUB'
php artisan config:clear
php artisan migrate
php artisan db:seed --class=RolePermissionSeeder
php artisan migrate:status
```

Expected result: migration
`2026_08_03_000000_create_employee_self_service_tables` berstatus `Ran`, tabel
family/request/notification tersedia, dan role-permission seed selesai tanpa
duplikasi. Jangan menjalankan test ketika `bootstrap/cache/config.php` ada;
`tests/TestCase.php` akan menghentikan proses sebelum trait database berjalan.

## Account-to-employee link

ESS hanya aktif setelah HR/IAM menghubungkan `users.employee_id` ke employee yang
benar. Relasi tersebut unique. Jangan membuat pencocokan otomatis berdasarkan
email karena shared mailbox, perubahan email, dan typo dapat mengakibatkan akses
profil yang salah. Account exit harus dinonaktifkan melalui IAM agar session
dicabut sesuai Phase 4.

## Pemeriksaan manual

1. Login sebagai employee yang sudah terhubung dan buka `/ess`.
2. Pastikan hanya profil sendiri tampil dan identifier termasking.
3. Ubah contact, lalu pastikan pesan sukses dan nilai baru terlihat.
4. Ajukan perubahan nama/rekening dengan evidence yang valid.
5. Login sebagai Company HR Admin dengan manage scope entity yang sama.
6. Buka `/ess-review`, periksa nilai lama/usulan dan evidence privat.
7. Approve atau reject dengan catatan, lalu verifikasi notification requester.
8. Coba reviewer entity lain dan pastikan detail menghasilkan 404.
9. Uji bahasa ID/EN, dark mode, keyboard focus, mobile layout, dan console browser.

## Automated verification

```powershell
php artisan test tests/Feature/PhaseSixEmployeeSelfServiceTest.php
composer quality
npm run build
composer validate --strict
composer audit
npm audit
```

Expected result: seluruh test dan quality gate lulus, PHPStan 0 error, Pint bersih,
Vite build berhasil, serta dependency audit tidak memiliki vulnerability.

## Troubleshooting

- HTTP 403 pada `/ess`: periksa account aktif, role seed, `users.employee_id`, dan
  employee status aktif.
- Antrean review 403: reviewer belum mempunyai manage scope efektif.
- Approval stale: master data berubah setelah submission; batalkan dan ajukan
  ulang agar current snapshot baru tercatat.
- Attachment gagal: periksa MIME, ukuran 5 MB, dan permission
  `storage/app/private`.
- Notification kosong: periksa permission reviewer serta periode
  `user_legal_entity_access`; jangan memberikan bypass global.
