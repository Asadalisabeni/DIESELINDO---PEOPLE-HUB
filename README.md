# Dieselindo PeopleHub

Dieselindo PeopleHub adalah proyek HRIS production untuk PT Dieselindo Utama Nusa
dan anak perusahaan. Sistem dirancang sebagai modular monolith berbasis Laravel
untuk Core HR, ESS, attendance, leave, overtime, payroll, statutory, approval,
reporting, notification, audit, dan data migration.

Status saat ini: **Phase 1 — Project setup**.

Baseline Phase 0 telah disetujui pada 28 Juli 2026. Milestone Phase 1.1 telah
menyiapkan Laravel 13, Pest, dependency frontend, konfigurasi lokal, dan
repository Git. Paket domain HRIS belum dipasang.

## Dokumen Phase 0

- [Project charter](docs/00-governance/project-charter.md)
- [Decision dan assumption log](docs/00-governance/decision-and-assumption-log.md)
- [Risk register](docs/00-governance/risk-register.md)
- [Roadmap](docs/00-governance/roadmap.md)
- [Definition of Done](docs/00-governance/definition-of-done.md)
- [Struktur repository](docs/00-governance/repository-structure.md)
- [Rencana dependency](docs/00-governance/dependency-plan.md)
- [Checklist environment lokal](docs/00-governance/local-environment-checklist.md)
- [Project state](docs/PROJECT_STATE.md)
- [Phase 1.1 bootstrap report](docs/01-project-setup/phase-1-bootstrap.md)
- [Phase 1.2 MySQL baseline report](docs/01-project-setup/phase-1-mysql-baseline.md)

## Local verification

Jalankan dari Windows PowerShell:

```powershell
Set-Location -LiteralPath 'C:\laragon\www\DIESELINDO PEOPLEHUB'

composer install
npm.cmd install
php artisan test
& '.\vendor\bin\pint.bat' --test
npm.cmd run build
```

Baseline migration MySQL sudah dijalankan dan diuji rollback/migrate ulang.

## Aturan kerja

1. Satu milestone menghasilkan perubahan kecil yang dapat diuji.
2. Perubahan database selalu melalui migration.
3. Payroll, tax, dan BPJS menggunakan effective-dated rules, snapshot,
   decimal arithmetic, service khusus, dan automated test.
4. Otorisasi data menggunakan permission, policy, dan row-level scope; menu
   tersembunyi bukan kontrol keamanan.
5. Data sensitif menggunakan least privilege, audit, masking/encryption yang
   sesuai, dan private storage.
6. Tidak ada klaim production-ready sebelum seluruh go-live gate terpenuhi.
7. Regulasi hanya diimplementasikan setelah verifikasi sumber resmi dan
   validasi perusahaan.
