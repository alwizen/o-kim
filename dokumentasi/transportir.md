# Panduan Penggunaan O-KIM — Transportir

Panduan ini untuk pengguna dengan role **Transportir** (perusahaan angkutan / pemilik mobil tangki).

> **Alamat panel:** `/transportir`
> **Login:** akun harus berstatus **Approved** agar bisa masuk ke panel.

---

## 1. Ringkasan Peran

Sebagai Transportir Anda dapat:

1. Mendaftarkan perusahaan (registrasi akun).
2. Mengelola data kendaraan (mobil tangki) dan dokumennya.
3. Mengajukan permohonan KIM (Kartu Izin Masuk).
4. Memantau status pengajuan.
5. Menindaklanjuti temuan hasil pemeriksaan.
6. Mengunduh KIM (PDF & gambar kartu).
7. Mencetak Laporan Pemeriksaan dan Berita Acara.

---

## 2. Registrasi & Aktivasi Akun

### 2.1 Mendaftar

1. Buka halaman registrasi transportir.
2. Isi data perusahaan dan data akun (email + kata sandi), **atau** daftar memakai akun Google.
3. Setelah mendaftar, akun berstatus **Pending** (menunggu peninjauan).

### 2.2 Status Akun

| Status | Arti | Yang Bisa Dilakukan |
|---|---|---|
| **Pending** | Menunggu peninjauan admin | Hanya melengkapi profil perusahaan (`/registrasi/profil`) |
| **Approved** | Disetujui | Dapat masuk ke panel `/transportir` |
| **Rejected** | Ditolak | Tidak bisa masuk; hubungi admin |
| **Suspended** | Dinonaktifkan | Tidak bisa masuk |

> Selama status masih **Pending**, Anda hanya diarahkan ke halaman kelengkapan profil perusahaan. Setelah admin memverifikasi, barulah panel transportir terbuka.

### 2.3 Melengkapi Profil Perusahaan

Lengkapi data perusahaan dengan benar, lalu simpan. Data ini yang dipakai pada dokumen resmi (Laporan Pemeriksaan & Berita Acara).

---

## 3. Menu di Panel Transportir

| Grup | Menu | Fungsi |
|---|---|---|
| Data & Profil | **Kendaraan** | Daftar mobil tangki + dokumen |
| Operasional KIM | **Pengajuan KIM** | Membuat & memantau permohonan KIM, cetak dokumen |
| Operasional KIM | **Temuan & Tindak Lanjut** | Kirim bukti tindak lanjut, ajukan dispensasi |
| Operasional KIM | **KIM Terbit** | Melihat & mengunduh KIM yang sudah terbit |

> 📖 **Fungsi lengkap setiap menu & peta akses per role:** [menu.md](menu.md)

---

## 4. Mengelola Kendaraan

### 4.1 Menambah Kendaraan

Buka **Kendaraan → Tambah**. Isi data:

- Nomor Polisi (NOPOL) — wajib, format dinormalisasi otomatis
- Merek / tipe kendaraan
- Nomor rangka
- Data karoseri (kapasitas tangki, jenis angkutan, dll.)
- Data teknis lain yang diminta formulir

### 4.2 Mengunggah Dokumen Kendaraan

Pada halaman kendaraan, buka bagian **Dokumen** lalu unggah:

| Dokumen | Keterangan |
|---|---|
| **STNK** | Wajib, punya tanggal kedaluwarsa |
| **KEUR** | Wajib, punya tanggal kedaluwarsa |
| **TERA** | Wajib, punya tanggal kedaluwarsa |

**Ketentuan unggah:**

- Format: **PDF, JPG, PNG**
- Ukuran maksimum: **5 MB** per berkas
- Isi tanggal kedaluwarsa sesuai dokumen asli

### 4.3 Status Verifikasi Dokumen

| Status | Arti |
|---|---|
| **Menunggu Verifikasi** | Baru diunggah, belum diperiksa admin |
| **Terverifikasi** | Disetujui admin — dokumen sah |
| **Ditolak** | Ditolak admin (ada alasan); unggah ulang perbaikan |

> **Penting:** Ketiga dokumen wajib (STNK, KEUR, TERA) **harus berstatus Terverifikasi** sebelum jadwal pemeriksaan bisa ditetapkan oleh petugas. Jika ada yang masih *Menunggu Verifikasi*, proses akan tertahan.

---

## 5. Mengajukan KIM

### 5.1 Membuat Pengajuan

Buka **Pengajuan KIM → Tambah**:

1. Pilih **kendaraan** yang akan diajukan.
2. Pilih **jenis pengajuan** (mis. Baru / Perpanjangan).
3. Simpan sebagai **Draf**.

### 5.2 Mengirim Pengajuan

Dari halaman detail pengajuan atau tabel, tekan **Kirim Pengajuan**.

**Syarat kirim:** seluruh dokumen wajib kendaraan sudah diunggah. Jika belum lengkap, pengiriman ditolak dengan pesan kesalahan.

