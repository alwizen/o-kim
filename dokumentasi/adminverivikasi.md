# Panduan Penggunaan O-KIM — Admin Verifikasi

Panduan ini untuk pengguna dengan role **Admin Verifikasi**.

> **Alamat panel:** `/admin`
> **Role:** `admin_verifikasi`
> **Hak akses utama:** verifikasi pendaftaran & dokumen, kelola data, jadwalkan pemeriksaan.

---

## 1. Ringkasan Peran

Admin Verifikasi adalah **pintu masuk data** ke dalam sistem. Tugas utamanya memastikan data transportir, kendaraan, dan dokumen sah sebelum masuk proses pemeriksaan.

Admin Verifikasi **dapat**:

- Meninjau & menyetujui pendaftaran transportir
- Mengelola data transporter dan kendaraan
- **Memverifikasi dokumen kendaraan**
- Mengelola checklist pemeriksaan
- **Menjadwalkan pemeriksaan**
- Menerbitkan KIM
- Melihat pengajuan, temuan, dan kartu KIM

Admin Verifikasi **tidak dapat**:

- Mengisi checklist
- Memverifikasi tindak lanjut temuan
- Memberi approval pengajuan
- Memberi dispensasi temuan

---

## 2. Hak Akses (Permission)

| Permission | Arti |
|---|---|
| `access_admin_panel` | Masuk panel `/admin` |
| `review_transporter_registrations` | Meninjau pendaftaran transportir |
| `manage_transporters` | Kelola data transporter |
| `manage_vehicles` | Kelola data kendaraan |
| `verify_documents` | Verifikasi dokumen kendaraan |
| `view_applications` | Melihat pengajuan |
| `view_findings` | Melihat temuan |
| `manage_checklists` | Kelola checklist pemeriksaan |
| `schedule_inspection` | Menjadwalkan pemeriksaan |
| `issue_kim` | Menerbitkan KIM |
| `view_kim_cards` | Melihat kartu KIM |

---

## 3. Menu di Panel Admin

| Grup | Menu | Fungsi |
|---|---|---|
| Operasional | **Pengajuan & Pemeriksaan** | Daftar pengajuan, jadwal, penerbitan KIM |
| Operasional | **Temuan & Verifikasi** | Melihat temuan (hanya lihat) |
| Operasional | **KIM Terbit** | Daftar KIM terbit |
| Master Data | **Transporter** | Kelola perusahaan |
| Master Data | **Kendaraan** | Kelola kendaraan + dokumen |
| Master Data | **Checklist Pemeriksaan** | Kelola butir checklist |

> 📖 **Fungsi lengkap setiap menu & peta akses per role:** [menu.md](menu.md)

---

## 4. Meninjau Pendaftaran Transportir

Buka menu **Transporter** untuk melihat perusahaan yang mendaftar.

Alur:

1. Transportir mendaftar → akun berstatus **Pending**
2. Admin meninjau kelengkapan data perusahaan
3. Berikan keputusan:
   - **Setujui** → akun menjadi **Approved**, transportir dapat masuk panel
   - **Tolak** → akun menjadi **Rejected**, sertakan alasan

Setelah disetujui, transportir menerima notifikasi dan dapat mulai mengelola kendaraan.

---

## 5. Verifikasi Dokumen Kendaraan

Ini tugas **paling penting** Admin Verifikasi.

### 5.1 Di Mana Melakukannya

Buka pengajuan → bagian **Dokumen Kendaraan**, atau lewat menu **Kendaraan** → detail kendaraan → bagian Dokumen.

### 5.2 Tombol Verifikasi

Pada setiap dokumen dengan status **Menunggu Verifikasi**, tersedia:

| Tombol | Fungsi |
|---|---|
| **Verifikasi** | Menandai dokumen sah (perlu konfirmasi) |
| **Tolak** | Menolak dokumen; **wajib mengisi alasan** |
| **Pratinjau** | Membuka berkas di tab baru |
| **Unduh** | Mengunduh berkas |

### 5.3 Panduan Memeriksa Dokumen

Periksa hal berikut sebelum menyetujui:

- Jenis dokumen sesuai (STNK / KEUR / TERA)
- NOPOL pada dokumen **cocok** dengan data kendaraan
- Tanggal kedaluwarsa **belum lewat**
- Berkas terbaca jelas (tidak buram/terpotong)
- Nama pemilik/perusahaan sesuai

### 5.4 Status Dokumen

| Status | Arti |
|---|---|
| **Menunggu Verifikasi** | Belum diperiksa |
| **Terverifikasi** | Sah — dipakai sebagai syarat penjadwalan |
| **Ditolak** | Ditolak; transportir wajib unggah ulang |

> **Penting:** Seluruh dokumen wajib (STNK, KEUR, TERA) **harus Terverifikasi** sebelum jadwal pemeriksaan dapat ditetapkan. Sistem akan menolak penjadwalan dengan pesan *"Dokumen belum terverifikasi"*.

---

## 6. Menjadwalkan Pemeriksaan

### 6.1 Syarat

- Seluruh dokumen wajib **Terverifikasi**
- Status pengajuan: **Terkirim** atau **Dalam Pemeriksaan**

### 6.2 Cara Menjadwalkan

Buka halaman detail pengajuan → tombol **Tetapkan Tanggal Pemeriksaan** (atau **Reschedule Pemeriksaan** bila sudah pernah dijadwalkan).

Isi:

