# Panduan Penggunaan O-KIM — FTM (Fuel Terminal Manager)

Panduan ini untuk pengguna dengan role **FTM (Fuel Terminal Manager)**.

> **Alamat panel:** `/admin`
> **Role:** `ftm`
> **Hak akses utama:** approval akhir, dispensasi temuan, penerbitan KIM.

---

## 1. Ringkasan Peran

FTM adalah **penentu keputusan akhir** dalam proses pengajuan KIM.

FTM **dapat**:

- Melihat pengajuan & temuan
- Memberi **approval akhir** (`approve_final`)
- Memberi / memperpanjang / mengubah alasan / mencabut **dispensasi temuan**
- Memverifikasi dokumen kendaraan
- Menerbitkan KIM
- Melihat kartu KIM

FTM **tidak dapat**:

- Mengisi checklist
- Memverifikasi tindak lanjut temuan
- Memberi approval per fungsi (HSSE/Distribusi/QQ)

---

## 2. Hak Akses (Permission)

| Permission | Arti |
|---|---|
| `access_admin_panel` | Masuk panel `/admin` |
| `view_applications` | Melihat pengajuan |
| `view_findings` | Melihat temuan |
| `approve_final` | **Approval akhir pengajuan** |
| `verify_documents` | Verifikasi dokumen kendaraan |
| `issue_kim` | Menerbitkan KIM |
| `view_kim_cards` | Melihat kartu KIM |
| `dispensate_findings` | **Memberi dispensasi temuan** (eksklusif FTM) |

> `dispensate_findings` **hanya** dimiliki FTM. Super Admin pun tidak mendapatkannya secara bawaan — ini keputusan bisnis agar dispensasi selalu melalui FTM.

---

## 3. Menu di Panel Admin

| Grup | Menu | Fungsi |
|---|---|---|
| Operasional | **Pengajuan & Pemeriksaan** | Approval akhir, penerbitan KIM |
| Operasional | **Temuan & Verifikasi** | Perpanjang / ubah alasan / cabut dispensasi |
| Operasional | **Pengajuan KIM Sementara** | **Antrean permohonan dispensasi dari transportir** (badge jumlah pending) |
| Operasional | **KIM Terbit** | Daftar & detail KIM |

> 📖 **Fungsi lengkap setiap menu & peta akses per role:** [menu.md](menu.md)

---

## 4. Approval Akhir

### 4.1 Syarat

Approval akhir hanya bisa diberikan bila **semua** terpenuhi:

1. Seluruh **approval fungsi** (HSSE, Distribusi, QQ) sudah **disetujui**
2. Checklist **sudah lengkap**
3. **Tidak ada temuan** yang menghalangi (temuan terbuka, atau dispensasi yang sudah lewat batas)

Jika belum, sistem menolak dengan pesan kesalahan terkait.

### 4.2 Cara Memberi Approval

Buka detail pengajuan → berikan keputusan:

- **Setujui** → status pengajuan menjadi **Disetujui**
- **Tolak** → **wajib mengisi alasan**; status menjadi **Ditolak**

Setelah FTM menyetujui, pengajuan siap diterbitkan KIM.

### 4.3 Urutan Persetujuan

```
Checklist selesai & tidak ada temuan terbuka
   ↓
Approval fungsi (HSSE ┐ Distribusi ├ QQ)  — berjalan PARALEL
   ↓
Status: Menunggu Persetujuan
   ↓
APPROVAL AKHIR FTM  ← di sini peran Anda
   ↓
Status: Disetujui
   ↓
Penerbitan KIM
```

> Approval fungsi tidak harus berurutan. Yang penting **ketiganya** menyetujui sebelum FTM dapat memberi keputusan akhir.

---

## 5. Dispensasi Temuan

Dispensasi = **izin menunda perbaikan** atas sebuah temuan, dengan batas waktu tertentu. Ini kewenangan eksklusif FTM.

### 5.1 Kapan Dispensasi Diberikan

Pertimbangkan bila:

- Temuan bersifat **minor** dan tidak membahayakan
- Perbaikan butuh waktu (mis. suku cadang indent)
- Operasional perlu tetap berjalan sementara perbaikan dijadwalkan

### 5.2 Tombol Dispensasi

Pada halaman detail temuan (**Temuan & Verifikasi**), tersedia:

