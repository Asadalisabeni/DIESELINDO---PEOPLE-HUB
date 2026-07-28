# Recommended Repository Structure

Struktur awal mengikuti konvensi Laravel dan menambahkan boundary domain tanpa
memecah aplikasi menjadi package/microservice prematur.

```text
dieselindo-peoplehub/
├── app/
│   ├── Application/
│   │   └── <Module>/
│   │       ├── Actions/
│   │       ├── DTOs/
│   │       └── Queries/
│   ├── Domain/
│   │   ├── IdentityAccess/
│   │   ├── Organization/
│   │   ├── Employee/
│   │   ├── Attendance/
│   │   ├── Leave/
│   │   ├── Overtime/
│   │   ├── Payroll/
│   │   ├── Tax/
│   │   ├── BPJS/
│   │   ├── Approval/
│   │   ├── Reporting/
│   │   ├── Notification/
│   │   ├── Audit/
│   │   └── ImportMigration/
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Middleware/
│   │   └── Requests/
│   ├── Infrastructure/
│   │   ├── Attendance/
│   │   ├── Files/
│   │   ├── Mail/
│   │   └── Persistence/
│   ├── Livewire/
│   ├── Models/
│   ├── Policies/
│   └── Providers/
├── bootstrap/
├── config/
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── docs/
│   ├── 00-governance/
│   ├── 01-requirements/
│   ├── 02-architecture/
│   ├── 03-security/
│   ├── 04-data-migration/
│   ├── 05-testing-uat/
│   ├── 06-operations/
│   └── adr/
├── lang/
│   ├── en/
│   └── id/
├── public/
├── resources/
│   ├── css/
│   ├── js/
│   └── views/
│       ├── components/
│       ├── layouts/
│       └── livewire/
├── routes/
├── storage/
├── tests/
│   ├── Architecture/
│   ├── Feature/
│   ├── Integration/
│   ├── Security/
│   └── Unit/
├── .github/workflows/
├── .env.example
├── composer.json
├── package.json
└── README.md
```

## Dependency rules

1. Domain code tidak bergantung pada HTTP/Livewire.
2. Controller/Livewire memanggil Application Action, bukan memuat formula.
3. Integrasi vendor berada di Infrastructure dan mengimplementasikan contract
   milik domain/application.
4. Shared helper generik dibatasi; konsep bisnis ditempatkan pada module owner.
5. Model Eloquent boleh berada di `app/Models`, sementara policy, action, value
   object, calculation, dan workflow dipisahkan menurut tanggung jawab.
6. Boundary akan diperkuat dengan architecture tests pada Phase 1/2.