- **Tanggal & Waktu Pelaksanaan Pemeriksaan** (wajib)

Setelah disimpan:

- `inspection_scheduled_at` terisi, pencatat jadwal tersimpan
- Transportir & petugas menerima notifikasi
- Tombol **Isi Checklist** pada TAD baru aktif **setelah waktu tersebut tiba**

### 6.3 Menjadwalkan Ulang

Gunakan tombol **Reschedule Pemeriksaan** untuk mengubah jadwal. Kedua pihak akan kembali menerima notifikasi.

> **Catatan teknis:** Aksi penjadwalan saat ini tersedia pada **halaman detail pengajuan**. Aksi penjadwalan pada **tabel daftar pengajuan** sedang dinonaktifkan dalam kode (dikomentari).

---

## 7. Mengelola Checklist Pemeriksaan

Buka menu **Checklist Pemeriksaan**.

### 7.1 Membuat Checklist

1. Tekan **Tambah**
2. Isi **kode** dan **nama** checklist
3. Pilih **fungsi/departemen** (HSSE / Distribusi / QQ)
4. Tandai **aktif**
5. Simpan

### 7.2 Mengelola Butir (Items)

Pada halaman checklist, gunakan bagian **Items**:

| Field | Keterangan |
|---|---|
| Kode butir | Mis. `HSSE-01` (bisa otomatis) |
| Label butir | Nama pemeriksaan |
| Deskripsi | Penjelasan singkat |
| Urutan (sort order) | Urutan tampil |
| Aktif | Hanya butir aktif yang muncul saat pemeriksaan |

### 7.3 Mengelola Sub-butir (Points)

Pada setiap butir dapat ditambahkan **sub-butir** (daftar centang) beserta urutannya.

> Checklist yang **aktif** akan otomatis dipakai pada pemeriksaan berikutnya. Perubahan berlaku untuk pemeriksaan baru.

---

## 8. Menerbitkan KIM

Setelah pengajuan berstatus **Disetujui**, tombol **Terbitkan KIM** tersedia.

**Syarat:**

- Status pengajuan = **Disetujui** (semua approval fungsi + final FTM selesai)
- Dokumen wajib masih berlaku pada tanggal penerbitan

Setelah terbit: status → **KIM Terbit**, nomor KIM dibuat otomatis, transportir dinotifikasi.

### KIM Sementara (Dispensasi)

Bila pengajuan memiliki temuan berstatus **Dispensasi FTM**, KIM terbit sebagai **KIM Sementara**:

| Aspek | Keterangan |
|---|---|
| Penanda | Kartu bertuliskan "KIM — SEMENTARA" |
| Masa berlaku | Dibatasi tanggal **batas dispensasi** terawal |
| Setelah batas | Otomatis berstatus **Kedaluwarsa** |

Masa berlaku normal KIM mengikuti tanggal kedaluwarsa dokumen terawal, maksimum sesuai pengaturan sistem (default 6 bulan).

---

## 9. Status Pengajuan

| Status | Arti |
|---|---|
| Draf | Belum dikirim transportir |
| Terkirim | Menunggu penjadwalan |
| Dalam Pemeriksaan | Petugas memeriksa |
| Perlu Perbaikan | Ada temuan |
| Menunggu Persetujuan | Menunggu approval akhir FTM |
| Disetujui | Siap terbit KIM |
| Ditolak | Ditolak + alasan |
| KIM Terbit | Selesai |
| Dibatalkan | Dibatalkan |

---

## 10. Alur Kerja Admin Verifikasi (Cheat Sheet)

```
[1] Tinjau pendaftaran transportir → Setujui/Tolak
   ↓
[2] Transportir unggah STNK/KEUR/TERA
   ↓
[3] VERIFIKASI DOKUMEN (Verifikasi/Tolak)
   ├── Tolak → transportir unggah ulang
   └── Verifikasi → dokumen sah
   ↓
[4] Transportir kirim pengajuan → status: Terkirim
   ↓
[5] JADWALKAN PEMERIKSAAN (tetapkan tanggal)
   ↓
TAD mengisi checklist (setelah jadwal tiba)
   ↓
Approval fungsi → FTM final → Disetujui
   ↓
[6] TERBITKAN KIM
```

---

## 11. Pertanyaan Umum

**Tombol "Tetapkan Tanggal Pemeriksaan" tidak bisa dipakai?**

Pastikan ketiga dokumen wajib sudah **Terverifikasi**. Jika masih *Menunggu Verifikasi*, verifikasi dulu.

**Dokumen ditolak, apa yang harus dilakukan?**

Hubungi transportir dan minta unggah ulang berkas yang benar. Sertakan alasan penolakan pada sistem agar tercatat.

**Bisa mengisi checklist?**

Tidak. Pengisian checklist adalah kewenangan TAD fungsi terkait.

**Bisa memverifikasi tindak lanjut temuan?**

Tidak. Admin Verifikasi hanya dapat **melihat** temuan. Verifikasi dilakukan oleh TAD fungsi terkait, Pengawas fungsi, atau Super Admin.

**Siapa yang memberi approval?**

Approval per fungsi diberikan oleh **Pengawas** masing-masing fungsi; approval akhir oleh **FTM**.

**Kenapa KIM tidak bisa diterbitkan?**

Status pengajuan belum **Disetujui**. Tunggu seluruh approval fungsi dan approval final FTM selesai. Juga pastikan dokumen wajib belum kedaluwarsa.
