# Notifikasi dan evidence privat ESS

## Database notification

Phase 6 menggunakan Laravel database notification. Payload notification hanya
berisi event kind, translation key, parameter public request ID, dan named route.
Current/proposed values, alasan, catatan reviewer, nama employee, nomor rekening,
NPWP, BPJS, dan path storage tidak dimasukkan. UI menerjemahkan key saat render,
sehingga notification yang sama dapat dibaca dalam bahasa Indonesia atau Inggris.

Saat request dibuat, penerima dipilih dari user aktif yang memiliki permission
review melalui role dan manage scope efektif pada legal entity employee. Reviewer
di perusahaan lain dan Super Admin tanpa explicit row scope tidak menerima
notification. Setelah approve/reject, hanya requester menerima hasil review.

Notification center selalu menggunakan relasi `$user->notifications()`. Endpoint
mark-read mencari UUID pada relasi tersebut sebelum mengubah `read_at`; UUID
notification milik akun lain menghasilkan HTTP 404. Mark-all-read juga hanya
menulis notification akun yang sedang login.

## Evidence privat

Evidence memakai tabel `employee_documents` dan private filesystem yang sama
dengan Core HR. Filename asli hanya menjadi metadata, sedangkan storage path
dibentuk dari employee public ID dan document ULID. Download melalui authorized
controller, memeriksa kepemilikan employee atau permission administratif serta
scope, lalu mengirim `Cache-Control: private, no-store, max-age=0` dan
`X-Content-Type-Options: nosniff`.

Employee hanya dapat mengunduh dokumen yang employee ID-nya sama dengan relasi
akun. Kepemilikan ini tidak membuka dokumen employee lain pada legal entity yang
sama. HR tetap mengikuti capability `documents.download`, sensitive view, dan
legal-entity scope. Setiap download menghasilkan audit event yang menyimpan type,
classification, document public ID, serta legal entity marker tanpa storage path.

## Lifecycle dan batasan

Evidence submission berstatus `pending_review`. Approval mengubahnya menjadi
`valid`; rejection atau cancellation menjadi `archived`. File tetap disimpan
untuk audit dan retention sampai kebijakan legal hold/retention perusahaan
disetujui. Malware scanner dan object storage belum tersedia pada local baseline,
sehingga integrasi scanner tetap menjadi gate staging dan bukan diklaim selesai.
