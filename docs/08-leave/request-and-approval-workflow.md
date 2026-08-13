# Pengajuan dan generic approval workflow

Employee memilih leave type aktif dalam legal entity sendiri, tanggal mulai/akhir,
alasan, dan bukti bila diperlukan. Versi pertama menerima unit hari penuh saja.
Service menghitung hari dari effective work schedule dan mengecualikan weekend/non-
working day serta holiday legal entity/branch. Service juga memvalidasi employment
history efektif, eligibility months, notice, maximum request days, overlapping active
request, bukti, dan saldo. Kegagalan menggagalkan transaksi dan membersihkan file
yang sempat disimpan.

Approval bukan kolom manager/HR yang tersebar di controller. `approval_definitions`
dan `approval_steps` mendefinisikan workflow versioned; `approval_instances` serta
`approval_instance_steps` menyimpan immutable subject/resolver snapshot saat submit;
`approval_actions` mencatat submit, approve, reject, revision, cancel, escalation,
actor/acting-for, note terenkripsi, idempotency hash, dan waktu UTC.

Paid leave mengikuti Employee → Direct Manager → scoped HR. Unpaid leave menambah
scoped Payroll confirmation. Resolver manager memilih active delegation, direct
manager, upper manager, lalu scoped HR fallback sesuai urutan master prompt. Tidak
ada auto-approval, requester tidak boleh menyetujui permohonan sendiri, dan current
step saja yang dapat bertindak. Scoped-permission step harus memiliki permission
dan effective manage access pada legal entity request.

Approve mengaktifkan step berikut. Reject atau revision menutup instance dan step
tersisa. Cancel hanya dapat dilakukan requester selama workflow pending. Final paid
approval memposting ledger usage dalam transaction yang sama. Unpaid leave tidak
mengurangi ledger; status final hanya menjadi downstream input dan belum melakukan
potongan payroll. Reminder/SLA fields tersimpan 24/72 jam sebagai konfigurasi awal;
automated escalation sengaja belum diaktifkan tanpa kebijakan organisasi final.
