# Panduan Penggunaan O-KIM — Admin Verifikasi

Panduan untuk **Admin Verifikasi** di Fuel Terminal Tegal.

> **Aplikasi:** `https://o-kim.ft-tegal.com`
> **Panel:** `/admin` · **Role:** `admin_verifikasi`
> **Tugas utama:** verifikasi pendaftaran transportir & dokumen, jadwalkan pemeriksaan, terbitkan KIM.

---

## 1. Hak Akses

| Permission | Arti |
|---|---|
| `access_admin_panel` | Masuk panel `/admin` |
| `review_transporter_registrations` | Meninjau pendaftaran transportir |
| `manage_transporters` / `manage_vehicles` | Kelola transporter & kendaraan |
| `verify_documents` | Verifikasi dokumen kendaraan |
| `manage_checklists` | Kelola checklist pemeriksaan |
| `schedule_inspection` | Menjadwalkan pemeriksaan |
| `issue_kim` / `view_kim_cards` | Menerbitkan & melihat KIM |
| `view_applications` / `view_findings` | Melihat pengajuan & temuan |

Admin Verifikasi **tidak dapat**: mengisi checklist, memverifikasi tindak lanjut temuan, memberi approval, atau memberi dispensasi.

---

## 2. Masuk ke Panel

Buka **`https://o-kim.ft-tegal.com/admin/login`** → isi **Email** dan **Kata Sandi** → **Masuk**.

Menu: **Operasional** (Pengajuan & Pemeriksaan · Temuan & Verifikasi · KIM Terbit), **Master Data** (Kendaraan · Checklist Pemeriksaan), **User Manajemen** (Verifikasi Transportir).

---

## 3. Meninjau Pendaftaran Transportir

Pendaftar **baru muncul** di antrean setelah **mengirim profil perusahaan**.

1. Buka **User Manajemen → Verifikasi Transportir** → filter **Status Akun → Menunggu Verifikasi**.
2. Klik **Lihat** pada baris pendaftar; periksa nama PIC, email, nama perusahaan, alamat, telepon, dan NIB.
3. Pilih:

| Tombol | Efek |
|---|---|
| **Setujui Pendaftaran** | Akun → **Disetujui**; transportir bisa masuk panel |
| **Tolak Pendaftaran** | Akun → **Ditolak**; **wajib isi alasan** |

---

## 4. Verifikasi Dokumen Kendaraan

Ini tugas paling penting: seluruh dokumen wajib (**STNK, KEUR, TERA**) harus **Terverifikasi** sebelum jadwal pemeriksaan bisa ditetapkan.

1. Buka **detail pengajuan → Dokumen Kendaraan**, atau **Master Data → Kendaraan → detail kendaraan**.
2. Periksa berkas lewat **Pratinjau** / **Unduh**.
3. Klik **Verifikasi** (sah) atau **Tolak** (**wajib isi alasan**).

**Cek sebelum menyetujui:** jenis dokumen sesuai, NOPOL cocok, tanggal kedaluwarsa belum lewat, berkas terbaca jelas. Format PDF/JPG/PNG, maks 5 MB.

---

## 5. Menjadwalkan Pemeriksaan

**Syarat:** dokumen wajib sudah Terverifikasi; status pengajuan **Terkirim / Dalam Pemeriksaan**.

1. Buka **Operasional → Pengajuan & Pemeriksaan** → buka **detail pengajuan**.
2. Klik **Tetapkan Tanggal Pemeriksaan** → isi tanggal & waktu → simpan.
3. Untuk mengubah jadwal gunakan **Reschedule Pemeriksaan**.

Tombol **Isi Checklist** pada TAD baru aktif setelah waktu pemeriksaan tiba. Transportir & petugas menerima notifikasi.

> Di tabel daftar, kolom **Dokumen Pending** menampilkan jumlah dokumen wajib yang belum terverifikasi.

---

## 6. Checklist Pemeriksaan

Buka **Master Data → Checklist Pemeriksaan**.

- Buat checklist per **Fungsi** (HSSE/Distribusi/QQ) dengan kode, judul, dan status **Aktif**.
- Tambahkan **butir (items)** dan **sub-butir (points)**, tandai **wajib diselesaikan** bila perlu.
- Butir wajib yang belum dinilai akan menahan approval.

---

## 7. Menerbitkan KIM

Setelah pengajuan **Disetujui**, tombol **Terbitkan KIM** tersedia di detail pengajuan. Dokumen wajib harus Terverifikasi dan masih berlaku.

Setelah terbit: status → **KIM Terbit**, nomor KIM otomatis, transportir dinotifikasi. KIM sementara (bila ada dispensasi) dibatasi batas dispensasi.

---

## 8. Pertanyaan Umum

**Tombol Tetapkan Tanggal tidak bisa dipakai?** Ketiga dokumen wajib belum Terverifikasi.

**Pendaftar tidak muncul di antrean?** Belum mengirim profil perusahaan.

**Bisa mengisi checklist / memverifikasi temuan / memberi dispensasi?** Tidak — hanya **melihat** temuan. Checklist milik TAD, verifikasi tindak lanjut milik TAD/Pengawas/Super Admin, dispensasi milik FTM.

**Siapa memberi approval?** Pengawas per fungsi, lalu approval akhir FTM.

**KIM tidak bisa diterbitkan?** Status belum **Disetujui** atau dokumen wajib kedaluwarsa.
