# Phase Roadmap

Setiap phase menggunakan entry criteria, implementasi kecil yang dapat diuji,
evidence, review, dan exit gate. Phase berikutnya tidak dimulai hanya karena
tanggal kalender tercapai.

| Phase | Fokus | Exit evidence utama |
|---:|---|---|
| 0 | Discovery dan governance | Charter, scope, assumption/decision log, risk register, stakeholder, roadmap, DoD disetujui |
| 1 | Project setup | Laravel project, Git, environment, test/static analysis/formatting, CI, base layout sehat |
| 2 | Architecture dan database design | Module boundaries, ADR, ERD, data dictionary, tenancy/security model direview |
| 3 | Design system dan UI foundation | Responsive shell, components, dark mode, i18n, accessibility baseline diuji |
| 4 | Authentication, role, permission, audit | Auth/2FA/session, granular permission, policy, audit dan security tests lulus |
| 5 | Organization dan Core HR | Organization/employee/history/contract/document vertical slice dan isolation tests |
| 6 | ESS | Profile, controlled update request, employee dashboard, notification dan access tests |
| 7 | Attendance | Schedule, sources, GPS/selfie/offline/correction, fingerprint PoC dan reconciliation |
| 8 | Leave dan izin | Types, effective rules, ledger, request/approval/expiry/report dan unit tests |
| 9 | Overtime | Request, approval, actual validation, eligibility dan calculation tests |
| 10 | Payroll foundation | Components, period, snapshot, gross-to-net, validation, lifecycle dan locking tests |
| 11 | PPh 21 dan BPJS | Official-source rule sets, golden tests, statutory report, business validation |
| 12 | Payslip, report, dashboard | Secure bilingual payslip, exports, dashboards, scheduled report dan authorization tests |
| 13 | Data migration | Templates, idempotent importer, trial runs, reconciliation dan final migration plan |
| 14 | Security hardening dan performance | Threat/security tests, sensitive-data review, indexes, performance evidence |
| 15 | Staging dan UAT | Production-like staging, UAT execution, defect/retest evidence, sign-off |
| 16 | Parallel payroll | Dua periode comparison, explained variance, Payroll/Finance sign-off |
| 17 | Production preparation | VPS/domain/SSL/firewall/backup/monitoring/pipeline/rollback drill siap |
| 18 | Production go-live | Deployment, final migration/reconciliation, smoke tests dan management sign-off |
| 19 | Hypercare dan stabilization | Monitoring, issue/RCA/patch cadence, post-implementation review |

## Delivery strategy

- Kerjakan vertical slice, bukan membuat semua tabel sekaligus tanpa use case.
- Prioritaskan control plane lebih dulu: identity, authorization, legal-entity
  isolation, audit, effective dating, dan approval.
- Payroll/attendance tidak dimulai sebelum fondasi data dan security terkait
  lolos gate.
- Setiap perubahan requirement masuk decision/assumption log dan risk review.