| Tombol | Fungsi |
|---|---|
| **Perpanjang Dispensasi** | Memperpanjang batas waktu |
| **Ubah Alasan** | Memperbarui alasan dispensasi |
| **Cabut Dispensasi** | Mencabut dispensasi |

> ⚠️ Tombol **"Beri Dispensasi" langsung sudah dihapus.** Dispensasi baru selalu dimulai dari **permohonan transportir** melalui menu **Pengajuan KIM Sementara**.

### 5.3 Menyetujui Permohonan Dispensasi

Transportir mengajukan permohonan → masuk ke menu **Operasional → Pengajuan KIM Sementara**.

| Aspek | Keterangan |
|---|---|
| Isi menu | **Hanya permohonan berstatus Pending** |
| Badge navigasi | Menampilkan jumlah permohonan yang menunggu |
| Tombol **Setujui** | Isi **Batas Perbaikan** (wajib, min H+1) + catatan opsional |
| Tombol **Tolak** | Isi **alasan penolakan** (wajib, min 10 karakter) |

**Efek keputusan:**

| Keputusan | Status permohonan | Status temuan |
|---|---|---|
| **Setujui** | `approved` | **Dispensasi FTM** + batas perbaikan tersimpan |
| **Tolak** | `rejected` | Tetap **Terbuka** (menghalangi approval) |

**Notifikasi otomatis:**

| Momen | Penerima |
|---|---|
| Transportir mengajukan | **FTM** (email + lonceng) |
| FTM menyetujui | Transportir (memuat batas perbaikan) |
| FTM menolak | Transportir (memuat alasan) |

> Bila permohonan ditolak, transportir **boleh mengajukan ulang**.

### 5.4 Efek Dispensasi

1. **Status temuan** → **Dispensasi FTM**
2. Temuan **tidak menghalangi** approval selama batas belum terlewati
3. **KIM dapat diterbitkan** tetapi berstatus **KIM Sementara**
4. Bila beberapa temuan didispensasi, yang dipakai adalah **batas tanggal terawal**

> 📖 **Alur lengkap KIM Sementara** (penerbitan, perpanjangan, pencabutan, gugur otomatis): [kim-sementara.md](kim-sementara.md)

### 5.5 Aturan Batas Waktu

| Kondisi | Akibat |
|---|---|
| Batas **belum** terlewati | Temuan dianggap selesai; pengajuan dapat lanjut |
| Batas **sudah** terlewati | Temuan kembali **menghalangi** approval |

> Perhitungan memakai tanggal hari ini: dispensasi berlaku **sampai akhir tanggal batas**.

### 5.6 Mencabut Dispensasi

Jika dicabut dan KIM sementara sudah terbit, KIM sementara tersebut ikut **dicabut**. Temuan kembali menghalangi approval.

### 5.7 Status Temuan

| Status | Arti |
|---|---|
| **Terbuka** | Belum ditindaklanjuti transportir |
| **Menunggu Verifikasi** | Bukti dikirim, menunggu TAD/Pengawas |
| **Terverifikasi** | Selesai |
| **Tindak Lanjut Ditolak** | Bukti ditolak; kirim ulang |
| **Dispensasi FTM** | Ditunda dengan batas waktu |

---

## 6. KIM Sementara (Dispensasi)

Ini konsekuensi penting dari dispensasi yang perlu Anda pahami.

| Aspek | KIM Normal | KIM Sementara |
|---|---|---|
| Pemicu | Tidak ada dispensasi aktif | Ada temuan dispensasi |
| Penanda sistem | `is_dispensation = false` | `is_dispensation = true` |
| Tampilan kartu | "KARTU IZIN MASUK (KIM)" | "KARTU IZIN MASUK (KIM) — SEMENTARA" |
| Masa berlaku | Dokumen terawal, maks 6 bulan (default) | Dibatasi **batas dispensasi terawal** |
| Setelah batas | Sesuai masa berlaku | Otomatis **Kedaluwarsa** |

### 6.1 Keterangan pada Kartu

Kartu KIM sementara memuat keterangan:

> *"KIM SEMENTARA — diterbitkan karena terdapat temuan dalam dispensasi FTM. KIM tidak berlaku setelah batas dispensasi terlampaui."*

### 6.2 Pemrosesan Otomatis

Terdapat perintah terjadwal (`ExpireDispensationCards`) yang:

- Menandai `dispensation_lapsed_at` pada KIM sementara yang batasnya sudah lewat
- Mengirim **notifikasi ke transportir** (batas dispensasi terlampaui)
- Mengirim **notifikasi ke petugas internal** terkait

