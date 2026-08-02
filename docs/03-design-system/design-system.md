# Phase 3 — Design System

Tanggal baseline: 2 Agustus 2026

Status: review candidate

## Tujuan

Menyediakan bahasa visual dan component contract tunggal sebelum authentication,
authorization, serta data HRIS sensitif diperkenalkan. Baseline ini bersifat
production-oriented, tetapi branding resmi tetap dapat diganti setelah logo dan
brand guide perusahaan disetujui.

## Brand direction

- Deep navy menyampaikan struktur, stabilitas, dan konteks enterprise.
- Industrial orange menjadi primary action dan pengenal Dieselindo.
- Slate menjaga hierarchy informasi tanpa membuat layar administrasi terlalu padat.
- Wordmark dan monogram `D`/`PH` saat ini code-native agar tajam, ringan, dan
  tidak bergantung pada asset eksternal.
- Tidak ada font, tracking pixel, CDN, atau image eksternal.

## Design tokens

Token berada di `resources/css/app.css` dan menjadi sumber tunggal untuk:

- brand scale `50`–`950`;
- navy scale `50`–`950`;
- semantic surface, border, primary text, dan secondary text;
- light/dark color scheme;
- panel dan overlay shadow;
- system-first sans dan mono font stack.

Semantic surface memakai CSS custom properties agar komponen tidak menyalin
hex value atau membuat warna dark mode sendiri-sendiri.

## Application shell

Shell di `resources/views/layouts/app.blade.php` menyediakan:

- persistent desktop sidebar;
- off-canvas mobile sidebar dengan backdrop;
- sticky topbar dan breadcrumb;
- language selector `id`/`en`;
- persisted light/dark preference;
- disabled future-module navigation yang tidak mengklaim authorization;
- skip link, main landmark, footer, dan toast live region.

Sidebar mobile menggunakan `aria-hidden` dan `inert` saat tertutup. Media query
state memastikan sidebar desktop tetap tersedia bagi keyboard dan assistive
technology.

## Component inventory

| Family | Blade component/pattern | Baseline |
|---|---|---|
| Brand | `x-brand`, `x-icon` | Code-native SVG, decorative icon hidden from AT |
| Actions | `x-button` | Primary, secondary, ghost, danger, disabled, link |
| Status | `x-badge`, `x-alert` | Neutral, brand, success, warning, danger |
| Forms | `x-form.input`, `x-form.select` | Visible label, hint/error, `aria-describedby`, invalid state |
| Empty/error | `x-state-panel` | Loading, empty, error, denied |
| Loading | `x-skeleton` | Visual skeleton plus screen-reader status |
| Overlay | `x-modal`, `x-drawer` | Escape/backdrop close, focus trap, inert background, scroll lock |
| Feedback | `x-toast-region` | Polite live region, dismissible, non-blocking |
| Navigation | Application shell/tabs | Active state, current page, keyboard tab switching |
| Data | Responsive table pattern | Caption, scoped header, horizontal containment |

Katalog hidup tersedia pada route `/design-system`. Katalog menggunakan data
contoh nonpersonal dan tidak terhubung ke database bisnis.

## Dependency decision

Phase 3 menambah dependency runtime frontend berikut:

- `alpinejs` 3.15.12 untuk state interaksi ringan;
- `@alpinejs/focus` 3.15.12 untuk focus trap dan inert/scroll management.

Livewire tidak dipasang pada Phase 3 karena belum ada server-driven business
interaction. Dependency tersebut baru dievaluasi ketika vertical slice
membutuhkannya. Audit npm setelah instalasi melaporkan nol vulnerability.

## Boundary

Phase 3 tidak menambahkan authentication, permission, employee data, query
database, atau business workflow. Menyembunyikan/menonaktifkan menu bukan
authorization. Phase 4 wajib menerapkan Policy/Gate/permission pada server.
