# Phase 1.1 — Laravel Bootstrap

Tanggal: 28 Juli 2026

## Tujuan

Membentuk aplikasi Laravel 13 yang dapat dijalankan dan diuji, mempertahankan
seluruh artefak Phase 0, serta mengunci dependency backend/frontend sebelum
modul HRIS dibuat.

## Keputusan teknis

- Menggunakan scaffold resmi `laravel/laravel` versi 13.
- Scaffold dibuat di folder sementara dan baru digabung setelah platform dan
  test bawaan lulus.
- `README.md` proyek dipertahankan; README generik scaffold tidak digunakan.
- Pest 4 dipasang sesuai baseline testing proyek.
- MySQL menjadi default local connection; PHPUnit/Pest tetap memakai SQLite
  in-memory agar unit/feature baseline deterministik dan tidak bergantung pada
  service lokal.
- Perintah npm di Windows menggunakan `npm.cmd` karena `npm.ps1` diblokir oleh
  PowerShell Execution Policy.

## Dependency terverifikasi

| Dependency | Versi |
|---|---:|
| Laravel Framework | 13.23.0 |
| Pest | 4.7.5 |
| Pest Laravel plugin | 4.1.0 |
| PHPUnit (transitive) | 12.5.30 |
| Tailwind CSS | lockfile |
| Vite | 7.3.6 saat build |

Versi frontend rinci menjadi sumber kebenaran di `package-lock.json`.

## Konfigurasi

- Application name: `Dieselindo PeopleHub`.
- Application URL local: `http://dieselindo-peoplehub.test`.
- Timezone: `Asia/Jakarta`.
- Locale: `id`; fallback: `en`; Faker: `id_ID`.
- Database: MySQL pada `127.0.0.1:3306`, database
  `dieselindo_peoplehub`.
- Session encryption: aktif.

## Verification

| Check | Result |
|---|---|
| Composer schema/lock validation | PASS |
| PHP platform requirements | PASS |
| Pest test suite | PASS — 3 tests, 7 assertions |
| Laravel Pint | PASS |
| Vite production build | PASS |
| Composer security audit | PASS — 0 advisories |
| npm security audit | PASS — 0 vulnerabilities |

## Batasan

- MySQL belum listening pada port 3306, sehingga migration MySQL belum diuji.
- Larastan dan GitHub Actions belum dipasang/dibuat.
- Authentication, Livewire, authorization, dan domain HRIS belum dimulai.
- Composer signing public keys pada mesin pengguna masih perlu dikonfigurasi.

## Troubleshooting

- Gunakan `npm.cmd`, bukan `npm`, jika PowerShell menolak `npm.ps1`.
- Selalu gunakan:

  ```powershell
  Set-Location -LiteralPath 'C:\laragon\www\DIESELINDO PEOPLEHUB'
  ```

- Untuk constraint Composer di Windows melalui `composer.bat`, gunakan
  constraint yang tidak bergantung pada caret batch, misalnya `4.*`, atau
  panggil Composer PHAR secara eksplisit.
- Error `dubious ownership` hanya terjadi pada akun sandbox Codex. Perintah
  internal Codex memakai `git -c safe.directory='C:/laragon/www/DIESELINDO PEOPLEHUB'`.

## Git checkpoint

- Branch: `main`.
- Recommended commit:
  `chore: bootstrap Laravel 13 project foundation`
- Commit dilakukan setelah pengecekan daftar file tracked dan secret scan.

## Checklist menuju milestone berikutnya

- [x] Laravel berjalan.
- [x] Dependency terkunci.
- [x] Test framework dan baseline test berjalan.
- [x] Formatting dan frontend build lulus.
- [x] Dependency security audit lulus.
- [x] `.env` di-ignore.
- [ ] MySQL local aktif.
- [ ] Database local dibuat.
- [ ] Migration baseline lulus pada MySQL.