### 6.3 Tindakan Anda

Sebelum batas berakhir:

- **Perpanjang dispensasi** bila perbaikan belum selesai, **atau**
- Pastikan temuan sudah diverifikasi TAD/Pengawas

Jika terlanjur lewat, pengajuan tertahan dan perlu penerbitan ulang setelah persyaratan dipenuhi.

---

## 7. Verifikasi Dokumen

FTM juga memegang `verify_documents`. Tombol **Verifikasi** dan **Tolak** tersedia pada dokumen kendaraan yang masih **Menunggu Verifikasi** — sama seperti Admin Verifikasi.

**Ketentuan:**

- Format: PDF, JPG, PNG
- Maks 5 MB per berkas
- Dokumen wajib (STNK, KEUR, TERA) harus terverifikasi agar pemeriksaan dapat dijadwalkan

---

## 8. Menerbitkan KIM

Setelah pengajuan berstatus **Disetujui**, tombol **Terbitkan KIM** tersedia.

**Syarat:**

- Status pengajuan = **Disetujui**
- Dokumen wajib masih berlaku pada tanggal penerbitan

**Masa berlaku:**

- Mengikuti tanggal kedaluwarsa **dokumen wajib terawal**
- Dibatasi maksimum sesuai pengaturan sistem (default **6 bulan**)
- Jika ada dispensasi, dibatasi **batas dispensasi terawal**

Setelah terbit: status → **KIM Terbit**, nomor KIM otomatis, transportir dinotifikasi.

---

## 9. Status Pengajuan

| Status | Arti |
|---|---|
| Draf | Belum dikirim |
| Terkirim | Menunggu penjadwalan |
| Dalam Pemeriksaan | Petugas memeriksa |
| Perlu Perbaikan | Ada temuan |
| **Menunggu Persetujuan** | **Menunggu approval akhir Anda** |
| Disetujui | Siap terbit KIM |
| Ditolak | Ditolak + alasan |
| KIM Terbit | Selesai |
| Dibatalkan | Dibatalkan |

---

## 10. Alur Kerja FTM (Cheat Sheet)

```
[OPSIONAL] Verifikasi dokumen kendaraan
   ↓
TAD mengisi checklist → temuan? → transportir tindak lanjut → verifikasi
   ↓
Approval fungsi (HSSE, Distribusi, QQ) selesai
   ↓
Status: Menunggu Persetujuan
   ↓
[1] APPROVAL AKHIR FTM
   ├── Setujui → Disetujui
   └── Tolak   → Ditolak (wajib alasan)
   ↓
[2] Ada permohonan dispensasi dari transportir?
      Menu "Pengajuan KIM Sementara"
      ├── Setujui (isi batas perbaikan) → temuan: Dispensasi FTM
      └── Tolak (isi alasan)            → temuan tetap Terbuka
   ↓
[3] TERBITKAN KIM → normal / SEMENTARA (bila ada dispensasi)
```

---

## 11. Pertanyaan Umum

**Approval akhir ditolak sistem?**

Periksa: (a) ketiga fungsi sudah menyetujui? (b) checklist lengkap? (c) masih ada temuan terbuka atau dispensasi yang lewat batas?

**Bisa memverifikasi tindak lanjut temuan?**

Tidak. FTM tidak memiliki `approve_tad_findings`. Verifikasi dilakukan TAD se-fungsi, Pengawas fungsi, atau Super Admin.

**Bisa memberi approval per fungsi?**

Tidak. FTM hanya punya `approve_final`, bukan `approve_hsse` / `approve_distribusi` / `approve_qq`.

**KIM terbit tapi langsung kedaluwarsa?**

Kemungkinan **KIM Sementara** dengan batas dispensasi sudah lewat. Perpanjang dispensasi atau selesaikan perbaikan lebih dulu.

**Batas dispensasi minimal kapan?**

Harus **tanggal di masa depan**. Alasan wajib minimal **10 karakter**.

**Kenapa Super Admin tidak bisa memberi dispensasi?**

Karena `dispensate_findings` sengaja tidak diberikan ke Super Admin (keputusan bisnis). Dispensasi harus melalui FTM.

**Apa yang terjadi jika beberapa temuan didispensasi dengan tanggal berbeda?**

KIM memakai batas **terawal** di antara seluruh dispensasi aktif.
