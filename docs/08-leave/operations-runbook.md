# Runbook operasional Leave & Izin

## Konfigurasi awal

HR membuat leave type per legal entity dan mengisi policy efektif. Jangan menganggap
nilai 12 hari/12 bulan sebagai kebijakan final sebelum sign-off. Pastikan work schedule,
holiday, employment history, direct manager, akun employee, HR manage scope, dan
Payroll scope untuk unpaid leave tersedia. Buat entitlement dengan grant reference
unik yang dapat ditelusuri ke dokumen HR atau batch migration.

## Operasi harian

Pantau queue Manager, HR, dan Payroll. Request yang tidak memiliki approver harus
ditolak saat submit; jangan menambahkan bypass role. Delegation harus terbatas tanggal,
legal entity, dan subject leave_request, dengan alasan audit. Cabut delegation setelah
pengganti tidak lagi berlaku. Adjustment selalu membutuhkan quantity non-zero, tanggal,
reason, dan reference; jangan mengedit record ledger menggunakan SQL manual.

Scheduler wajib menjalankan `schedule:run`. Periksa output `leave:process-lifecycle`
dan failed jobs. Untuk rehearsal gunakan tanggal non-production yang disetujui dan
database backup. Jangan menjalankan expiry maju pada production karena entry ledger
append-only. Bila expiry salah, buat reversal/adjustment melalui service setelah review,
bukan menghapus entry.

## Rekonsiliasi dan insiden

Bandingkan saldo UI dengan SUM ledger per entitlement. Periksa duplicate reference,
valid-to, usage request, dan approval action history. Jika balance berubah saat final
approval, request tetap pending karena transaction rollback; HR harus mengoreksi data
dan mengulang approval dengan idempotency key baru. Cross-company visibility, bukti
medis terbuka, atau unauthorized export adalah insiden keamanan: cabut sesi/akses,
preservasi audit, hentikan export, dan eskalasi ke owner keamanan.

## Verification checklist

Jalankan migration status, role seeder, Phase 8 tests, full composer quality, Vite
build, Blade cache, route list, Composer/npm audit, scheduler list, dan browser UAT
Employee/Manager/HR/Payroll. Tidak boleh ada reset/fresh/rollback pada database bisnis.
