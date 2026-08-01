# ADR-0004 — Identifiers and Database Conventions

- Status: Accepted
- Date: 2 Agustus 2026

## Decision

- Primary key internal memakai `BIGINT UNSIGNED` auto-increment.
- Resource yang tampil pada URL/API/file memiliki `public_id CHAR(26)` ULID
  dengan ASCII binary collation dan unique index.
- Foreign key internal memakai bigint; public ID tidak menjadi join key utama.
- Nama tabel/kolom `snake_case`; boolean diawali `is_`/`has_`; instant diakhiri
  `_at_utc`; business date diakhiri `_date`; effective interval memakai
  `effective_from`/`effective_to`.
- Status menggunakan string-backed application enum dengan check constraint
  bila lifecycle stabil. Database enum native tidak dipakai.
- JSON hanya untuk metadata/snapshot yang tidak memerlukan relational integrity;
  field inti tetap kolom relational.
- Foreign key memakai `RESTRICT` untuk master/record penting. Cascade hanya
  untuk child murni yang tidak memiliki retention/audit mandiri.

## Consequences

- Numeric join tetap efisien dan public enumeration lebih sulit.
- ULID harus dibuat application-side dan tidak boleh diterima sebagai bukti
  authorization.
- Perubahan status enum dapat dimigrasikan tanpa native-enum lock-in.
- Public ID dan tenant scope tetap diperiksa bersama.

## Alternatives rejected

- UUID sebagai seluruh PK: index lebih besar untuk workload MySQL awal.
- Exposing numeric ID: memudahkan enumeration resource sensitif.
- Soft delete pada semua tabel: bertentangan dengan ledger/immutability dan
  menyembunyikan lifecycle bisnis.
