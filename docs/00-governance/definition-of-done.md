# Definition of Done

## A. Work item

Sebuah work item dinyatakan selesai jika:

- acceptance criteria dapat ditelusuri ke requirement;
- implementasi lengkap, tidak memiliki placeholder pada jalur kritis;
- validation, authorization, legal-entity isolation, dan audit diterapkan sesuai
  klasifikasi data;
- perubahan database menggunakan reversible migration, foreign key, constraint,
  dan index yang ditinjau;
- proses kritis menggunakan transaction dan idempotency bila relevan;
- unit/feature/integration/security test yang relevan lulus;
- code formatter dan static analysis lulus;
- tidak ada secret atau personal/payroll production data di source/test fixture;
- perubahan UI responsif, bilingual, dark-mode compatible, accessible, dan
  memiliki loading/empty/error/denied state yang relevan;
- dokumentasi, release note, dan project state diperbarui;
- peer review selesai dan evidence test tercatat.

## B. Milestone

Milestone selesai jika:

- seluruh work item dan acceptance criteria milestone selesai;
- migration diuji pada database kosong dan upgrade path;
- regression suite lulus;
- negative authorization dan cross-company isolation tests lulus;
- risiko baru ditriase dan risiko terbuka memiliki owner/treatment;
- manual test menghasilkan expected result;
- rollback/recovery untuk perubahan berisiko telah diuji;
- demo/review bersama process owner selesai;
- daftar file berubah, cara uji, batasan, dan Git checkpoint dicatat;
- approver milestone memberikan sign-off.

## C. Payroll/statutory tambahan

Perubahan payroll, PPh 21, BPJS, overtime, prorate, atau rounding hanya selesai
jika:

- formula berada di service/domain class, bukan controller;
- aturan dan tarif tidak di-hard-code dan memiliki effective date;
- uang tidak dihitung dengan float;
- sumber regulasi resmi dan tanggal akses terdokumentasi;
- golden test mencakup boundary, joiner, leaver, retroactive, off-cycle, nol,
  negatif, dan rounding yang relevan;
- snapshot/lock immutability dan reopen/adjustment diuji;
- hasil direview Payroll dan Finance; untuk statutory juga oleh pihak yang
  kompeten.

## D. Release candidate/go-live

Release tidak boleh disebut production-ready sebelum:

- scope rilis pertama dan dokumentasi operasional selesai;
- tidak ada defect Critical atau High yang memengaruhi payroll, security, data
  integrity, atau approval;
- UAT dan dua parallel payroll run mendapat sign-off;
- statutory rules divalidasi;
- migration reconciliation, backup, dan restore test berhasil;
- HTTPS, secret management, queue, scheduler, email, audit, monitoring, alert,
  hardening, dan least privilege telah diverifikasi;
- rollback plan dan go-live/hypercare ownership disetujui manajemen.
