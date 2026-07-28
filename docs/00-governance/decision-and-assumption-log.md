# Decision dan Assumption Log

## A. Keputusan yang sudah menjadi baseline

| ID | Keputusan |
|---|---|
| DEC-001 | Nama aplikasi sementara `Dieselindo PeopleHub`; nama repository `dieselindo-peoplehub`. |
| DEC-002 | Target adalah aplikasi production untuk PT Dieselindo Utama Nusa dan anak perusahaan, bukan demo. |
| DEC-003 | Arsitektur menggunakan modular monolith, bukan microservices untuk rilis pertama. |
| DEC-004 | Stack: Laravel 13, PHP 8.3, MySQL 8, Blade, Livewire, Alpine.js, Tailwind CSS, Vite. |
| DEC-005 | Local development menggunakan Windows, Laragon, PowerShell, Composer, npm, dan Git/GitHub. |
| DEC-006 | Satu aplikasi dan satu database dengan isolasi data berdasarkan legal entity. |
| DEC-007 | Otorisasi menggunakan permission granular, Policy, dan row-level scope. Super Admin tidak otomatis boleh membaca payroll. |
| DEC-008 | Hierarki organisasi adalah Legal Entity → Branch → Division opsional → Department → Position → Employee. |
| DEC-009 | Approval memakai generic engine dan atasan langsung sebagai dasar routing. |
| DEC-010 | Payroll bulanan menggunakan metode PPh 21 Gross, maker-checker-final approver, snapshot, versioning, dan immutable lock. |
| DEC-011 | Bank payroll awal adalah BCA; versi awal menghasilkan transfer/validation report, bukan format host-to-host tanpa spesifikasi bank. |
| DEC-012 | Attendance mendukung fingerprint, GPS/selfie, web, offline PWA, manual adjustment, dan import melalui source abstraction. |
| DEC-013 | Fingerprint Solution X100C wajib melalui technical spike; CSV/Excel adalah fallback resmi sampai protokol terbukti. |
| DEC-014 | Cuti menggunakan ledger, bukan hanya kolom saldo. |
| DEC-015 | Data lama dipertahankan melalui effective-dated history; perpindahan legal entity tidak boleh sekadar mengganti foreign key. |
| DEC-016 | Bahasa Indonesia dan Inggris, timezone tampilan `Asia/Jakarta`, mata uang IDR, responsive PWA, dan dark mode. |
| DEC-017 | Local, staging, dan production dipisahkan. Staging tidak memakai data sensitif tanpa masking. |
| DEC-018 | Branch Git yang direncanakan: `main`, `develop`, `feature/*`, dan `fix/*`; pull request wajib sebelum merge ke branch terlindungi. |
| DEC-019 | Migrasi mencakup target lima tahun data dari Excel dan sistem HR lama, dengan batch/row result, rekonsiliasi, dan idempotency. |
| DEC-020 | Go-live memerlukan UAT, dua parallel payroll run, security/data isolation validation, successful restore test, dan management sign-off. |
| DEC-021 | Baseline Phase 0 disetujui Project/UAT Lead pada 28 Juli 2026; Phase 1 diizinkan dimulai. |
| DEC-022 | Folder kerja lokal tetap `C:\laragon\www\DIESELINDO PEOPLEHUB`; nama package/repository tetap `dieselindo/peoplehub` dan `dieselindo-peoplehub`. |
| DEC-023 | Pest 4 dipakai sebagai test framework. Versi terverifikasi saat bootstrap: Pest 4.7.5 dan Laravel plugin 4.1.0. |

## B. Baseline configurable yang belum boleh dianggap kebijakan production

| ID | Baseline awal | Keputusan/artefak yang dibutuhkan |
|---|---|---|
| ASM-001 | Attendance cutoff tanggal 21–20; pembayaran tanggal 25/hari kerja sebelumnya. | Payroll calendar per legal entity dan aturan hari libur. |
| ASM-002 | Jadwal contoh Senin–Jumat 08:00–17:00, istirahat 60 menit. | Work schedule resmi per entity/branch/department/employee. |
| ASM-003 | Grace period keterlambatan 15 menit. | Kebijakan tertulis, kategori, dan keputusan apakah berdampak ke payroll. |
| ASM-004 | Cuti tahunan 12 hari setelah 12 bulan, valid 12 bulan, carry-forward nonaktif. | Policy per legal entity dan effective date. |
| ASM-005 | Surat dokter diwajibkan setelah ambang hari tertentu. | Ambang, exception, dan data-access policy medis. |
| ASM-006 | Reminder 90/60/30/7 hari; approval reminder 24/48/72 jam. | SLA dan escalation matrix per workflow. |
| ASM-007 | Metode penyimpanan timestamp UTC dan presentasi WIB. | Architecture decision record pada Phase 2. |
| ASM-008 | Deep navy, industrial orange, slate/gray, font Inter. | Logo, brand guide, dan persetujuan visual. |
| ASM-009 | Domain kandidat `hris.dieselindo.co.id`. | Persetujuan domain, DNS owner, dan environment URL. |
| ASM-010 | Server awal 2 vCPU, 4 GB RAM, 80 GB SSD/NVMe. | Load test, growth estimate, provider, dan budget. |
| ASM-011 | RPO 6 jam dan RTO 8 jam. | Business impact analysis dan management approval. |
| ASM-012 | Retention audit 7 tahun; payroll/statutory 10 tahun; dokumen pasca-exit 5 tahun. | Legal review, privacy policy, legal-hold procedure. |
| ASM-013 | Semua karyawan mengikuti program BPJS tanpa pengecualian awal. | Daftar program dan status enrollment aktual. |
| ASM-014 | Division dan cost center opsional. | Master organization dan accounting requirements. |
| ASM-015 | Employee status awal tetap dan PKWT. | Code list resmi serta status legacy untuk migration mapping. |

## C. Informasi yang perlu dikumpulkan tanpa menghalangi dokumentasi Phase 0

1. Nama sponsor, product owner, process owner, approver, dan technical owner.
2. Daftar legal entity beserta identitas legal, NPWP, alamat, logo, dan rekening.
3. SOP HR, attendance, leave, overtime, salary change, payroll, dan termination.
4. Payroll component dictionary dan contoh kalkulasi yang sudah disetujui.
5. Sampel anonim export Solution X100C dan dokumentasi/protokol vendor.
6. Inventaris file Excel dan skema sistem lama beserta data owner.
7. Volume dokumen, ukuran foto, histori attendance, dan pertumbuhan data.
8. Format payroll journal serta kebutuhan integrasi accounting.
9. Detail BCA transfer file apabila file bank khusus diperlukan.
10. Provider email, object storage, VPS, domain, VPN/IP allowlist, dan monitoring.
