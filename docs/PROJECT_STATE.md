# Project State

Terakhir diperbarui: 2 Agustus 2026 (Asia/Jakarta)

## Status

- Current phase: Phase 3 — Design system dan UI foundation, review candidate.
- Phase 0 gate: disetujui Project/UAT Lead pada 28 Juli 2026.
- Phase 1 gate: disetujui Project/UAT Lead pada 2 Agustus 2026; tag
  `phase-1-complete`.
- Phase 2 gate: disetujui dan dikunci Project/UAT Lead pada 2 Agustus 2026;
  tag `phase-2-complete` menunjuk main SHA
  `0b9cdaecf88a21c289a33f6469ea3c740f3289d1`.
- Phase 3 checkpoint: menunggu visual review dan pernyataan lock Project/UAT
  Lead.
- Git workflow: `feature/phase-3-design-system` → draft PR `develop` → `main`
  setelah approval.
- Laravel Framework: 13.23.0; PHP: 8.3.30; MySQL lokal: 8.4.3.
- Frontend: Blade, Tailwind CSS 4, Alpine.js 3.15.12,
  `@alpinejs/focus` 3.15.12, Vite 7.

## Phase 3 review candidate

- Configurable deep-navy/industrial-orange/slate branding dan code-native mark.
- Semantic light/dark token, system-first typography, shadow, surface, border,
  dan text hierarchy.
- Responsive application shell dengan desktop sidebar, mobile off-canvas,
  sticky topbar, breadcrumb, footer, dan disabled future navigation.
- Blade components untuk button, badge, alert, form control, state panel,
  skeleton, modal, drawer, toast, brand, dan icon.
- Responsive table serta keyboard tabs reference pattern.
- Loading, empty, error, permission-denied, confirmation, dan validation state.
- Dark mode mengikuti system preference dan menyimpan pilihan eksplisit tanpa
  data sensitif.
- Translation framework `id`/`en` dengan middleware, CSRF POST allowlist, dan
  catalog key parity test.
- Accessibility baseline: semantic landmarks, skip link, focus-visible,
  labelled controls, ARIA relationships, focus trap, live region, serta mobile
  sidebar `inert`/`aria-hidden`.

## Verification evidence

- Pint: PASS.
- Larastan/PHPStan level 8: PASS — 0 error.
- Full Pest suite: PASS — 15 tests, 200 assertions.
- Phase 3 UI suite: PASS — 7 tests, 63 assertions.
- Vite production build: PASS — 57 modules transformed.
- npm audit setelah dependency install: PASS — 0 vulnerability.
- Browser desktop 1440×1000 light/dark: PASS.
- Browser mobile 390×844: PASS, tidak ada horizontal overflow.
- Locale, tabs, modal, drawer, toast, mobile sidebar: PASS.
- Browser console: PASS, tidak ada error.
- `php artisan migrate:status`: tiga migration `[1] Ran`; Phase 3 tidak
  menambah migration/data.

## Architecture dan security invariants

- Tenant row, UTC/date, effective interval, public ULID, decimal, immutable
  payroll/audit, dan sensitive-data controls dari Phase 2 tetap berlaku.
- Menu tersembunyi/disabled bukan authorization. Phase 4 wajib server-side
  Policy/Gate/permission dan negative security tests.
- Translation dan theme preference tidak boleh menyimpan data pribadi/sensitif.
- External font/CDN/image tidak digunakan pada baseline UI.

## Open implementation gates

1. Visual sign-off Project/UAT Lead untuk Phase 3.
2. Logo dan brand guide resmi perusahaan.
3. Nama stakeholder/data/security/risk owner serta approver.
4. Legal entity/organization master dan code lists.
5. SOP/policy attendance, leave, overtime, payroll, retention, dan privacy.
6. Audit screen reader/contrast formal sebelum production.

## Next authorized step

Review Phase 3 melalui route `/` dan `/design-system`. Setelah Project/UAT Lead
menyatakan lock, promote ke `develop`/`main`, tag `phase-3-complete`, kemudian
mulai Phase 4 — Authentication, role, permission, dan audit.
