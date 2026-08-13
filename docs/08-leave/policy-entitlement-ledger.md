# Kebijakan efektif, entitlement, dan ledger

`leave_types` menyimpan klasifikasi dasar per legal entity: kode, nama, kategori,
paid/unpaid, apakah memakai saldo, unit hari, ambang bukti, dan kebutuhan konfirmasi
Payroll. Semua angka kebijakan berada di `leave_policies` yang memiliki version dan
effective interval. Versi aktif yang overlap ditolak dalam transaksi dengan row
lock. Nilai rekomendasi master prompt—12 hari setelah 12 bulan, valid 12 bulan,
carry forward off—tersedia sebagai nilai yang dapat diinput HR, bukan seed atau
aturan production hard-coded.

Grant menghasilkan satu `leave_entitlements` bucket dan satu entry positif pada
`leave_ledger_entries`. `grant_reference` unik per legal entity membuat import atau
retry idempoten. Valid-to dapat diberikan eksplisit; bila kosong dan policy memiliki
validity months, service menghitungnya dari valid-from. Saldo selalu `SUM(quantity)`
ledger, tidak ada mutable balance column. Quantity disimpan decimal(10,2) dan logic
domain mengubah nilai ke integer hundredths untuk menghindari float arithmetic.

Entry didukung: opening, entitlement, adjustment, usage, cancellation, expiry,
carry forward, dan reversal. Adjustment nol ditolak. Entry lama tidak diedit atau
dihapus; pembetulan diposting sebagai entry baru dengan reason dan reference unik.
Final approval mem-lock entitlement, memeriksa saldo terbaru, lalu mengalokasikan
usage dengan urutan expiry paling awal. Bila saldo berubah dan tidak cukup, seluruh
approval transaction rollback sehingga tidak ada approval final atau usage parsial.

Expiry dijalankan scheduler harian. Bucket yang lewat valid-to mendapat entry expiry
sebesar sisa positif lalu status bucket menjadi expired. Proses idempoten melalui
reference `EXPIRY:<public-id>`. Carry forward sengaja tidak diposting otomatis pada
Phase 8; konfigurasi tersedia, tetapi operasi memerlukan kebijakan HR yang disetujui
dan implementasi teruji agar tidak memindahkan saldo secara keliru.
