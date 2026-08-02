# Phase 3 — Accessibility, Theme, dan I18n Baseline

Tanggal baseline: 2 Agustus 2026

Status: review candidate

## Accessibility baseline

Baseline berikut diterapkan dan diuji:

- satu visible `h1` per page dan heading hierarchy berurutan;
- skip link menuju focusable `main`;
- semantic `nav`, `header`, `main`, `aside`, `footer`, `table`, dan `caption`;
- current navigation memakai `aria-current="page"`;
- disabled future navigation memakai `aria-disabled="true"` dan tidak menjadi
  tautan palsu;
- seluruh icon dekoratif memakai `aria-hidden="true"`;
- visible form label dan programmatic hint/error relationship;
- invalid field memakai `aria-invalid="true"`;
- modal/drawer mempunyai accessible name, focus trap, Escape/backdrop close,
  scroll lock, dan inert background;
- toast memakai `aria-live="polite"` dan tidak mencuri fokus;
- keyboard focus memakai outline kontras yang tidak dihapus;
- loading, empty, error, permission denied, dan confirmation state memiliki
  label serta tindakan eksplisit;
- mobile sidebar menjadi `inert` dan `aria-hidden` ketika tertutup.

Baseline ini belum menggantikan audit WCAG 2.2 manual dengan assistive
technology nyata. Audit screen reader NVDA/VoiceOver, zoom 200%, reduced motion,
dan contrast tooling tetap exit gate sebelum production.

## Internationalization

- Locale default: `id`.
- Fallback locale: `en`.
- Locale yang diizinkan: `id`, `en`.
- Pilihan disimpan dalam Laravel session.
- Middleware `SetLocale` fail-closed ke `id` bila session value rusak/tidak
  didukung.
- Endpoint perubahan locale memakai POST, CSRF, allowlist validation, dan
  kembali ke halaman asal.
- Catalog `lang/id/ui.php` dan `lang/en/ui.php` harus memiliki key identik;
  automated test memeriksa parity.
- Teks komponen dan shell menggunakan translation key. Nama legal entity pada
  katalog hanya sample nonpersonal.

## Theme behavior

- Bila preferensi belum ada, theme mengikuti `prefers-color-scheme` perangkat.
- Pilihan eksplisit disimpan sebagai `peoplehub-theme` (`light` atau `dark`)
  pada `localStorage`.
- Theme storage tidak memuat identifier, session, permission, atau data HR.
- Root class dan `color-scheme` diperbarui bersama agar native controls
  mengikuti theme.

## Visual verification evidence

Pengujian dilakukan melalui browser pada server Laravel lokal sementara:

| Scenario | Result |
|---|---|
| Desktop 1440×1000, light dan dark | PASS |
| Mobile 390×844 | PASS, tidak ada horizontal overflow |
| Mobile sidebar open/close | PASS, `aria-hidden`/`inert` berubah benar |
| Indonesia ke English | PASS, session dan `<html lang>` berubah |
| Tabs | PASS, selected tab dan panel berubah |
| Modal dan drawer | PASS, dialog visible dan focus trap aktif |
| Toast | PASS, polite status visible |
| Browser console | PASS, tidak ada error |

Server verifikasi sementara bukan bagian deployment dan tidak mengubah
konfigurasi Laragon maupun port MES 8877.