Setelah terkirim, status berubah menjadi **Terkirim** dan menunggu admin menjadwalkan pemeriksaan.

### 5.3 Status Pengajuan

| Status | Arti |
|---|---|
| **Draf** | Belum dikirim, masih bisa diubah |
| **Terkirim** | Sudah dikirim, menunggu penjadwalan pemeriksaan |
| **Dalam Pemeriksaan** | Petugas sedang melakukan pemeriksaan checklist |
| **Perlu Perbaikan** | Ada temuan; Anda harus menindaklanjuti |
| **Menunggu Persetujuan** | Semua fungsi sudah setuju, menunggu approval akhir FTM |
| **Disetujui** | Disetujui penuh, siap diterbitkan KIM |
| **Ditolak** | Ditolak (alasan tertera pada pengajuan) |
| **KIM Terbit** | KIM sudah diterbitkan |
| **Dibatalkan** | Pengajuan dibatalkan |

---

## 6. Menindaklanjuti Temuan

Jika petugas menemukan ketidaksesuaian saat pemeriksaan, status pengajuan menjadi **Perlu Perbaikan** dan muncul daftar **temuan** pada halaman pengajuan.

### 6.1 Mengirim Tindak Lanjut

1. Buka pengajuan → bagian **Temuan**.
2. Pilih temuan, lalu tekan **Kirim Tindak Lanjut**.
3. Isi **uraian perbaikan** yang sudah dilakukan.
4. Unggah **bukti** (foto/dokumen).

**Ketentuan bukti:**

- Format: **PDF, JPG, PNG**
- Maksimum **5 berkas** per tindak lanjut
- Ukuran maksimum **5 MB** per berkas

### 6.2 Setelah Dikirim

Status temuan menjadi **Menunggu Verifikasi**. Petugas fungsi terkait akan memeriksa bukti Anda:

- **Diterima** → status temuan **Terverifikasi** (temuan ditutup)
- **Ditolak** → status temuan **Tindak Lanjut Ditolak**, disertai catatan alasan. Anda harus mengirim ulang tindak lanjut.

> Selama masih ada temuan yang belum selesai, pengajuan tidak dapat dilanjutkan ke tahap persetujuan.

### 6.3 Status Temuan

| Status | Arti |
|---|---|
| **Terbuka** | Temuan baru, belum ditindaklanjuti |
| **Menunggu Verifikasi** | Bukti sudah dikirim, menunggu diperiksa petugas |
| **Terverifikasi** | Tindak lanjut diterima, temuan selesai |
| **Tindak Lanjut Ditolak** | Bukti ditolak, wajib kirim ulang |
| **Dispensasi FTM** | Diberi penundaan perbaikan oleh FTM (lihat bagian 6.4 & 8) |

### 6.4 Mengajukan Dispensasi (KIM Sementara)

Bila temuan **tidak bisa diselesaikan segera** — misalnya harus inden suku cadang — Anda dapat memohon penundaan perbaikan kepada FTM.

**Langkah:**

1. Buka **Temuan & Tindak Lanjut** → pilih temuan.
2. Pada detail temuan, tekan tombol **"Ajukan Dispensasi"**.
3. Isi **Alasan Permohonan** (minimal 10 karakter, maks 1000). Contoh:
   > *"Perbaikan menunggu suku cadang impor, estimasi tiba dalam 2 minggu."*
4. Kirim. Permohonan berstatus **Menunggu FTM**.

**Ketentuan:**

| Aspek | Keterangan |
|---|---|
| Muncul saat | Temuan berstatus **Terbuka** atau **Tindak Lanjut Ditolak**, dan belum ada permohonan menunggu |
| Jumlah permohonan | **Satu** permohonan aktif per temuan |
| Selama menunggu | Temuan **tetap dianggap terbuka** — pengajuan belum bisa lanjut ke persetujuan |
| Bila disetujui | Temuan menjadi **Dispensasi FTM**, Anda menerima email berisi **batas perbaikan** |
| Bila ditolak | Temuan tetap **Terbuka**; Anda menerima email berisi alasannya dan **boleh mengajukan ulang** |

> Pantau status permohonan pada section **"Permohonan KIM Sementara (Dispensasi)"** di detail temuan.

---

## 7. Mengunduh KIM

Setelah KIM terbit, buka menu **KIM Terbit**. Tersedia dua tombol:

| Tombol | Hasil |
|---|---|
| **Unduh KIM** | Berkas PDF kartu KIM |
| **Unduh Gambar (Kartu ATM)** | Berkas PNG ukuran 85,6 × 54 mm |

> Untuk tombol **Unduh Gambar**, cetak dengan skala **100%** — jangan gunakan opsi *"fit to page"* agar ukuran kartu sesuai aslinya.

Anda hanya dapat melihat KIM milik perusahaan Anda sendiri.

---

