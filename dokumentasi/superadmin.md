# Panduan Penggunaan O-KIM — Super Admin

Panduan ini untuk pengguna dengan role **Super Admin** — pengelola sistem dengan akses tertinggi.

> **Alamat panel:** `/admin`
> **Role:** `super_admin`
> **Hak akses:** seluruh permission **kecuali** `dispensate_findings`

---

## 1. Ringkasan Peran

Super Admin mengelola **sistem dan pengguna**, bukan pengambil keputusan operasional.

Super Admin **dapat**:

- Mengelola akun internal & role
- Mengelola master data (jenis dokumen, checklist, kendaraan, transporter)
- Mengatur pengaturan aplikasi (masa berlaku KIM, format penomoran, notifikasi)
- Mengatur notifikasi email
- Mengakses seluruh fitur operasional
- Memverifikasi tindak lanjut temuan (sebagai **jalan terakhir**)

Super Admin **tidak dapat**:

- Memberi **dispensasi temuan** — ini **eksklusif FTM** (`dispensate_findings`)

---

## 2. Batasan Penting

| Batasan | Alasan |
|---|---|
| **Tidak bisa dispensasi temuan** | Keputusan bisnis: dispensasi wajib melalui FTM |
| Tidak mengisi checklist | Tugas TAD fungsi terkait |
| Tidak memberi approval fungsi/final | Tugas Pengawas & FTM |

> Meski secara teknis dapat mengakses hampir semua menu, gunakan hak tersebut sesuai peran. Penyalahgunaan akan tercatat pada jejak audit (pembuat approval, verifikator, dsb.).

---

## 3. Menu di Panel Admin

| Grup | Menu | Fungsi |
|---|---|---|
| **Operasional** | Pengajuan & Pemeriksaan | Seluruh pengajuan |
| | Temuan & Verifikasi | Seluruh temuan |
| | KIM Terbit | Seluruh KIM |
| **Master Data** | Kendaraan | Data kendaraan |
| | Jenis Dokumen | Master jenis dokumen |
| | Checklist Pemeriksaan | Master checklist |
| **User Manajemen** | Pengguna Internal | Akun petugas internal |
| | Role & Permission | Role & hak akses |
| | Verifikasi Transportir | Perusahaan transportir |
| | Notifikasi Email | Pengaturan email per event |
| | Pengaturan | Pengaturan sistem (halaman khusus) |

> 📖 **Fungsi lengkap setiap menu & peta akses per role:** [menu.md](menu.md)

---

## 4. Mengelola Pengguna Internal

Buka **User Manajemen → Pengguna Internal**.

### 4.1 Membuat Akun Petugas

1. Tekan **Tambah**
2. Isi **nama**, **email**, **kata sandi**
3. Pilih **role** (satu atau lebih)
4. Set **status akun** = **Approved**
5. Simpan

Akun hanya dapat masuk panel bila statusnya **Approved**.

### 4.2 Role yang Tersedia

| Role | Peran |
|---|---|
| `super_admin` | Pengelola sistem |
| `admin_verifikasi` | Verifikasi dokumen & jadwal |
| `hsse` | TAD HSSE — isi checklist |
| `distribusi` | TAD Distribusi — isi checklist |
| `qq` | TAD QQ — isi checklist |
| `pengawas_hsse` | Pengawas HSSE |
| `pengawas_distribusi` | Pengawas Distribusi |
| `pengawas_qq` | Pengawas QQ |
| `ftm` | Fuel Terminal Manager |
| `transportir` | Perusahaan angkutan (panel terpisah) |

### 4.3 Status Akun

| Status | Arti |
|---|---|
| **Pending** | Menunggu peninjauan (tidak bisa masuk) |
| **Approved** | Aktif (bisa masuk) |
| **Rejected** | Ditolak |
| **Suspended** | Dinonaktifkan sementara |

### 4.4 Field Jabatan

Setiap pengguna punya field **`jabatan`**. Isi dengan benar karena dipakai pada dokumen resmi.

---

## 5. Mengelola Role & Permission

Buka **User Manajemen → Role & Permission**.

Setiap role dapat diberi permission berikut:

