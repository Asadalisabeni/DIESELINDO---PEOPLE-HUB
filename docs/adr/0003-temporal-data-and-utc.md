# ADR-0003 — Temporal Data, Effective Dating, and UTC

- Status: Accepted
- Date: 2 Agustus 2026

## Context

Riwayat employment, salary, bank, statutory, schedule, dan rules tidak boleh
berubah ketika konfigurasi baru berlaku. Attendance juga membawa device time
dan server-received time.

## Decision

- Instant disimpan sebagai UTC pada `DATETIME(6)`; connection/session database
  menggunakan UTC dan presentation memakai `Asia/Jakarta`.
- Business calendar value seperti join date, payroll date, holiday, dan
  effective day disimpan sebagai `DATE`, bukan dikonversi timezone.
- Local time-of-day schedule disimpan sebagai `TIME` bersama timezone schedule.
- Effective-dated row memakai interval setengah terbuka
  `[effective_from, effective_to)`; `effective_to` nullable berarti masih aktif.
- Overlap untuk natural key yang sama dicegah oleh service transaction dengan
  lock dan diuji; MySQL tidak menyediakan exclusion constraint native.
- Perubahan historis membuat version/adjustment baru, bukan overwrite.
- Attendance event menyimpan `occurred_at_utc`, offset/timezone perangkat,
  `received_at_utc`, serta source sequence/idempotency key.

## Consequences

- Rentang tanggal tidak memiliki ambiguity pada boundary tengah malam.
- `DATETIME(6)` menghindari batas 2038 `TIMESTAMP` dan mempertahankan microsecond.
- Semua query “aktif pada tanggal X” memakai `from <= X AND (to IS NULL OR to > X)`.
- Clock anomaly dan timezone input harus divalidasi sebelum attendance masuk
  payroll.

## Alternatives rejected

- Menyimpan semua nilai sebagai WIB: ambigu untuk integrasi dan DST negara lain.
- Hanya menyimpan current row: merusak payroll/history.
- Inclusive end date: meningkatkan off-by-one dan overlap pada pergantian rule.
