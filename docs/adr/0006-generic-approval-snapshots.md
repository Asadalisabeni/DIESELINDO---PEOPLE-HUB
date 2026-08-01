# ADR-0006 — Generic Approval Engine with Resolved Snapshots

- Status: Accepted
- Date: 2 Agustus 2026

## Decision

Approval definition bersifat versioned dan effective-dated. Saat request
diajukan, engine memilih satu definition lalu membuat `approval_instance` dan
`approval_instance_steps` yang berisi snapshot urutan, resolver, approver yang
terpilih, SLA, escalation, dan subject summary. Perubahan definition berikutnya
tidak mengubah instance berjalan.

Business module menyediakan `ApprovalSubject` contract. Approval menyimpan
registered `subject_type` dan `subject_public_id`, bukan mengimpor model Domain
module tersebut. Semua action (submit, approve, reject, revise, cancel,
delegate, escalate) append-only.

Fallback direct manager: delegation aktif → manager satu level di atas → HR.
Payroll, salary, bank account, dan restricted data tidak pernah auto-approved.

## Consequences

- Generic subject tidak memiliki foreign key ke semua business table; registry,
  existence validation, immutable snapshot, reconciliation, dan integration
  tests menjadi wajib.
- Approver resolution dapat diaudit pada kondisi organisasi saat submit.
- Maker, checker, dan final approver dapat dipisahkan serta diuji.

## Alternatives rejected

- Approval column per request table: logic tersebar dan sulit versioning.
- Menghitung ulang approver setiap page load: history berubah saat organisasi
  berubah.
- Auto-approve ketika struktur kosong: tidak aman untuk transaksi sensitif.
