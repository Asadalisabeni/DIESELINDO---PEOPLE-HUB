# Phase 1.3 — Quality Toolchain dan CI

Tanggal: 2 Agustus 2026

## Tujuan

Menambahkan static analysis, perintah quality lokal, continuous integration,
dependency update automation, dan supply-chain checks sebelum fitur bisnis
mulai dibuat.

## Keputusan teknis

- Larastan 3.10.0 dipilih karena mendukung Illuminate/Laravel 13.
- PHPStan 2.2.7 dijalankan pada level 8.
- Tidak dibuat baseline, ignored error, atau suppression.
- Composer resolution dipatok pada PHP 8.3.30 dan package minimum stability
  diubah menjadi `stable`.
- Quality command dijalankan secara serial untuk stabilitas Windows lokal.
- CI backend dan frontend dipisah agar hasil dan kegagalan lebih jelas.

## File yang dibuat atau diubah

- `composer.json`
- `composer.lock`
- `package.json`
- `package-lock.json`
- `phpstan.neon.dist`
- `.github/workflows/ci.yml`
- `.github/dependabot.yml`
- `config/filesystems.php`
- `tests/Feature/ApplicationBaselineTest.php`
- dokumentasi project state/governance.

## Static analysis

Path yang dianalisis:

- `app`
- `bootstrap`
- `config`
- `database`
- `routes`

Larastan menemukan satu isu pada scaffold: `env('APP_URL')` dapat bertipe
boolean sementara `rtrim()` mengharuskan string. Nilai tersebut dinormalisasi
menjadi string pada batas konfigurasi dan dilindungi regression assertion.

## Quality scripts

```powershell
Set-Location -LiteralPath 'C:\laragon\www\DIESELINDO PEOPLEHUB'

composer run format:test
composer run analyse
composer run test
composer run quality
npm.cmd run check
```

`composer run quality` menjalankan formatting check, static analysis, dan test
suite. Audit dependency tetap command terpisah karena memerlukan network.

## GitHub Actions

Workflow memiliki dua job:

1. Backend menggunakan Ubuntu 24.04, PHP 8.3, Composer 2, dan service MySQL
   8.4. Job memvalidasi Composer, menginstal dependency, membuat application
   key sementara, menjalankan migration, Pint, Larastan, Pest, dan audit.
2. Frontend menggunakan Node.js 24, `npm ci --ignore-scripts`, production build,
   dan npm audit.

Workflow memakai permission minimum `contents: read`, timeout per job, dan
concurrency cancellation. Workflow divalidasi memakai actionlint 1.7.12;
binary validator diverifikasi dengan SHA-256 checksum release resmi.
Action pihak ketiga `shivammathur/setup-php` dipin ke commit SHA immutable;
Dependabot tetap memantau pembaruannya.

## Dependabot

Dependabot dijadwalkan mingguan pada zona waktu `Asia/Jakarta` untuk:

- Composer;
- npm;
- GitHub Actions.

Maksimum lima pull request terbuka per ecosystem.

## Keamanan

- Tidak ada production secret di workflow.
- MySQL password `root` hanya credential container CI yang bersifat sementara.
- Lockfile wajib digunakan pada backend dan frontend.
- Frontend lifecycle scripts dinonaktifkan saat instalasi CI.
- Dependency audit menghasilkan 0 advisory/vulnerability.
- Secret pattern scan lulus.

## Cara pengujian

```powershell
Set-Location -LiteralPath 'C:\laragon\www\DIESELINDO PEOPLEHUB'

composer validate --strict
composer check-platform-reqs
composer run quality
npm.cmd ci --ignore-scripts --dry-run
npm.cmd run check
php artisan migrate:status
```

Audit dengan network:

```powershell
composer audit --locked --no-interaction
npm.cmd audit --audit-level=high
```

## Expected result

- Composer schema/lock valid.
- Larastan menghasilkan `No errors`.
- Pint menghasilkan `passed`.
- Pest menghasilkan 3 test dan 8 assertion yang lulus.
- Vite production build berhasil.
- Tiga migration tetap `[1] Ran`.
- Audit menghasilkan 0 advisory/vulnerability.

## Hasil aktual

- Composer validation/platform: PASS.
- Larastan level 8: PASS — 0 error.
- Pint: PASS.
- Pest: PASS — 3 tests, 8 assertions.
- Frontend lock dry-run/build: PASS.
- MySQL migration status: PASS.
- Composer/npm audit: PASS — 0 finding.
- Actionlint/YAML structure validation: PASS.

## Troubleshooting

- Gunakan `npm.cmd`, bukan `npm`, apabila PowerShell memblokir `npm.ps1`.
- Jalankan quality commands secara serial jika antivirus Windows memperlambat
  beberapa proses PHP paralel.
- Warning Git `dubious ownership` hanya muncul saat command elevated berjalan
  dengan akun berbeda dari pemilik `.git`; tidak diatasi dengan global
  safe-directory agar konfigurasi mesin tidak diperlebar.

## Risiko atau keterbatasan

- Workflow sudah lolos static validation tetapi belum dapat dieksekusi di
  GitHub sebelum remote repository tersedia dan code dipush.
- Test suite aplikasi tetap memakai SQLite in-memory; CI memiliki langkah
  migration MySQL terpisah untuk menjaga compatibility evidence.
- PHP 8.3 berada pada security-fixes-only; upgrade runtime akan dievaluasi
  sebagai keputusan terpisah, bukan perubahan diam-diam.

## Git checkpoint

- Branch: `main`.
- Recommended commit:
  `chore: establish static analysis and CI quality gates`

## Checklist menuju milestone berikutnya

- [x] Larastan level 8 tersedia.
- [x] Tidak ada static-analysis baseline/suppression.
- [x] Quality scripts tersedia.
- [x] CI backend/frontend tersedia.
- [x] CI migration memakai MySQL 8.4.
- [x] Dependabot tersedia.
- [x] Workflow dan YAML tervalidasi.
- [x] Dependency audit lulus.
- [ ] CI dijalankan pada GitHub remote.
- [x] Base application layout dibuat pada milestone Phase 1.4.
