# Private employee documents

## Storage contract

- File disimpan pada disk `local` yang root-nya `storage/app/private`.
- Path dibuat server: `employee-documents/{employee-public-id}/{document-ulid}.{ext}`.
  Original filename hanya metadata dan tidak memengaruhi path.
- Format saat ini: PDF, JPEG, atau PNG; maksimum 10 MiB. Laravel memvalidasi file,
  MIME hasil server inspection, dan ukuran.
- Metadata menyimpan MIME, byte size, SHA-256 checksum, issued/expiry date,
  classification, uploader, serta public ULID.
- Upload file dilakukan sebelum transaksi metadata; bila metadata/audit gagal,
  file langsung dihapus sehingga tidak ada orphan akibat request gagal.

## Authorization dan download

Tidak ada public symlink atau URL storage untuk employee document. Download
selalu melalui authorized controller yang membuktikan:

1. actor terautentikasi dan aktif;
2. document legal entity berada pada scope actor;
3. actor memiliki `documents.download`;
4. actor memiliki `employees.view-sensitive`.

Metadata nama/type/classification hanya dirender untuk actor dengan
`documents.view`, difilter ulang memakai legal-entity scope, dan tetap melewati
policy document per row. Transfer employee tidak membuka metadata atau file
document yang dibuat oleh entity asal.

Response memakai attachment filename aman, MIME tersimpan,
`X-Content-Type-Options: nosniff`, dan `Cache-Control: private, no-store,
max-age=0`. Setiap download menghasilkan audit event dengan document ULID, type,
dan classification tanpa path/checksum/content.

## Batasan sebelum production

Malware scanner belum tersedia pada local baseline. Sebelum staging/UAT,
aktifkan quarantine/scanning workflow dan object storage private bila dipilih.
Controlled retention/purge hanya boleh dibuat setelah kebijakan legal hold dan
data retention disetujui; UI Phase 5 tidak menyediakan delete document.
