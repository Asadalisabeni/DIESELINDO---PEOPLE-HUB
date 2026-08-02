# Scope akses Employee Self-Service

## Tujuan

Phase 6 menyediakan portal karyawan untuk melihat profil sendiri, memperbarui
kontak yang diizinkan, mengajukan perubahan sensitif, melihat riwayat pengajuan,
dan menerima notifikasi. Scope ESS berbeda dari scope administrasi HR agar akun
karyawan tidak perlu memperoleh akses legal entity hanya untuk melihat dirinya.

## Keputusan authorization

Self-scope ditentukan oleh relasi unik `users.employee_id`. Policy memeriksa
permission ESS, relasi akun-karyawan, status akun aktif, dan status employee
aktif. ULID tetap digunakan pada URL. Pengguna yang tidak memiliki relasi
employee menerima HTTP 403 dan tidak memperoleh employee fallback berdasarkan
email atau nama.

Review HR memakai jalur berbeda. Reviewer wajib memiliki permission
`ess.profile-change.review` dan effective `user_legal_entity_access` berlevel
`manage` pada legal entity permintaan. Scope `view`, role tanpa permission, atau
manage scope pada perusahaan lain tidak dapat membuka maupun memproses
permintaan. Query detail reviewer disaring sebelum `firstOrFail`, sehingga
resource di luar scope menghasilkan HTTP 404.

## Permission Phase 6

- `ess.access`: membuka portal profil sendiri.
- `ess.profile.update`: mengubah telepon, alamat, dan kontak darurat sendiri.
- `ess.profile-change.request`: membuat dan membatalkan pengajuan sendiri.
- `ess.documents.download`: mengunduh dokumen milik employee yang terhubung.
- `notifications.view`: membuka notification center milik akun sendiri.
- `ess.profile-change.review`: review HR dengan tambahan manage scope.

Semua role karyawan dapat memiliki capability self-service karena seorang HR,
payroll, manager, auditor, atau administrator juga dapat menjadi employee.
Capability tersebut tidak memberikan data bila `employee_id` tidak terhubung.
Permission review hanya diberikan kepada Super Admin, Group HR Admin, dan
Company HR Admin; Super Admin tetap tidak memperoleh row scope implisit.

## Data yang ditampilkan

Profil menampilkan identitas dasar dan current employment assignment. NIK,
rekening, NPWP, serta nomor BPJS ditampilkan dalam bentuk last-four masking.
Nilai contact milik sendiri dapat ditampilkan penuh karena dibutuhkan untuk
dikoreksi. Setiap pembukaan halaman profil menulis audit event tanpa memasukkan
nilai sensitif ke audit properties.

## Negative security cases

Automated test mencakup akun tanpa relasi employee, employee tanpa administrative
entity scope, reviewer beda legal entity, notification milik akun lain, dokumen
milik employee lain, serta sensitive identifier yang tidak boleh muncul pada
HTML. Penyembunyian menu hanya membantu UX; controller, policy, dan scoped query
tetap menjadi enforcement utama.
