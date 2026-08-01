# ADR-0001 — Modular Monolith and Dependency Direction

- Status: Accepted
- Date: 2 Agustus 2026

## Context

Rilis pertama memiliki banyak domain yang saling berkaitan, tetapi volume awal
sekitar 100 employee dan satu delivery team tidak membenarkan biaya operasional
microservices.

## Decision

Gunakan satu Laravel application dan satu MySQL database dengan module ownership
yang eksplisit. Delivery bergantung pada Application, Application pada Domain,
dan Infrastructure mengimplementasikan port milik layer dalam. Cross-module
write hanya melalui Application contract pemilik data.

## Consequences

- Transaction kritis dapat tetap atomic.
- Deployment, observability, backup, dan incident response lebih sederhana.
- Boundary harus dijaga melalui review dan architecture test karena database
  fisik tetap dipakai bersama.
- Pemisahan service hanya dipertimbangkan setelah ada evidence scaling,
  ownership, reliability, atau security boundary yang nyata.

## Alternatives rejected

- Microservices: menambah distributed transaction, network failure, dan
  operational burden tanpa kebutuhan terukur.
- Laravel application tanpa module ownership: cepat di awal tetapi meningkatkan
  coupling payroll/security dan risiko perubahan silang.
