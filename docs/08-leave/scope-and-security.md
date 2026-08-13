# Phase 8 — Scope dan keamanan Leave & Izin

Phase 8 memiliki tiga jalur akses yang tidak saling menggantikan. Employee hanya
membaca saldo, riwayat, dan permohonan miliknya melalui relasi akun-karyawan.
Manager hanya melihat permohonan yang current approval step-nya ditugaskan kepada
dirinya atau delegate aktifnya. HR, Payroll, dan Auditor harus memiliki permission
serta `user_legal_entity_access` efektif; operasi mutasi juga mensyaratkan level
`manage`. Super Admin tidak memperoleh row-scope hanya dari nama role.

Alasan permohonan, catatan approval, subject/resolver snapshot, alasan adjustment,
dan alasan delegation disimpan dengan encrypted cast. Bukti cuti memakai private
employee document storage, nama file acak ULID, checksum, validasi MIME/extension,
dan batas 5 MB. Download hanya tersedia bagi requester atau current approver dan
selalu dicatat ke audit. Header unduhan melarang cache serta MIME sniffing.

Catatan medis diklasifikasikan Restricted. UI meminta pengguna membatasi detail
medis pada informasi yang diperlukan HR. Nama atau isi bukti tidak dimasukkan ke
notifikasi dan audit. Email hanya memuat public request ID dan status, dikirim
melalui queue, sedangkan business transaction tidak menunggu transport email.

Ledger saldo dan approval action adalah append-only pada application model:
update/delete melempar exception. Koreksi saldo menggunakan entry baru dengan
reason dan idempotency reference. Report CSV membatasi query pada intersection
scope aktor, mencatat actor/time/entity count, dan tidak mengekspor alasan atau
lampiran. Seluruh public route menggunakan ULID, bukan numeric database ID.

Lock Phase 8 menetapkan baseline implementasi, bukan persetujuan kebijakan cuti
produksi. HR/Legal wajib mengesahkan jenis, eligibility, entitlement, expiry,
carry forward, kebutuhan surat dokter, notice, delegation, SLA, dan retensi bukti.
