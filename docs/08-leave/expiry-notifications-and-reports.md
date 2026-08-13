# Expiry, notifikasi, dan laporan Leave

Command `php artisan leave:process-lifecycle` dijadwalkan setiap hari pukul 00:30
Asia/Jakarta dengan `withoutOverlapping`. Command menerima `--date=YYYY-MM-DD` untuk
rehearsal terkontrol. Proses pertama memposting expiry ledger idempoten untuk bucket
yang sudah lewat valid-to. Proses kedua mengirim reminder 30 hari sebelum valid-to.
Angka 30 hari adalah baseline operasional saat ini dan harus dipindahkan menjadi
configuration owner-approved sebelum production go-live.

Notifikasi approval pending, hasil review, dan balance expiry menggunakan database
dan mail channel. Notification mengimplementasikan `ShouldQueue`; payload hanya
berisi public request ID, translation key, status, tanggal, dan route. Alasan cuti,
saldo, detail medis, filename, dan legal entity sensitif tidak dikirim melalui email.
Template memakai translation key sehingga bahasa Indonesia dan Inggris tetap
konsisten dengan locale worker yang dikonfigurasi pada deployment.

Admin/report view menyediakan daftar request dan filter tanggal. CSV export hanya
bagi aktor yang memiliki `leave.report` dan `reports.export`, serta hanya legal entity
dalam effective scope. Kolom terdiri dari legal entity code, employee number/name,
leave type, periode, jumlah hari, dan status; reason serta evidence tidak diekspor.
Setiap export mencatat event `leave_report_exported`, actor, format, entity count,
dan timestamp pada audit trail.

Phase 8 report adalah baseline leave transactions yang aman dan dapat direkonsiliasi.
Excel/PDF, branch/department filter, calendar visualization, snapshot report, dan
scheduled report lengkap tetap menjadi scope Phase 12. Data source dan authorization
contract Phase 8 sengaja disiapkan agar penambahan format tersebut tidak menulis atau
mengubah source-of-truth leave tables.