## 8. KIM Sementara (Dispensasi)

Apabila FTM memberikan **dispensasi** atas temuan (perbaikan boleh ditunda), KIM tetap dapat diterbitkan namun berstatus **KIM Sementara**.

| Aspek | Keterangan |
|---|---|
| Penanda | Kartu bertuliskan **"KARTU IZIN MASUK (KIM) — SEMENTARA"** |
| Alasan | Terdapat temuan yang sedang dalam dispensasi FTM |
| Masa berlaku | Mengikuti **batas tanggal dispensasi** (bukan masa berlaku normal) |
| Setelah batas lewat | KIM otomatis berstatus **Kedaluwarsa** dan tidak berlaku |

> **Perhatian:** KIM Sementara bukan pengganti kewajiban perbaikan. Segera lakukan perbaikan sebelum tanggal batas dispensasi. Jika terlampaui, KIM tidak berlaku dan Anda akan menerima notifikasi.

> 📖 **Penjelasan lengkap alur KIM Sementara:** [kim-sementara.md](kim-sementara.md)

---

## 9. Mencetak Dokumen

Pada halaman detail pengajuan, tersedia tombol cetak berikut (muncul otomatis bila syaratnya terpenuhi):

### 9.1 Cetak Laporan Pemeriksaan

- Tombol **Cetak Laporan Pemeriksaan**
- Muncul bila sudah ada hasil pemeriksaan checklist
- Menghasilkan PDF berisi seluruh butir checklist HSSE, Distribusi, dan QQ beserta hasil dan catatannya
- Nama berkas menyertakan status kelengkapan:
  - `...[ON-PROSES].pdf` — checklist belum terisi seluruhnya
  - `...[DONE].pdf` — seluruh butir sudah dinilai

### 9.2 Cetak Berita Acara

- Tombol **Cetak Berita Acara**
- Muncul **hanya bila** sudah ada temuan yang **ditindaklanjuti dan terverifikasi**
- Menghasilkan PDF 2 halaman:
  - **Halaman 1** — Berita Acara (No. BA memakai nomor pengajuan, tabel temuan, keterangan "Sudah ditindaklanjuti")
  - **Halaman 2** — Lampiran dokumentasi: foto temuan awal & foto verifikasi tindak lanjut berdampingan, lengkap dengan tanggal masing-masing

---

## 10. Notifikasi

Anda akan menerima notifikasi (dalam aplikasi dan/atau email) pada tahap:

1. Registrasi transportir ditinjau/disetujui.
2. Dokumen kendaraan diverifikasi atau ditolak.
3. Jadwal pemeriksaan ditetapkan atau diubah.
4. Tindak lanjut temuan diterima atau ditolak.
5. Pengajuan disetujui/ditolak per fungsi.
6. Approval akhir dari FTM.
7. KIM diterbitkan.
8. Dispensasi diberikan.
9. Batas dispensasi telah terlampaui (KIM sementara tidak berlaku).

---

## 11. Alur Singkat (Cheat Sheet)

```
Registrasi → Approved
   ↓
Tambah Kendaraan + Unggah STNK/KEUR/TERA
   ↓
Admin verifikasi dokumen (harus Terverifikasi)
   ↓
Buat Pengajuan → Kirim  →  status: Terkirim
   ↓
Admin menjadwalkan pemeriksaan
   ↓
Petugas (TAD) mengisi checklist
   ↓
   ├── Tidak ada temuan → lanjut approval
   └── Ada temuan → status: Perlu Perbaikan
            ↓
       Kirim tindak lanjut + bukti
            ↓
       Petugas verifikasi (Terima / Tolak)
            ↓
Approval fungsi (HSSE, Distribusi, QQ)  →  Menunggu Persetujuan
   ↓
Approval akhir FTM  →  Disetujui
   ↓
KIM diterbitkan  →  Unduh PDF / Gambar
```

---

## 12. Pertanyaan Umum

**Tidak bisa masuk panel setelah daftar?**
Akun Anda masih **Pending**. Selesaikan profil perusahaan dan tunggu persetujuan admin.

**Tombol "Kirim Pengajuan" tidak bisa ditekan?**
Pastikan seluruh dokumen wajib (STNK, KEUR, TERA) sudah diunggah.

**Pemeriksaan tidak kunjung dijadwalkan?**
Cek status dokumen kendaraan. Jika masih *Menunggu Verifikasi*, admin belum bisa menetapkan jadwal.

**Tindak lanjut temuan saya ditolak terus?**
Perhatikan **catatan penolakan** pada temuan. Pastikan foto bukti jelas, memperlihatkan bagian yang diperbaiki, dan uraian sesuai kondisi sebenarnya.

**KIM saya berstatus Kedaluwarsa padahal baru terbit?**
Kemungkinan itu **KIM Sementara** dan batas dispensasi sudah terlewati. Segera selesaikan perbaikan dan minta petugas memverifikasi temuan.
