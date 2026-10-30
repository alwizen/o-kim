# Panduan Penggunaan O-KIM — TAD (HSSE / Distribusi / QQ)

Panduan ini untuk **Tenaga Ahli Departemen (TAD)** pada fungsi **HSSE**, **Distribusi**, dan **QQ**.

> **Alamat panel:** `/admin`
> **Role:** `hsse`, `distribusi`, `qq`
> **Hak akses utama:** mengisi checklist pemeriksaan dan membuat temuan.

---

## 1. Ringkasan Peran

TAD adalah petugas yang **melakukan pemeriksaan fisik kendaraan** menggunakan checklist digital.

TAD **dapat**:

- Mengisi checklist pemeriksaan untuk fungsi yang sama
- Menilai setiap butir (Sesuai / Temuan / N/A)
- Mengunggah bukti pemeriksaan (foto/dokumen)
- Membuat temuan
- Memverifikasi tindak lanjut temuan pada fungsinya
- Mencetak laporan pemeriksaan

TAD **tidak dapat**:

- Mengisi checklist fungsi lain (TAD HSSE tidak bisa mengisi checklist Distribusi)
- Memberi approval pengajuan
- Menjadwalkan pemeriksaan
- Menerbitkan KIM
- Memberi dispensasi temuan

---

## 2. Hak Akses (Permission)

| Permission | Arti |
|---|---|
| `access_admin_panel` | Dapat masuk panel `/admin` |
| `view_applications` | Melihat daftar pengajuan |
| `inspect_hsse` / `inspect_distribusi` / `inspect_qq` | Mengisi checklist fungsi terkait |
| `manage_findings` | Membuat temuan |
| `view_findings` | Melihat daftar temuan |

> Anda **hanya** mendapat permission `inspect_*` sesuai fungsi Anda.

---

## 3. Menu di Panel Admin

| Grup | Menu | Fungsi |
|---|---|---|
| Operasional | **Pengajuan & Pemeriksaan** | Daftar pengajuan, isi checklist |
| Operasional | **Temuan & Verifikasi** | Daftar temuan & verifikasi tindak lanjut |

> 📖 **Fungsi lengkap setiap menu & peta akses per role:** [menu.md](menu.md)

---

## 4. Mengisi Checklist Pemeriksaan

### 4.1 Syarat Sebelum Memeriksa

Tombol **Isi Checklist** hanya muncul bila **semua** syarat terpenuhi:

1. **Jadwal pemeriksaan sudah ditetapkan** oleh admin.
2. **Waktu pemeriksaan sudah tiba** (atau terlewat).
3. **Status pengajuan** salah satu dari:
   - Terkirim
   - Dalam Pemeriksaan
   - Perlu Perbaikan
4. Anda memiliki permission `inspect_<fungsi>` yang sesuai.

Jika jadwal belum tiba, halaman pemeriksaan akan menolak dengan pesan:

> *"Pemeriksaan baru dapat dilakukan pada [tanggal] [jam]."*

### 4.2 Masuk ke Halaman Pemeriksaan

Ada dua cara:

- Dari tabel **Pengajuan & Pemeriksaan** → tombol **Isi Checklist**
- Dari halaman detail pengajuan → tombol **Isi Checklist Pemeriksaan**

### 4.3 Struktur Checklist

Checklist disusun bertingkat:

```
Checklist HSSE
├── HSSE-01 — Sakelar Keamanan / Safety Switch
│   ├── Sub-butir pemeriksaan (centang yang sesuai)
│   └── Hasil penilaian: Sesuai / Temuan / N/A
├── HSSE-02 — ...
└── ...
```

**Setiap butir terdiri dari:**

1. **Sub-butir pemeriksaan** — daftar centang (checkbox). Centang sub-butir yang sesuai kondisi kendaraan.
2. **Hasil penilaian** — pilihan radio:
   - **Sesuai** — kondisi memenuhi standar
   - **Temuan** — ada ketidaksesuaian (wajib isi deskripsi temuan)
   - **N/A** — tidak berlaku untuk kendaraan ini
3. **Catatan** — keterangan tambahan (opsional, kecuali diminta)
4. **Bukti pemeriksaan** — unggah foto/dokumen pendukung

### 4.4 Ketentuan Unggah Bukti

| Aspek | Ketentuan |
|---|---|
| Format | PDF, JPG, PNG |
| Ukuran maks | 5 MB per berkas |
| Jumlah maks | 10 berkas per unggahan |

> Gambar otomatis dikompresi oleh sistem agar hemat penyimpanan.

### 4.5 Menyimpan

Setelah semua butir pada fungsi Anda dinilai, simpan. Progress checklist per fungsi dapat dipantau dari halaman detail pengajuan.

---

## 5. Membuat Temuan

Temuan **dibuat otomatis** ketika Anda memilih hasil **Temuan** pada sebuah butir.

### 5.1 Yang Perlu Diisi

- **Deskripsi temuan** — uraikan ketidaksesuaian secara jelas dan spesifik
  - Contoh baik: *"APAR di sisi kanan belakang kedaluwarsa (tanggal 03/2026)"*
  - Contoh kurang baik: *"APAR bermasalah"*
- **Bukti pemeriksaan** — foto kondisi awal (sangat disarankan, karena foto ini tampil di Berita Acara)

### 5.2 Status Temuan

| Status | Arti |
|---|---|
| **Terbuka** | Temuan baru, menunggu tindak lanjut transportir |
| **Menunggu Verifikasi** | Transportir sudah mengirim bukti perbaikan |
| **Terverifikasi** | Anda menerima tindak lanjut; temuan selesai |
| **Tindak Lanjut Ditolak** | Bukti ditolak; transportir harus kirim ulang |
| **Dispensasi FTM** | FTM memberi penundaan perbaikan |

