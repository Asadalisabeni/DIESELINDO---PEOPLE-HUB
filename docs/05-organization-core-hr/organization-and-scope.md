# Organization dan legal-entity scope

## Tujuan

Phase 5 memperkenalkan struktur `Legal Entity → Branch → Division → Department
→ Position → Employee` tanpa menjadikan parameter browser sebagai bukti
otorisasi. Work location dan cost center turut dibuat karena keduanya diperlukan
oleh employment assignment.

## Model akses

- Capability berasal dari role/permission.
- Row scope berasal dari `user_legal_entity_access` dan selalu memiliki interval
  `[effective_from, effective_to)`.
- Level `view` hanya mengizinkan read sesuai permission; level `manage`
  diperlukan untuk create/update employee, struktur, kontrak, dokumen, transfer,
  dan pemberian scope lain.
- Super Admin tidak mempunyai row scope implisit. Pembuatan legal entity
  mensyaratkan kombinasi `organization.manage` dan `entity-access.manage`,
  sehingga hanya Super Admin/Group HR Admin yang dapat membuatnya; transaksi
  tersebut otomatis memberi pembuat scope `manage` pada entity baru. Company HR
  Admin hanya dapat mengelola struktur entity yang sudah didelegasikan.
- Resource business memakai public ULID dan dicari di dalam entity set actor.
  Employee di luar scope menghasilkan 404 pada endpoint detail agar keberadaan
  record tidak bocor.

## Invariant hierarki

- Department wajib memiliki branch pada legal entity yang sama dan division
  bersifat opsional.
- Position wajib merujuk department pada entity yang sama.
- Work location boleh entity-wide atau terkait branch pada entity yang sama.
- Assignment memvalidasi seluruh foreign key melalui service dalam database
  transaction. Referensi lintas entity menghasilkan validation error dan tidak
  membuat row parsial.
- Relasi turunan employee (history, contact, contract, document, bank, tax, dan
  BPJS) selalu difilter kembali dengan entity set actor. Transfer employee tidak
  memberi akses implisit ke data historis milik entity asal.
- Master yang sudah dipakai tidak dihapus dari UI; gunakan status `inactive`.

## Permission Phase 5

Permission baru adalah `organization.view`, `organization.manage`,
`entity-access.manage`, `documents.view`, `documents.upload`,
`documents.download`, `contracts.manage`, dan `employee-financial.view`.
Seeder menginvalidasi cache Spatie sebelum membuat permission, setelah semua
permission dibuat, dan setelah role disinkronkan agar upgrade database tetap
idempotent.

## Audit

Pembuatan/perubahan entity dan unit menyimpan actor, subject, public identifier,
unit type, serta daftar field yang berubah. Tax identifier atau nilai restricted
lain tidak pernah dimasukkan ke properties audit. Seluruh event bisnis
`organization` dan `employee` membawa immutable `legal_entity_public_id`; feed
audit memfilter event tersebut terhadap scope actor dan fail closed bila event
bisnis lama/tidak valid tidak mempunyai penanda entity. Event autentikasi tetap
berada pada security plane global karena tidak dimiliki legal entity tertentu.
