# Panduan Penggunaan O-KIM — FTM (Fuel Terminal Manager)

Panduan untuk **Fuel Terminal Manager** di Fuel Terminal Tegal.

> **Aplikasi:** `https://o-kim.ft-tegal.com`
> **Panel:** `/admin` · **Role:** `ftm`
> **Tugas utama:** approval akhir, keputusan dispensasi (KIM Sementara), penerbitan KIM, verifikasi dokumen.

---

## 1. Hak Akses

| Permission | Arti |
|---|---|
| `access_admin_panel` | Masuk panel `/admin` |
| `view_applications` / `view_findings` | Melihat pengajuan & temuan |
| `approve_final` | **Approval akhir pengajuan** |
| `verify_documents` | Verifikasi dokumen kendaraan |
| `issue_kim` / `view_kim_cards` | Menerbitkan & melihat KIM |
| `dispensate_findings` | **Dispensasi temuan (eksklusif FTM)** |

> `dispensate_findings` hanya dimiliki FTM. Super Admin pun tidak mendapatkannya — dispensasi selalu melalui FTM.

FTM **tidak dapat**: mengisi checklist, memverifikasi tindak lanjut temuan, atau memberi approval per fungsi.

---

## 2. Masuk ke Panel

Buka **`https://o-kim.ft-tegal.com/admin/login`** → isi **Email** dan **Kata Sandi** → **Masuk**.

Dashboard menampilkan **antrean keputusan FTM**, **permohonan dispensasi menunggu**, dan **dispensasi yang akan berakhir**.

---

## 3. Approval Akhir

**Syarat:**

1. Ketiga approval fungsi (HSSE, Distribusi, QQ) sudah disetujui
2. Checklist lengkap di ketiga fungsi
3. Tidak ada temuan yang menghalangi

> **Pengecualian:** bila ada **dispensasi aktif**, approval pengawas tidak diwajibkan — cukup checklist lengkap dan tidak ada temuan yang menghalangi.

**Langkah:** buka detail pengajuan berstatus **Menunggu Persetujuan** → klik approval FTM:

- **Setujui** → status **Disetujui**
- **Tolak** → **wajib isi alasan** → status **Ditolak**

---

## 4. Keputusan Dispensasi / KIM Sementara

Dispensasi = izin menunda perbaikan temuan dengan batas waktu. **Selalu dimulai dari permohonan transportir** — tombol "Beri Dispensasi" langsung sudah tidak ada.

**Alur:** Transportir klik **Ajukan Dispensasi** (alasan ≥ 10 karakter) → masuk antrean FTM → FTM memutuskan.

**Langkah:**

1. Buka **Operasional → Pengajuan KIM Sementara**.
2. Filter **Status → Menunggu FTM**.
3. Pilih tindakan pada baris permohonan:
   - **Setujui** → isi **Batas Perbaikan** (min H+1) + catatan opsional → temuan menjadi **Dispensasi FTM**
   - **Tolak** → isi **Alasan Penolakan** (min 10 karakter) → temuan tetap **Terbuka**
   - **Terbitkan KIM Sementara** → pada permohonan yang sudah disetujui; mencatat approval FTM sekaligus menerbitkan kartu sementara

---

## 5. Aksi Dispensasi Lanjutan

Pada **detail temuan** (Operasional → Temuan & Verifikasi), untuk temuan berstatus **Dispensasi FTM**:

| Tombol | Fungsi |
|---|---|
| **Perpanjang Dispensasi** | Memperpanjang batas; kartu KIM aktif otomatis disinkronkan |
| **Ubah Alasan Dispensasi** | Mengubah teks alasan (tidak memengaruhi masa berlaku) |
| **Cabut Dispensasi** | Temuan kembali Terbuka; KIM sementara aktif langsung **Dicabut** |

**Aturan:** batas baru harus di masa depan (min H+1); dispensasi berlaku sampai akhir tanggal batas; bila beberapa temuan didispensasi, KIM memakai **batas terawal**.

---

## 6. Verifikasi Dokumen & Terbitkan KIM

- **Verifikasi dokumen**: pada **detail pengajuan → Dokumen Kendaraan**, tombol **Verifikasi** / **Tolak** (tolak wajib alasan).
- **Terbitkan KIM**: tombol muncul saat pengajuan **Disetujui**; dokumen wajib harus Terverifikasi dan masih berlaku.

**Masa berlaku** = paling awal dari: kedaluwarsa dokumen wajib, batas sistem (default 6 bulan), atau batas dispensasi. Kartu bertanda **— SEMENTARA** bila ada dispensasi.

---

## 7. Pemrosesan Otomatis

Perintah harian `okim:expire-dispensations` (00:05) menandai KIM sementara yang lewat batas, mengirim notifikasi ke transportir & petugas, dan mengubah temuan menjadi **Dispensasi Kedaluwarsa**.

---

## 8. Pertanyaan Umum

**Approval akhir ditolak sistem?** Pastikan ketiga fungsi sudah setuju (kecuali ada dispensasi aktif), checklist lengkap, dan tidak ada temuan yang menghalangi.

**Di mana tombol "Beri Dispensasi"?** Sudah dihapus; gunakan menu **Pengajuan KIM Sementara**.

**Batas dispensasi minimal?** Tanggal di masa depan (min H+1); alasan min 10 karakter.

**Bisa memverifikasi tindak lanjut temuan?** Tidak — itu TAD se-fungsi, Pengawas, atau Super Admin.

**Apa yang terjadi bila dispensasi dicabut?** KIM sementara aktif langsung Dicabut dan temuan kembali menghalangi.
