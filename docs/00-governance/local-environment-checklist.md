# Local Environment Checklist

Pemeriksaan dilakukan pada 28 Juli 2026 dari
`C:\laragon\www\DIESELINDO PEOPLEHUB`.

| Pemeriksaan | Status | Evidence/tindakan |
|---|---|---|
| Folder kerja tersedia dan writable | PASS | Folder dapat diakses; saat pemeriksaan masih kosong |
| Repository Git | PASS | Repository lokal diinisialisasi pada branch `main`; commit awal belum dibuat |
| PHP | PASS | PHP 8.3.30 CLI |
| `php.ini` aktif | PASS | `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.ini` |
| Extension Laravel umum | PASS | bcmath, curl, dom, fileinfo, intl, mbstring, openssl, PDO, pdo_mysql, sodium, xml, zip tersedia |
| Composer | PASS WITH WARNING | 2.10.0; signing public keys Composer belum dikonfigurasi |
| Composer registry connectivity | PASS | Packagist dapat diakses dengan izin network; install dan security audit berhasil |
| Node.js | PASS | 24.16.0 |
| npm | PASS WITH WORKAROUND | npm 11.13.0 melalui `npm.cmd`; install, build, dan audit berhasil |
| Git CLI | PASS | 2.55.0.windows.2 |
| MySQL binary | PASS | MySQL Community Server 8.4.3 tersedia di Laragon |
| MySQL pada PATH | WARNING | `mysql` tidak ditemukan lewat PATH; gunakan path Laragon atau Laragon terminal |
| MySQL service/listener | BLOCKED | Port 3306 tidak sedang listening pada saat pemeriksaan |
| Windows timezone | PASS | `(UTC+07:00) Bangkok, Hanoi, Jakarta` |
| PHP CLI default timezone | WARNING | `UTC`; aplikasi harus menetapkan `Asia/Jakarta` secara eksplisit |
| Domain/SSL/SSH/VPS | DEFERRED | Belum tersedia sesuai brief |

## Perintah verifikasi aman

```powershell
Set-Location -LiteralPath 'C:\laragon\www\DIESELINDO PEOPLEHUB'

php -v
php --ini
php -m
composer --version
composer diagnose
node --version
npm.cmd --version
git --version

& 'C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe' --version
Get-NetTCPConnection -LocalPort 3306 -State Listen -ErrorAction SilentlyContinue
Get-TimeZone
```

## Tindakan sebelum Phase 1

1. Jalankan Laragon/MySQL dan pastikan port/database credential lokal.
2. Buat database lokal `dieselindo_peoplehub`, kemudian uji migration.
3. Gunakan `npm.cmd`, atau ubah Execution Policy hanya bila kebijakan keamanan
   lokal mengizinkan. Perubahan policy tidak diperlukan untuk proyek.
4. Konfigurasi Composer signing keys secara interaktif pada mesin pengguna dan
   ulangi diagnose dengan konektivitas registry.
5. Jangan menaruh credential lokal pada file yang akan di-commit.
