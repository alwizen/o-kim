# Referensi Menu Aplikasi O-KIM

Dokumen ini memetakan **seluruh menu** pada kedua panel beserta fungsinya, dan role mana saja yang bisa mengaksesnya.

> Untuk panduan langkah-demi-langkah, lihat panduan per role: [README.md](README.md)

---

## Ringkasan Dua Panel

| Panel | Alamat | Pengguna |
|---|---|---|
| **Admin** | `/admin` | Semua petugas internal (Super Admin, Admin Verifikasi, TAD, Pengawas, FTM) |
| **Transportir** | `/transportir` | Perusahaan angkutan (pengguna eksternal) |

---

## Panel Admin

### Grup: Operasional

| Menu | Fungsi | Role yang Bisa Akses |
|---|---|---|
| **Pengajuan & Pemeriksaan** | Daftar seluruh pengajuan KIM. Dari sini: melihat detail, mengisi checklist, menjadwalkan pemeriksaan, approval fungsi & final, menerbitkan KIM, serta mencetak Laporan Pemeriksaan & Berita Acara. | Semua petugas internal (aksi di dalamnya dibatasi izin) |
| **Temuan & Verifikasi** | Daftar seluruh temuan hasil pemeriksaan. Dari sini: memverifikasi tindak lanjut transportir (Terima/Tolak), serta FTM dapat memperpanjang, mengubah alasan, atau mencabut dispensasi. | Yang punya `view_findings` |
| **Pengajuan KIM Sementara** | **Antrean permohonan dispensasi** dari transportir. FTM menyetujui (menetapkan batas perbaikan) atau menolak. Badge menampilkan jumlah menunggu. | **FTM saja** |
| **KIM Terbit** | Daftar KIM yang sudah diterbitkan. Melihat detail, pratinjau, dan mengunduh kartu KIM (normal maupun sementara). | Yang punya `view_kim_cards` |

### Grup: Master Data

| Menu | Fungsi | Role yang Bisa Akses |
|---|---|---|
| **Kendaraan** | Data seluruh kendaraan beserta dokumennya. Bisa dipakai untuk verifikasi dokumen kendaraan. | Admin Verifikasi, Super Admin |
| **Jenis Dokumen** | Master jenis dokumen (STNK, KEUR, TERA, dll). Menentukan dokumen mana yang **wajib** dan mana yang **punya tanggal kedaluwarsa**. | Super Admin |
| **Checklist Pemeriksaan** | Master checklist: membuat checklist per fungsi, menambah butir (items) dan sub-butir (points), mengatur urutan & status aktif. | Admin Verifikasi, Super Admin |
| **Pengaturan Sistem** | Pengaturan aplikasi: masa berlaku KIM, format penomoran KIM & pengajuan, ambang peringatan kedaluwarsa. | Super Admin |

### Grup: User Manajemen

| Menu | Fungsi | Role yang Bisa Akses |
|---|---|---|
| **Pengguna Internal** | Kelola akun petugas internal: buat akun, atur role, ubah status akun, isi jabatan. | Super Admin |
| **Role & Permission** | Kelola role dan hak aksesnya. | Super Admin |
| **Verifikasi Transportir** | Meninjau pendaftaran perusahaan transportir: setujui atau tolak. | Admin Verifikasi, Super Admin |
| **Notifikasi Email** | Pengaturan pengiriman email per event: pilih role penerima dan aktifkan/nonaktifkan. | Super Admin |

---

## Panel Transportir

### Grup: Data & Profil

| Menu | Fungsi |
|---|---|
| **Kendaraan** | Kelola mobil tangki perusahaan: tambah kendaraan, isi data karoseri, unggah & perbarui dokumen (STNK/KEUR/TERA). |

### Grup: Operasional KIM

| Menu | Fungsi |
|---|---|
| **Pengajuan KIM** | Membuat pengajuan KIM (draf), mengirim pengajuan, memantau status, mencetak Laporan Pemeriksaan & Berita Acara, dan mengunduh KIM. |
| **Temuan & Tindak Lanjut** | Daftar temuan pada pengajuan perusahaan. Dari sini: mengirim bukti tindak lanjut, dan **mengajukan dispensasi** (KIM Sementara) bila perbaikan butuh waktu. |
| **KIM Terbit** | Daftar KIM yang sudah terbit untuk perusahaan Anda. Unduh PDF atau gambar kartu (siap cetak ATM). |

---

## Peta Akses Menu per Role

| Menu | Super Admin | Admin Verifikasi | TAD | Pengawas | FTM |
|---|:---:|:---:|:---:|:---:|:---:|
| Pengajuan & Pemeriksaan | ✅ | ✅ | ✅ | ✅ | ✅ |
| Temuan & Verifikasi | ✅ | ✅ (lihat) | ✅ | ✅ | ✅ |
| Pengajuan KIM Sementara | — | — | — | — | ✅ |
| KIM Terbit | ✅ | ✅ | — | ✅ | ✅ |
| Kendaraan | ✅ | ✅ | — | — | — |
| Jenis Dokumen | ✅ | — | — | — | — |
| Checklist Pemeriksaan | ✅ | ✅ | — | — | — |
| Pengaturan Sistem | ✅ | — | — | — | — |
| Pengguna Internal | ✅ | — | — | — | — |
| Role & Permission | ✅ | — | — | — | — |
| Verifikasi Transportir | ✅ | ✅ | — | — | — |
| Notifikasi Email | ✅ | — | — | — | — |

> **TAD** & **Pengawas**: menu yang sama dengan admin lain, tetapi **isi datanya dibatasi pada fungsinya** (HSSE / Distribusi / QQ) dan aksi yang tersedia mengikuti izin masing-masing.

---

## Menu vs Aksi: Perbedaan Penting

Beberapa menu terlihat sama, tetapi **aksi di dalamnya berbeda** sesuai izin. Contoh pada **Pengajuan & Pemeriksaan**:

| Aksi | Siapa yang Bisa |
|---|---|
| Isi Checklist | TAD (fungsi terkait) |
| Tetapkan/Reschedule Pemeriksaan | Admin Verifikasi |
| Approval fungsi (HSSE/Distribusi/QQ) | Pengawas fungsi terkait |
| Approval akhir | FTM |
| Terbitkan KIM | Admin Verifikasi, Pengawas, FTM |
| Cetak Laporan Pemeriksaan | Semua yang melihat pengajuan |
| Cetak Berita Acara | Semua yang melihat pengajuan (muncul bila ada temuan terverifikasi) |

Pada **Temuan & Verifikasi**:

| Aksi | Siapa yang Bisa |
|---|---|
| Terima/Tolak Tindak Lanjut | TAD se-fungsi, Pengawas fungsi, Super Admin |
| Perpanjang / Ubah Alasan / Cabut Dispensasi | FTM |
| Beri Dispensasi baru | **Tidak ada di sini** — lewat menu **Pengajuan KIM Sementara** |

---

## Dashboard

Setiap role punya widget dashboard berbeda. Rincian lengkap ada di [README.md](README.md) bagian **Dashboard per Role**.

---

## Notifikasi

Notifikasi muncul pada **ikon lonceng** di kanan atas panel. Sebagian event juga dikirim lewat **email**, tergantung pengaturan di menu **Notifikasi Email**.

Widget **Aktivitas Terbaru** pada dashboard menampilkan 10 notifikasi terakhir Anda.
