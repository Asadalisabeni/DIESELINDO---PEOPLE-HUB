# Initial Dependency Plan

Belum ada package yang dipasang pada Phase 0. Semua versi dikunci melalui
`composer.lock`/`package-lock.json` setelah compatibility matrix dan proof of
concept Phase 1 selesai.

## Baseline yang telah diverifikasi

- Laravel 13 mendukung PHP 8.3–8.5 dan memerlukan minimal PHP 8.3:
  [Laravel 13 release notes](https://laravel.com/docs/13.x/releases).
- Livewire 4 mendukung Laravel 10+ dan PHP 8.1+:
  [Livewire installation](https://livewire.laravel.com/docs/4.x/installation).
- Pest saat ini memerlukan PHP 8.3+:
  [Pest installation](https://pestphp.com/docs/installation).
- Dokumentasi Spatie Permission v8 menunjukkan requirement generasi terkininya
  PHP 8.3+ dan Laravel 12+, sehingga cocok sebagai kandidat Laravel 13, tetapi
  constraint Composer aktual tetap wajib dibuktikan saat instalasi:
  [Spatie Permission](https://spatie.be/docs/laravel-permission/v8/installation-laravel).

## Dependency awal yang direncanakan

| Kategori | Kandidat | Tujuan | Gate sebelum adopsi |
|---|---|---|---|
| Framework | `laravel/framework:^13.0` | Fondasi aplikasi | Fresh install dan test lulus di PHP 8.3 |
| UI | `livewire/livewire:^4.0` | Reactive server-driven UI | Smoke test, CSP/session behavior |
| Authorization | `spatie/laravel-permission` | Role/permission granular | Laravel 13 constraint, cache, MySQL indexes, custom scope design |
| Audit | `spatie/laravel-activitylog` | Audit aktivitas aplikasi | Laravel 13 support, sensitive-field redaction, retention |
| Test | `pestphp/pest` + Laravel plugin | Unit/feature testing | Laravel plugin compatibility |
| Static analysis | `larastan/larastan` | PHPStan untuk Laravel | Laravel 13 support dan baseline tanpa suppress berlebihan |
| Formatting | `laravel/pint` | Style automation | Versi bawaan/kompatibel |
| Spreadsheet | `maatwebsite/excel` | Import/export Excel | Laravel 13 support, memory/chunk/import security PoC |
| PDF | Dipilih setelah spike | Payslip/report PDF | Laravel 13, font Indonesia, encryption/password, QR, layout test |
| Authentication | Official Laravel starter kit/Fortify candidate | Verification, reset, 2FA | Session/device requirements dan Laravel 13 support |
| Queue/cache | Laravel database driver lokal; Redis candidate staging/prod | Async mail/report/import | Capacity dan operational ownership |
| PWA | Controlled service worker terlebih dahulu | Offline attendance/installability | Idempotency, cache policy, upgrade/recovery test |

## Dependency yang sengaja ditunda

- Fingerprint vendor SDK sampai protocol/export evidence tersedia.
- BCA host-to-host/file generator sampai spesifikasi resmi diterima.
- Object-storage adapter, monitoring, backup, dan deployment packages sampai
  infrastructure decision disetujui.
- Library money/calculation tambahan sampai desain decimal/rounding Phase 2
  membuktikan kebutuhan.
- Package PWA generik jika service worker terkontrol memenuhi kebutuhan.

## Supply-chain gate

Sebelum package baru di-merge:

1. cek Laravel/PHP constraint dan supported release;
2. cek maintenance cadence, security advisories, license, transitive dependency;
3. dokumentasikan alasan dan alternative considered;
4. jalankan `composer audit`/`npm audit` saat akses registry tersedia;
5. commit lock file;
6. hindari package yang menduplikasi capability framework tanpa manfaat jelas.