| Permission | Arti |
|---|---|
| `access_admin_panel` | Akses panel admin |
| `manage_users` | Kelola pengguna |
| `review_transporter_registrations` | Tinjau pendaftaran transportir |
| `manage_transporters` | Kelola transporter |
| `manage_vehicles` | Kelola kendaraan |
| `manage_document_types` | Kelola jenis dokumen |
| `verify_documents` | Verifikasi dokumen kendaraan |
| `manage_checklists` | Kelola checklist pemeriksaan |
| `view_applications` | Lihat pengajuan |
| `inspect_hsse` / `inspect_distribusi` / `inspect_qq` | Isi checklist fungsi |
| `manage_findings` | Kelola temuan |
| `view_findings` | Lihat temuan |
| `verify_finding_actions` | Verifikasi tindak lanjut temuan |
| `approve_hsse` / `approve_distribusi` / `approve_qq` | Approval pengajuan fungsi |
| `approve_tad_findings` | Verifikasi temuan dari user TAD |
| `approve_final` | Approval akhir |
| `dispensate_findings` | **Dispensasi temuan (eksklusif FTM)** |
| `issue_kim` | Terbitkan KIM |
| `view_kim_cards` | Lihat kartu KIM |
| `schedule_inspection` | Jadwalkan pemeriksaan |
| `view_audit_logs` | Lihat audit log |
| `manage_settings` | Pengaturan sistem |
| `manage_notification_settings` | Pengaturan notifikasi |
| `manage_roles` | Kelola role |

> Saat mengubah permission sebuah role, pengguna dengan role tersebut perlu **login ulang** agar perubahan berlaku (cache permission).

---

## 6. Pengaturan Sistem

Buka menu **Pengaturan** (memerlukan `manage_settings`).

### 6.1 Masa Berlaku KIM

| Field | Keterangan |
|---|---|
| **Masa Berlaku (bulan)** | Batas **maksimum** masa berlaku KIM (default 6) |

> Tanggal akhir KIM diambil yang **paling awal** antara nilai ini dengan kedaluwarsa dokumen wajib. Bila ada dispensasi, dibatasi pula oleh batas dispensasi.

### 6.2 Penomoran KIM

| Field | Keterangan |
|---|---|
| **Prefix** | Awalan nomor KIM |
| **Format Periode** | Format periode pada nomor |
| **Digit Urutan** | Jumlah digit nomor urut |

Tersedia **Pratinjau Nomor KIM** untuk melihat hasil format sebelum disimpan.

### 6.3 Penomoran Pengajuan

| Field | Keterangan |
|---|---|
| **Prefix** | Awalan nomor pengajuan |
| **Format Periode** | Format periode |
| **Digit Urutan** | Jumlah digit urut |

Tersedia **Pratinjau Nomor Pengajuan**.

> Nomor pengajuan dipakai sebagai **Nomor Berita Acara (BA)**.

### 6.4 Notifikasi

| Field | Keterangan |
|---|---|
| **Peringatan Kedaluwarsa (hari)** | Rentang hari sebelum dokumen/KIM kedaluwarsa untuk memicu pengingat email harian (1–365) |

---

## 7. Pengaturan Notifikasi Email

Buka **User Manajemen → Notifikasi Email** (khusus Super Admin + `manage_notification_settings`).

Fitur:

- Daftar **event** notifikasi
- Pilih **role penerima** per event
- **Aktifkan/nonaktifkan** pengiriman email per event

> Pengaturan ini mengatur pengiriman **email**. Notifikasi dalam aplikasi tetap berjalan.

---

## 8. Master Data

### 8.1 Jenis Dokumen

Menu **Jenis Dokumen** — mengatur jenis dokumen kendaraan.

Field penting:

| Field | Keterangan |
|---|---|
| Kode | Mis. `STNK`, `KEUR`, `TERA` |
| Nama | Nama tampilan |
| **Wajib** (`is_required`) | Harus diunggah & terverifikasi sebelum penjadwalan |
| **Punya Kedaluwarsa** (`has_expiry_date`) | Memengaruhi perhitungan masa berlaku KIM |
| Aktif | Hanya yang aktif dipakai |

> Mengubah `is_required`/`has_expiry_date` berdampak langsung pada syarat penjadwalan dan masa berlaku KIM.

### 8.2 Checklist Pemeriksaan

Menu **Checklist Pemeriksaan** — kelola butir & sub-butir pemeriksaan (detail sama seperti panduan Admin Verifikasi).

### 8.3 Kendaraan

Menu **Kendaraan** — melihat/mengelola kendaraan beserta dokumennya.

### 8.4 Verifikasi Transportir

Menu **Verifikasi Transportir** — meninjau perusahaan yang mendaftar.

---

## 9. Verifikasi Temuan (Jalan Terakhir)

Super Admin **selalu boleh** memverifikasi tindak lanjut temuan. Ini disediakan sebagai pengaman agar temuan tidak pernah macet permanen.

### Kapan Digunakan

Gunakan hanya bila:

- Temuan tercatat dibuat oleh akun **non-TAD** (mis. dibuat Super Admin saat uji coba), sehingga Pengawas tidak berwenang
- Petugas fungsi terkait tidak tersedia dan penyelesaian mendesak

### Aturan Verifikasi Temuan

