# Phase 2.2 — Database Design Rules

## Scope

Dokumen ini adalah contract untuk migration bertahap. Tidak semua tabel dibuat
sekaligus; setiap phase berikutnya hanya membuat tabel yang diperlukan vertical
slice dan wajib mematuhi contract ini atau menambahkan ADR baru.

## MySQL baseline

| Concern | Baseline |
|---|---|
| Engine | InnoDB |
| Character set | `utf8mb4` |
| Collation | `utf8mb4_unicode_ci`; identifier/hash memakai binary/ASCII collation bila case-sensitive |
| Internal PK | `BIGINT UNSIGNED` auto-increment |
| Public identifier | `CHAR(26)` ULID, unique, non-null untuk resource addressable |
| Instant | `DATETIME(6)` UTC, suffix `_at_utc` |
| Business date | `DATE`, suffix `_date` atau effective interval |
| Local schedule time | `TIME` + timezone IANA pada schedule |
| Money | `DECIMAL(19,4)` + `CHAR(3)` currency |
| Rate | `DECIMAL(12,8)` |
| Unit/quantity | `DECIMAL(19,6)` bila fractional |
| Status | `VARCHAR` + application enum; check constraint bila lifecycle stabil |
| JSON | metadata/snapshot non-relational saja |

## Legal-entity invariant

Tenant-owned table selalu memiliki `legal_entity_id` non-null dan index yang
dimulai dengan kolom itu. Child juga membawa entity ID bila dibutuhkan untuk
scope langsung, walau parent sudah memilikinya. Application Action memvalidasi
kesamaan entity semua foreign key di dalam transaction.

Untuk relasi berisiko tinggi, migration dapat menambah composite unique pada
parent `(legal_entity_id, id)` dan composite foreign key child agar database
ikut menolak cross-entity reference. Keputusan per tabel dicatat saat migration
karena MySQL memerlukan index yang tepat.

## Effective-dated invariant

Interval aktif adalah `[effective_from, effective_to)`. Constraint dasar:

- `effective_to IS NULL OR effective_to > effective_from`;
- unique natural key + `effective_from`;
- index natural key + `effective_from` + `effective_to`;
- overlap check melalui transaction dan row/advisory lock yang konsisten;
- tepat satu versi yang cocok untuk satu tanggal bisnis.

Backdated correction tidak mengedit payroll locked. Ia menghasilkan versi
master baru dan, bila perlu, payroll adjustment/retroactive run.

## Referential actions

- `RESTRICT` untuk legal entity, employee, employment, salary, approval,
  attendance normalized record, leave ledger, payroll, statutory, dan audit.
- `CASCADE` hanya untuk child draft/value yang tidak memiliki retention mandiri,
  misalnya definition step yang belum pernah dipakai.
- `SET NULL` hanya untuk optional pointer non-historis seperti current manager
  cache; historical/snapshot reference tetap dipertahankan.
- Hard delete dilakukan oleh controlled retention job, bukan cascade lintas
  aggregate.

## Delete and immutability matrix

| Category | Strategy |
|---|---|
| Reference master belum dipakai | restricted hard delete atau deactivate |
| Employee/employment/contract | status/effective end; tidak dihapus dari UI |
| Draft request | cancel; soft delete hanya bila requirement eksplisit |
| Attendance raw event | append-only; correction membuat record baru |
| Leave ledger | append-only reversal entry |
| Approval action/instance step | append-only; tidak soft delete |
| Payroll snapshot/item/locked run | immutable; adjustment/version/reopen workflow |
| Audit/outbox processed record | append-only sampai retention job |
| Import staging | controlled retention setelah reconciliation/sign-off |

## Lifecycle examples

- Payroll run: `draft → calculating → validation_failed|ready_for_review → reviewed → approved → locked → published`; reopen membuat version baru dan tidak menghapus versi locked.
- Approval instance: `draft → pending → approved|rejected|cancelled|revision_requested|expired`.
- Attendance event: `received → validated|anomalous|rejected → normalized`; state history/audit tetap tersedia.
- Import batch: `uploaded → validating → validated|failed → importing → reconciled|partial|failed → closed`.

## Uniqueness and race control

Business uniqueness harus ada di database, bukan hanya validation:

- legal-entity code global unique;
- employee number unique per legal entity/current assignment policy;
- one active employment interval per employee;
- attendance source external ID unique per source/entity;
- one attendance daily record per employee/entity/work date;
- leave ledger idempotency/reference unique;
- payroll group + period + run version unique;
- payroll employee unique per run;
- payroll item unique by run employee + component + sequence/version;
- approval action idempotency key unique per instance;
- import source fingerprint/idempotency key unique;
- outbox event ID unique.

Transaction kritis memakai unique constraint sebagai final race guard dan
menangani duplicate-key sebagai domain conflict, bukan HTTP 500 generik.

## Index review

Setiap migration menyertakan query-driven index. Baseline order:

1. `legal_entity_id`;
2. foreign key/scope seperti employee/period/status;
3. date/effective range;
4. public ID atau external idempotency key.

Index pada encrypted value dilarang; equality lookup memakai keyed blind index.
JSON path index hanya dibuat setelah query evidence. Report besar menggunakan
read query/snapshot, bukan menambah index spekulatif pada semua kolom.

## Idempotency and outbox

`idempotency_keys` menyimpan scope, key hash, request fingerprint, status,
result reference, dan expiry. Payload rahasia tidak disimpan. Attendance sync,
import, payroll command, dan external callback wajib menentukan scope unik.

`outbox_messages` ditulis dalam transaction yang sama dengan aggregate. Worker
mengirim setelah commit, menyimpan attempt/error yang sudah disanitasi, dan
consumer deduplicate berdasarkan event public ID.

## Migration sequencing

1. Phase 4: IAM access scope dan audit control plane.
2. Phase 5: Organization/Core HR/effective histories/private documents.
3. Phase 7–9: schedule, attendance, leave ledger, overtime.
4. Phase 10–11: compensation, payroll snapshots, tax/BPJS rule sets.
5. Phase 12–13: payslip/report snapshot/import staging.

Setiap sequence diuji fresh migration, rollback yang aman untuk environment
non-production, dan upgrade path. Production rollback untuk data-bearing
migration memakai forward-fix/expand-contract plan, bukan drop data otomatis.
