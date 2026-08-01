# ADR-0005 — Decimal Money and Explicit Rounding

- Status: Accepted
- Date: 2 Agustus 2026

## Context

Payroll, tax, BPJS, overtime, prorate, dan variance tidak boleh dihitung dengan
binary floating point atau aturan pembulatan tersembunyi.

## Decision

- Nilai uang disimpan sebagai `DECIMAL(19,4)` dengan `currency CHAR(3)`; IDR
  tetap disimpan dalam skala ini agar calculation/snapshot konsisten.
- Rate/percentage memakai `DECIMAL(12,8)` dan unit/quantity memakai
  `DECIMAL(19,6)` sesuai kebutuhan field.
- PHP menerima/menghasilkan decimal string. Float dilarang pada domain payroll.
- Pembulatan dilakukan hanya pada boundary yang ditentukan rule set/component,
  dengan `rounding_mode` dan `rounding_scale` tersimpan pada version/snapshot.
- Baseline tampilan/pembayaran IDR adalah scale 0, tetapi statusnya configurable
  sampai Payroll/Finance dan statutory validator menyetujui setiap rule.
- Locked payroll menyimpan input, unrounded/intermediate evidence yang relevan,
  rounded amount, rule version, dan adjustment—not recomputation dari master.

## Consequences

- Money library/BCMath dipilih melalui compatibility and calculation spike
  sebelum Phase 10; keputusan ini tidak otomatis menambah dependency.
- Aggregation dan comparison memakai decimal semantics.
- Rounding difference harus dapat direkonsiliasi per component dan employee.

## Alternatives rejected

- PHP float: tidak deterministik untuk nilai uang.
- Integer rupiah saja: tidak cukup untuk rate, prorate, dan statutory
  intermediate calculation.
- Rounding hanya di UI: membuat ledger dan report tidak konsisten.