| Pihak | Kewenangan |
|---|---|
| TAD | Semua temuan pada **fungsinya sendiri** |
| Pengawas | Temuan yang **pembuatnya user TAD** pada fungsinya |
| **Super Admin** | **Selalu boleh** |

Tombol: **Terima Tindak Lanjut** / **Tolak Tindak Lanjut** (tolak wajib catatan).

---

## 10. Dispensasi Temuan — Tidak Tersedia

Super Admin **tidak memiliki** `dispensate_findings`. Bila ada kebutuhan dispensasi:

1. Sampaikan ke **FTM**
2. FTM memberikan dispensasi (batas waktu + alasan min 10 karakter)

### Tentang KIM Sementara

Dispensasi menghasilkan **KIM Sementara**:

| Aspek | Keterangan |
|---|---|
| Penanda | `is_dispensation = true`, kartu bertulis "SEMENTARA" |
| Masa berlaku | Dibatasi batas dispensasi terawal |
| Setelah batas | Otomatis **Kedaluwarsa** |

Terdapat perintah terjadwal `ExpireDispensationCards` yang menandai KIM sementara yang lewat batas dan mengirim notifikasi ke transportir & petugas.

---

## 11. Audit Log

Permission `view_audit_logs` tersedia pada daftar permission.

> **Catatan:** saat ini **belum ada menu/antarmuka khusus** untuk menampilkan audit log. Jejak perubahan tetap tersimpan pada data (mis. `approved_by`, `verified_by`, `dispensated_by`, `submitted_by`).

---

## 12. Perintah Terjadwal

| Perintah | Fungsi |
|---|---|
| `ExpireDispensationCards` | Menandai KIM sementara yang melewati batas dispensasi + kirim notifikasi |

Pastikan **scheduler** berjalan di server produksi (`php artisan schedule:work` atau cron) agar perintah ini berjalan otomatis.

---

## 13. Alur Sistem End-to-End

```
Transportir registrasi → Pending
   ↓
Admin Verifikasi tinjau → Approved
   ↓
Transportir unggah dokumen → Menunggu Verifikasi
   ↓
Admin/FTM verifikasi dokumen → Terverifikasi
   ↓
Transportir buat & kirim pengajuan → Terkirim
   ↓
Admin jadwalkan pemeriksaan
   ↓
TAD isi checklist (setelah jadwal tiba)
   ├── Tanpa temuan → lanjut
   └── Ada temuan → Perlu Perbaikan
            ↓
       Transportir kirim tindak lanjut + bukti
            ↓
       TAD se-fungsi / Pengawas / Super Admin verifikasi
            ↓
       (Bila perlu) FTM beri DISPENSASI
            ↓
Approval fungsi (HSSE, Distribusi, QQ) — paralel
   ↓
Menunggu Persetujuan
   ↓
Approval akhir FTM → Disetujui
   ↓
Terbitkan KIM → Normal / SEMENTARA
   ↓
KIM Terbit
```

---

## 14. Status Pengajuan & KIM

### Status Pengajuan

| Status | Arti |
|---|---|
| Draf | Belum dikirim |
| Terkirim | Menunggu penjadwalan |
| Dalam Pemeriksaan | Petugas memeriksa |
| Perlu Perbaikan | Ada temuan |
| Menunggu Persetujuan | Menunggu approval akhir FTM |
| Disetujui | Siap terbit KIM |
| Ditolak | Ditolak |
| KIM Terbit | Selesai |
| Dibatalkan | Dibatalkan |

### Status KIM

| Status | Arti |
|---|---|
| Aktif | Berlaku |
| Kedaluwarsa | Lewat masa berlaku / batas dispensasi |
| Dicabut | Dicabut |

---

## 15. Pertanyaan Umum

**Kenapa Super Admin tidak bisa dispensasi temuan?**

`dispensate_findings` sengaja tidak diberikan demi keputusan bisnis. Dispensasi harus melalui FTM.

**Perubahan permission tidak berlaku?**

Minta pengguna **login ulang**. Permission di-cache oleh sistem.

**Tombol verifikasi temuan tidak muncul?**

Pastikan temuan berstatus **Menunggu Verifikasi**. Super Admin selalu berwenang, jadi bila tetap tidak muncul kemungkinan status temuan bukan Menunggu Verifikasi.

**KIM sementara tidak otomatis kedaluwarsa?**

Periksa apakah scheduler berjalan. Jalankan `php artisan schedule:work` atau konfigurasi cron di server.

**Bagaimana mengubah masa berlaku KIM?**

Menu **Pengaturan → Masa Berlaku KIM**. Ingat: tanggal akhir tetap diambil paling awal antara nilai ini dan kedaluwarsa dokumen wajib.

**Di mana melihat audit log?**

Belum tersedia antarmukanya. Jejak tercatat di field seperti `approved_by`, `verified_by`, `dispensated_by`.
