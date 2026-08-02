# Core HR data model

## Aggregate employee

`employees` menyimpan identitas stabil dan cache assignment yang berlaku hari
ini: `legal_entity_id` serta `employee_number`. Source of truth penempatan adalah
`employment_histories`, bukan kolom cache.

Setiap employee memiliki public ULID, nomor unik per legal entity, status
`active|inactive|terminated`, dan actor fields. NIK dienkripsi menggunakan
Laravel encrypted cast. Equality/duplicate check menggunakan keyed HMAC blind
index dengan domain separation; empat karakter terakhir disimpan untuk masking.
Production harus mengisi `PEOPLEHUB_BLIND_INDEX_KEY` dengan key khusus di secret
manager, terpisah dari repository.

## Effective-dated history

- Interval memakai bentuk setengah terbuka `[effective_from, effective_to)`.
- Assignment baru mengunci history employee, menutup interval yang sedang
  mencakup tanggal efektif, dan menggunakan tanggal assignment berikutnya
  sebagai batas akhir bila ada.
- Tanggal efektif duplikat dan tanggal sebelum join ditolak.
- Transfer antar-entity mensyaratkan actor memiliki scope `manage` pada entity
  asal dan tujuan. Sistem tidak mengganti company pointer tanpa menulis history.
- Assignment retroaktif juga memerlukan `manage` pada entity pemilik interval
  historis yang akan ditutup. Kontak dan kontrak baru setelah transfer hanya
  boleh mengubah row milik entity employee saat ini.
- Self-manager ditolak. Manager dan seluruh referensi organisasi harus berada
  pada entity assignment.

## Contract dan profil

Contract renewal membuat row baru dan menandai contract aktif sebelumnya
`superseded`; record lama tidak dihapus. Fixed-term contract wajib mempunyai end
date. Index expiry mendukung reminder 90/60/30/7 hari pada fase notification.

Contact, emergency contact, bank account, tax profile, dan BPJS profile memakai
effective date. Nomor rekening, holder name, tax identifier, nomor BPJS, nomor
telepon, alamat, serta kontak darurat disimpan terenkripsi. Bank/tax/BPJS baru
berstatus `pending`; maker-checker verification dilanjutkan bersama approval dan
payroll foundation.

## Transaction boundary

Create employee menulis identity, assignment awal, contact, emergency contact,
contract, dan profil opsional dalam satu database transaction. Audit hanya
menyimpan nama kategori restricted yang tersedia, tidak nilainya. Database
unique constraint menjadi race guard terakhir untuk employee number, NIK blind
index, contract number, dan effective date.