> **Catatan:** Selama masih ada temuan yang belum selesai, pengajuan **tidak bisa** masuk tahap persetujuan.

---

## 6. Memverifikasi Tindak Lanjut Temuan

Buka menu **Temuan & Verifikasi** → pilih temuan berstatus **Menunggu Verifikasi**.

### 6.1 Siapa yang Boleh Memverifikasi

| Pihak | Syarat |
|---|---|
| **TAD** | Memiliki `inspect_<fungsi>` untuk fungsi temuan tersebut |
| **Pengawas fungsi** | Memiliki `approve_tad_findings` **dan** pembuat temuan adalah user TAD |
| **Super Admin** | Selalu boleh (jalan terakhir agar temuan tidak macet) |

> **Penting:** Sejak pembaruan aturan, **semua TAD pada fungsi yang sama** dapat memverifikasi temuan fungsinya — tidak terbatas pada TAD yang membuat temuan. Ini mencegah pemeriksaan macet saat petugas pembuat tidak tersedia.
>
> TAD **tidak dapat** memverifikasi temuan fungsi lain (TAD HSSE tidak bisa memverifikasi temuan Distribusi).

### 6.2 Tombol Verifikasi

Pada halaman detail temuan, tersedia:

| Tombol | Fungsi |
|---|---|
| **Terima Tindak Lanjut** | Menutup temuan (status → Terverifikasi) |
| **Tolak Tindak Lanjut** | Menolak bukti; **wajib mengisi catatan penolakan** |

Kedua tombol hanya muncul bila temuan berstatus **Menunggu Verifikasi** dan Anda berwenang.

### 6.3 Saat Menolak

Catatan penolakan **wajib** diisi (maks 1000 karakter). Jelaskan alasannya secara jelas agar transportir dapat memperbaiki, misalnya:

> *"Foto tidak memperlihatkan bagian yang diperbaiki. Mohon unggah foto close-up beserta tanggal pengambilan."*

---

## 7. Mencetak Laporan Pemeriksaan

Pada halaman detail pengajuan tersedia tombol **Cetak Laporan Pemeriksaan** (muncul bila sudah ada hasil pemeriksaan).

Laporan PDF memuat:

- Data pengajuan (NOPOL, perusahaan, jenis, status, jadwal)
- Ringkasan pemeriksaan (jumlah butir dinilai, progres, sesuai, temuan, N/A, temuan terbuka)
- Seluruh butir checklist HSSE, Distribusi, dan QQ beserta sub-butir, hasil, catatan, dan waktu periksa

**Penanda status pada nama berkas:**

| Penanda | Arti |
|---|---|
| `[ON-PROSES]` | Checklist belum dinilai seluruhnya |
| `[DONE]` | Seluruh butir sudah dinilai |

> Laporan ini **tanpa** kolom kode butir dan **tanpa** blok tanda tangan.

---

## 8. Dispensasi Temuan

**Anda tidak dapat** memberikan dispensasi. Dispensasi hanya dapat diberikan oleh **FTM** (`dispensate_findings`).

Namun Anda perlu memahami efeknya: temuan berstatus **Dispensasi FTM** tidak menghalangi pengajuan untuk lanjut ke persetujuan, **selama** batas waktunya belum terlewati. Jika batas terlewati, temuan kembali menghalangi approval.

---

## 9. Alur Kerja TAD (Cheat Sheet)

```
Admin menjadwalkan pemeriksaan
   ↓
TAD buka "Isi Checklist" (setelah jadwal tiba)
   ↓
Untuk setiap butir:
   ├── Centang sub-butir yang sesuai
   ├── Pilih hasil: Sesuai / Temuan / N/A
   ├── (Jika Temuan) isi deskripsi + unggah foto
   └── (Opsional) isi catatan
   ↓
Simpan checklist
   ↓
Transportir menindaklanjuti temuan + kirim bukti
   ↓
   status temuan: Menunggu Verifikasi
   ↓
TAD membuka "Temuan & Verifikasi"
   ├── Terima  → Terverifikasi
   └── Tolak   → Tindak Lanjut Ditolak (wajib catatan)
   ↓
Semua fungsi selesai & tidak ada temuan terbuka
   ↓
Pengajuan lanjut ke persetujuan (Pengawas / FTM)
```

---

## 10. Pertanyaan Umum

**Tombol "Isi Checklist" tidak muncul?**

Periksa berurutan:

1. Apakah jadwal pemeriksaan sudah ditetapkan admin?
2. Apakah waktu pemeriksaan sudah tiba?
3. Apakah status pengajuan masih pada tahap pemeriksaan?
4. Apakah Anda membuka fungsi yang benar? (TAD HSSE hanya melihat tombol untuk checklist HSSE)

**Tidak bisa memverifikasi temuan rekan?**

Pastikan temuan tersebut berada pada **fungsi yang sama** dengan Anda. TAD fungsi lain tidak berwenang.

**Tombol verifikasi tidak muncul padahal temuan "Menunggu Verifikasi"?**

Periksa apakah Anda memiliki permission `inspect_<fungsi>` yang sesuai. Jika tetap tidak muncul, hubungi Super Admin.

**Bisa mengubah hasil checklist yang sudah disimpan?**

Ya — selama pengajuan masih dalam tahap pemeriksaan atau perbaikan, butir dapat dinilai ulang.

**Bisakah saya memberi dispensasi agar pengajuan cepat lanjut?**

Tidak. Dispensasi adalah kewenangan FTM. Sampaikan kebutuhan tersebut kepada FTM.
