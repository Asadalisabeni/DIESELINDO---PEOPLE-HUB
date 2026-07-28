# Project Charter — Dieselindo PeopleHub

## 1. Tujuan

Membangun HRIS yang dapat digunakan secara nyata oleh PT Dieselindo Utama Nusa
dan anak perusahaan, dengan kontrol keamanan, auditabilitas, isolasi
multi-company, dan ketepatan payroll yang layak untuk production.

## 2. Sasaran bisnis

- Menyatukan employee master dan histori employment.
- Menyediakan ESS yang dapat digunakan dari desktop dan mobile.
- Mengelola attendance, leave, izin, overtime, dan approval secara terlacak.
- Menghasilkan payroll, PPh 21, BPJS, payslip, dan laporan dengan maker-checker.
- Mengurangi proses manual tanpa menghilangkan review manusia pada transaksi
  sensitif.
- Menyediakan jejak audit, migrasi data, backup/restore, dan kontrol operasional
  untuk go-live.

## 3. Ruang lingkup rilis pertama

Rilis pertama mencakup identity and access, role/permission, multi-legal entity,
organization, Core HR, ESS, attendance, leave/izin, overtime, payroll, PPh 21,
BPJS, payslip, approval, report, dashboard, notification, audit, import/migration,
backup/restore, staging, UAT, parallel payroll, deployment, dan hypercare.

Di luar rilis pertama: ATS, performance, learning, succession, engagement,
employee loan, reimbursement, dan advanced AI analytics.

## 4. Pengguna dan organisasi awal

- Perkiraan maksimum awal: sekitar 100 karyawan.
- Peran operasional awal: HR, Payroll Administrator, Finance/Accounting,
  Manager, Supervisor, Employee, Auditor, dan Super Admin.
- Lokasi awal: Sunter, Tigaraksa, dan Krekot.
- Departemen awal: Sales, Gudang, Finance, HR, dan Inventory.
- Hierarki: Legal Entity → Branch → Division (opsional) → Department →
  Position → Employee.
- Setiap employee memiliki satu atasan langsung sebagai dasar approval.

## 5. Prinsip arsitektur

- Modular monolith, satu aplikasi dan satu database.
- Setiap transaksi HR memiliki legal entity yang dapat ditelusuri.
- Controller tipis; aturan bisnis berada pada Action, Service, atau Domain class.
- Timestamp aplikasi disajikan konsisten dalam `Asia/Jakarta`; strategi
  penyimpanan UTC akan diputuskan pada Phase 2.
- Nilai uang menggunakan decimal, bukan float.
- Aturan bisnis yang dapat berubah disimpan sebagai konfigurasi
  effective-dated.
- Payroll terkunci immutable dan berasal dari snapshot.
- File sensitif disimpan private dan dilayani melalui otorisasi/signed URL.

## 6. Tata kelola dan stakeholder

| Stakeholder | Peran proyek | Tanggung jawab keputusan/sign-off |
|---|---|---|
| Sponsor/Manajemen | Sponsor | Scope, anggaran, risiko residual, go-live |
| UAT Lead | Koordinator penerimaan | UAT plan, defect triage, koordinasi sign-off |
| HR | Process owner | Core HR, leave, attendance, policy employment |
| Payroll Administrator | Payroll SME/maker | Formula, data input, parallel run |
| Finance | Checker | Rekening, total biaya, journal, payroll review |
| Final Payroll Approver | Final approver | Approval, lock, reopen |
| Manager/Supervisor | Operational approver | Routing dan SLA approval |
| IT/Infrastructure | Technical operator | Network, server, access, backup, monitoring |
| Tax/BPJS adviser | Regulatory validator | Validasi statutory sebelum production |
| Engineering/QA/Security | Delivery team | Implementasi, test, security, dokumentasi |

Nama pemegang peran dan RACI rinci masih perlu diisi perusahaan.

## 7. Milestone Phase 0

Phase 0 selesai apabila:

- charter, scope, keputusan, asumsi, risiko, roadmap, dan Definition of Done
  ditinjau;
- stakeholder owner dan approver utama ditetapkan;
- sumber kebijakan HR/payroll dan sumber data migrasi diinventarisasi;
- isu environment yang menghalangi Phase 1 memiliki owner dan tindakan;
- baseline memperoleh persetujuan sponsor/representative yang berwenang.

## 8. Definition of success

Keberhasilan akhir bukan sekadar fitur selesai. Rilis harus melewati automated
tests, security and isolation tests, UAT, dua parallel payroll run, statutory
validation, migration reconciliation, restore test, operational readiness, dan
management sign-off.
