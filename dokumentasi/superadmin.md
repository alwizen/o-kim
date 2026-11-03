# Panduan Penggunaan O-KIM — Super Admin

Panduan untuk **Super Admin** — pengelola sistem dengan akses tertinggi di Fuel Terminal Tegal.

> **Aplikasi:** `https://o-kim.ft-tegal.com`
> **Panel:** `/admin` · **Role:** `super_admin`
> **Hak akses:** seluruh permission **kecuali** `dispensate_findings`

---

## 1. Peran & Batasan

Super Admin mengelola **sistem dan pengguna**, bukan pengambil keputusan operasional.

**Tidak dapat:**

| Batasan | Alasan |
|---|---|
| **Tidak bisa dispensasi temuan** | Keputusan bisnis: dispensasi wajib melalui FTM |
| Tidak mengisi checklist | Tugas TAD |
| Tidak memberi approval fungsi/final | Tugas Pengawas & FTM |

Super Admin **selalu boleh** memverifikasi tindak lanjut temuan (jalan terakhir bila temuan macet).

---

## 2. Masuk ke Panel

Buka **`https://o-kim.ft-tegal.com/admin/login`** → isi **Email** dan **Kata Sandi** → **Masuk**.

Bila belum ada akun Super Admin, buat lewat konsol:

```bash
php artisan okim:make-admin email@domain.com --name="Nama" --role=super_admin --password="rahasia123"
```

---

## 3. Mengelola Pengguna Internal

Buka **User Manajemen → Pengguna Internal** → **Tambah**.

| Field | Keterangan |
|---|---|
| Nama, Jabatan | Jabatan dipakai pada dokumen resmi |
| Email, Kata Sandi | Password minimal 8 karakter |
| Role | Bisa lebih dari satu |
| Status Akun | Pilih **Disetujui** agar bisa masuk |

**Pendaftaran mandiri petugas** lewat `/daftar/internal` masuk dengan status **Menunggu Verifikasi** — setujui dengan mengubah **Status Akun → Disetujui**.

**Role tersedia:** `super_admin`, `admin_verifikasi`, `hsse`, `distribusi`, `qq`, `pengawas_hsse`, `pengawas_distribusi`, `pengawas_qq`, `ftm`, `transportir`.

---

## 4. Role & Permission

Buka **User Manajemen → Role & Permission**.

**Susunan bawaan:**

| Role | Permission inti |
|---|---|
| `super_admin` | Semua **kecuali** `dispensate_findings` |
| `admin_verifikasi` | Registrasi transportir, kendaraan, verifikasi dokumen, checklist, jadwal, terbit KIM |
| `hsse` / `distribusi` / `qq` (TAD) | `view_applications`, `inspect_<fungsi>`, `manage_findings`, `view_findings` |
| `pengawas_*` | `verify_finding_actions`, `approve_tad_findings`, `approve_<fungsi>`, `issue_kim`, `view_kim_cards` |
| `ftm` | `approve_final`, `issue_kim`, `view_kim_cards`, `verify_documents`, `dispensate_findings` |

> Setelah mengubah permission, minta pengguna **login ulang** (permission di-cache).

---

## 5. Pengaturan Sistem

Buka **Master Data → Pengaturan Sistem**.

| Bagian | Isi |
|---|---|
| **Masa Berlaku KIM** | Batas maksimum (bulan, default 6) — tanggal akhir tetap diambil yang paling awal dengan kedaluwarsa dokumen wajib |
| **Penomoran KIM** | Prefix, format periode, digit urutan (+ pratinjau) |
| **Penomoran Pengajuan** | Prefix, format periode, digit urutan (+ pratinjau); nomor ini dipakai sebagai nomor Berita Acara |
| **Notifikasi** | Peringatan kedaluwarsa (hari) |

---

## 6. Notifikasi Email

Buka **User Manajemen → Notifikasi Email**.

- Event dikelompokkan: **Transportir**, **Internal**, **Akun & Keamanan**.
- Per event: pilih **role penerima** dan aktifkan/nonaktifkan **email**.
- Pengaturan ini hanya untuk email. **Notifikasi lonceng panel selalu aktif.**

---

## 7. Master Data

