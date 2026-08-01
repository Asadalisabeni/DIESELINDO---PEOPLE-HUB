# Phase 1.4 — Base Application Layout

Tanggal: 2 Agustus 2026

## Tujuan

Mengganti halaman welcome bawaan Laravel dengan layout Blade minimal yang dapat
digunakan ulang dan diuji, tanpa mengambil scope design system, authentication,
atau modul bisnis HRIS.

## Implementasi

- `resources/views/layouts/app.blade.php` menyediakan document title, locale,
  Vite assets, skip link, `header`, `main`, dan `footer`.
- `resources/views/welcome.blade.php` menggunakan shared layout dan menampilkan
  status fondasi aplikasi.
- `resources/css/app.css` memakai system font stack sehingga halaman tidak
  bergantung pada font CDN.
- `tests/Feature/ApplicationBaselineTest.php` memverifikasi response, view,
  locale, landmark semantik, skip link, dan status Phase 1.
- `tests/TestCase.php` menonaktifkan resolusi Vite hanya selama automated test,
  sehingga backend test tidak bergantung pada output build dari job frontend.

## Boundary

Milestone ini tidak menetapkan design system final, dark mode, navigation
modul, dashboard, authentication, logo, atau brand token. Seluruhnya tetap
menjadi bagian phase yang sesuai pada roadmap.

## Verification

```powershell
Set-Location -LiteralPath 'C:\laragon\www\DIESELINDO PEOPLEHUB'

composer run quality
npm.cmd run check
```

Hasil:

- Pint: PASS.
- Larastan level 8: PASS — 0 error.
- Pest: PASS — 4 tests, 17 assertions.
- Vite production build: PASS.
- Runtime HTTP: PASS — status 200.
- Browser desktop 1280×720: PASS — tidak ada horizontal overflow atau console
  error.
- Browser mobile 375×812: PASS — tidak ada horizontal overflow atau console
  error.
- HTML memakai `lang="id"` dan tepat satu `header`, `main`, serta `footer`.

## Git checkpoint

- Branch: `main` untuk initial repository bootstrap.
- Commit message: `feat: complete phase 1 project setup`.
- Phase tag: `phase-1-complete` setelah GitHub Actions pada commit checkpoint
  lulus.
