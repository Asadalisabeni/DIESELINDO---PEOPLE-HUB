# ADR-0002 — Legal-Entity Isolation

- Status: Accepted
- Date: 2 Agustus 2026

## Context

Aplikasi melayani PT Dieselindo Utama Nusa dan anak perusahaan dalam satu
database. Kebocoran payroll atau employee data lintas perusahaan adalah risiko
kritis.

## Decision

Setiap tenant-owned record memiliki `legal_entity_id` eksplisit, termasuk saat
nilai itu dapat diturunkan dari parent. Request membentuk `LegalEntityScope`
dari assignment actor dan permission. Policy memeriksa capability, row-level
scope membatasi query, dan field-level authorization membatasi data sensitif.
Semua create/update memvalidasi bahwa foreign key tenant lain memiliki
`legal_entity_id` yang sama.

Global/reference table hanya boleh tidak memiliki `legal_entity_id` bila
ownership-nya benar-benar group/global dan hal itu tercatat di data dictionary.

## Consequences

- Composite index dimulai dengan `legal_entity_id` untuk query tenant utama.
- Redundansi entity ID meningkatkan kemampuan scope dan audit, tetapi invariant
  kesamaan entity wajib diuji dalam transaction.
- `find($id)` tanpa scope pada request path dilarang.
- Group report memakai explicit authorized entity set; tidak ada wildcard
  implicit.

## Alternatives rejected

- Database per company: overhead migration/operations tidak sebanding dengan
  ukuran awal dan menyulitkan group reporting.
- Menu hiding saja: bukan kontrol keamanan.
- Global scope tanpa Policy: tidak cukup untuk field/action authorization dan
  raw/query edge cases.