| Menu | Isi |
|---|---|
| **Jenis Dokumen** | Kode, nama, **wajib untuk pengajuan KIM**, **memiliki tanggal kedaluwarsa**, aktif |
| **Checklist Pemeriksaan** | Checklist, butir (items), dan sub-butir (points) per fungsi |
| **Kendaraan** | Data kendaraan + dokumen |
| **Verifikasi Transportir** | Tinjau pendaftaran perusahaan (sama seperti Admin Verifikasi) |

> Mengubah `wajib`/`kedaluwarsa` pada Jenis Dokumen berdampak langsung pada syarat penjadwalan dan masa berlaku KIM.

---

## 8. Verifikasi Temuan (Jalan Terakhir)

Gunakan bila temuan dibuat akun non-TAD atau petugas fungsi terkait tidak tersedia.

| Pihak | Kewenangan |
|---|---|
| TAD | Temuan pada fungsinya sendiri |
| Pengawas | Temuan yang dibuat user TAD pada fungsinya |
| **Super Admin** | **Selalu boleh** |

Tombol: **Terima Tindak Lanjut** / **Tolak Tindak Lanjut** (tolak wajib catatan).

---

## 9. Dispensasi Temuan

Super Admin **tidak** bisa memberi dispensasi. Kebutuhan dispensasi disampaikan ke **FTM**, dengan alur: **transportir mengajukan → FTM memutuskan** di menu **Pengajuan KIM Sementara**.

---

## 10. Perintah & Penjadwalan Server

| Perintah | Jadwal | Fungsi |
|---|---|---|
| `okim:expire-dispensations` | Harian 00:05 | Menandai KIM sementara yang lewat batas + notifikasi |
| `okim:notify-expiry` | Harian 07:00 | Pengingat dokumen & KIM akan kedaluwarsa |
| `okim:make-admin` | Manual | Membuat/memperbarui akun internal |
| `okim:test-mail {email}` | Manual | Uji kirim email |

**Wajib di produksi:**

```bash
* * * * * cd /path/to/o-kim && php artisan schedule:run >> /dev/null 2>&1
php artisan queue:work --tries=3     # worker email
```

> Tanpa queue worker, email menumpuk di tabel `jobs` dan tidak terkirim.

---

## 11. Alur Sistem End-to-End

```
Transportir daftar → kirim profil perusahaan
   ↓
Admin Verifikasi setujui akun
   ↓
Transportir unggah STNK/KEUR/TERA → Admin verifikasi dokumen
   ↓
Transportir kirim pengajuan → Admin jadwalkan pemeriksaan
   ↓
TAD isi checklist → temuan? → transportir kirim tindak lanjut → verifikasi
   ↓
(bila butuh waktu) transportir ajukan dispensasi → FTM memutuskan
   ↓
Approval fungsi (HSSE, Distribusi, QQ) → Approval akhir FTM
   ↓
Terbitkan KIM → Normal / SEMENTARA
```

**Status pengajuan:** Draf · Terkirim · Dalam Pemeriksaan · Perlu Perbaikan · Menunggu Persetujuan · Disetujui · Ditolak · KIM Terbit · Dibatalkan.

**Status KIM:** Aktif · Kedaluwarsa · Dicabut.

---

## 12. Pertanyaan Umum

**Kenapa tidak bisa dispensasi temuan?** `dispensate_findings` sengaja tidak diberikan; dispensasi harus melalui FTM.

**Perubahan permission tidak berlaku?** Minta pengguna **login ulang**.

**Pendaftar tidak muncul di antrean?** Belum mengirim profil perusahaan.

**KIM sementara tidak otomatis kedaluwarsa?** Periksa scheduler; jalankan manual `php artisan okim:expire-dispensations`.

**Email tidak terkirim?** Cek event aktif & role penerima di menu Notifikasi Email, pastikan queue worker berjalan, lalu uji dengan `php artisan okim:test-mail`.

**Mengubah masa berlaku KIM?** **Pengaturan Sistem → Masa Berlaku KIM** (tetap dibatasi kedaluwarsa dokumen & batas dispensasi).

**Di mana audit log?** Belum ada antarmukanya; jejak tersimpan di field seperti `approved_by`, `verified_by`, `dispensated_by`.
