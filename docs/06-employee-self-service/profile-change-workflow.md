# Workflow perubahan profil ESS

## Perubahan langsung

Telepon, alamat, dan kontak darurat dapat diperbarui langsung oleh employee.
Service mengunci employee row, menutup interval aktif pada tanggal perubahan,
dan membuat record baru dengan interval `[effective_from, effective_to)`.
Pembaruan ulang pada hari yang sama mengubah record hari tersebut agar unique
constraint tidak dilanggar. Nilai contact dan emergency tetap terenkripsi pada
database, sedangkan audit hanya menyimpan nama field yang berubah.

## Perubahan yang membutuhkan review

Jenis permintaan Phase 6 adalah nama resmi, status perkawinan, rekening bank,
profil pajak/PTKP, profil BPJS, data keluarga, dokumen identitas, dan koreksi
employment. Request menyimpan current snapshot, proposed values, alasan, reviewer,
catatan review, waktu submit/review/apply, serta optional private evidence.
Current values, proposed values, alasan, dan review notes memakai encrypted cast.

Nama resmi, status perkawinan, dan rekening memerlukan dokumen pendukung. Dokumen
identitas selalu memerlukan file. File hanya menerima PDF/JPEG/PNG maksimal 5 MB,
memakai server-generated path, SHA-256 checksum, private disk, dan status
`pending_review` sampai keputusan dibuat.

## Anti-stale dan concurrency

Pada submission, service membuat domain-separated HMAC dari canonical current
snapshot. Reviewer mengunci request dan employee, menghitung ulang snapshot,
kemudian membandingkannya menggunakan `hash_equals`. Jika master data berubah
setelah submission, approval ditolak dan employee harus mengajukan ulang.
Hanya satu request pending untuk kombinasi employee dan type yang diperbolehkan.
Transaksi dan row lock mencegah double-review atau partial application.

## Application behavior

- Nama resmi dan status perkawinan diperbarui setelah approval.
- Bank, pajak, dan BPJS membuat record efektif baru serta menutup interval lama.
- Rekening yang sudah terhubung ke employee lain pada entity yang sama ditolak.
- Data keluarga membuat record terenkripsi dan efektif bertanggal.
- Dokumen identitas yang disetujui berubah dari evidence pending menjadi dokumen
  valid dengan tipe yang diminta; evidence ditolak atau dibatalkan diarsipkan.
- Koreksi employment tidak mengubah assignment otomatis. Approval memberi tanda
  `manual_follow_up_required`, sedangkan perubahan formal harus tetap dilakukan
  HR melalui workflow employment history Phase 5 agar hierarki, effective date,
  source/target entity scope, dan riwayat tidak dapat dilewati.

## Display dan audit

Halaman detail menunjukkan nilai lama dan usulan. Identifier rekening, pajak,
BPJS, dan keluarga tetap dimasking sebelum presentation. Audit menyimpan public
ID request, public ID employee, legal entity marker, type, status, dan keberadaan
attachment; audit tidak menyimpan payload, alasan, nama file path, atau nomor
identitas penuh.
