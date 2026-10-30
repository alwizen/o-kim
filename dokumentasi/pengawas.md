# Panduan Penggunaan O-KIM — Pengawas (HSSE / Distribusi / QQ)

Panduan ini untuk **Pengawas** fungsi **HSSE**, **Distribusi**, dan **QQ**.

> **Alamat panel:** `/admin`
> **Role:** `pengawas_hsse`, `pengawas_distribusi`, `pengawas_qq`
> **Hak akses utama:** verifikasi tindak lanjut temuan, approval fungsi, penerbitan KIM.

---

## 1. Ringkasan Peran

Pengawas adalah **penanggung jawab fungsi** yang mengawasi hasil pemeriksaan TAD dan memberi persetujuan atas nama fungsinya.

Pengawas **dapat**:

- Melihat seluruh pengajuan & temuan
- Memverifikasi tindak lanjut temuan (yang dibuat user TAD)
- Memberi **approval fungsi** (`approve_hsse` / `approve_distribusi` / `approve_qq`)
- Menerbitkan KIM
- Melihat kartu KIM

Pengawas **tidak dapat**:

- Mengisi checklist pemeriksaan (checklist adalah tugas TAD)
- Memberi dispensasi temuan (kewenangan FTM)
- Memberi approval akhir (kewenangan FTM)

---

## 2. Perbedaan Pengawas vs TAD

| Aspek | TAD | Pengawas |
|---|---|---|
| Isi checklist | ✅ | ❌ |
| Buat temuan | ✅ | ❌ |
| Lihat temuan | ✅ | ✅ |
| Verifikasi tindak lanjut | ✅ (semua TAD se-fungsi) | ✅ (temuan buatan TAD) |
| Approval fungsi | ❌ | ✅ |
| Terbitkan KIM | ❌ | ✅ |
| Dispensasi temuan | ❌ | ❌ |

---

## 3. Hak Akses (Permission)

| Permission | Arti |
|---|---|
| `access_admin_panel` | Masuk panel `/admin` |
| `view_applications` | Melihat daftar pengajuan |
| `view_findings` | Melihat daftar temuan |
| `verify_finding_actions` | Verifikasi tindak lanjut temuan |
| `approve_tad_findings` | Menyetujui/menolak temuan buatan TAD |
| `approve_hsse` / `approve_distribusi` / `approve_qq` | Approval pengajuan fungsi terkait |
| `issue_kim` | Menerbitkan KIM |
| `view_kim_cards` | Melihat kartu KIM |

---

## 4. Menu di Panel Admin

| Grup | Menu | Fungsi |
|---|---|---|
| Operasional | **Pengajuan & Pemeriksaan** | Daftar pengajuan + approval |
| Operasional | **Temuan & Verifikasi** | Verifikasi tindak lanjut temuan |
| Operasional | **KIM Terbit** | Daftar & detail KIM terbit |

> 📖 **Fungsi lengkap setiap menu & peta akses per role:** [menu.md](menu.md)

---

## 5. Memverifikasi Tindak Lanjut Temuan

Buka **Temuan & Verifikasi** → pilih temuan berstatus **Menunggu Verifikasi**.

### 5.1 Syarat Kewenangan

Pengawas boleh memverifikasi bila **keduanya** terpenuhi:

1. Anda memiliki permission `approve_tad_findings`
2. **Pembuat temuan adalah user TAD**

> ⚠️ Jika temuan tercatat dibuat oleh akun **non-TAD** (mis. Super Admin), Pengawas **tidak dapat** memverifikasinya. Dalam kasus ini, verifikasi dilakukan oleh **Super Admin** atau TAD fungsi terkait.

### 5.2 Tombol yang Tersedia

| Tombol | Fungsi |
|---|---|
| **Terima Tindak Lanjut** | Menutup temuan (status → Terverifikasi) |
| **Tolak Tindak Lanjut** | Menolak bukti; **wajib mengisi catatan** |

Kedua tombol hanya muncul ketika temuan berstatus **Menunggu Verifikasi**.

### 5.3 Panduan Menolak

Catatan penolakan wajib diisi. Berikan arahan yang dapat ditindaklanjuti:

> *"Bukti foto kurang jelas. Mohon unggah ulang dengan pencahayaan cukup dan sertakan foto bagian yang diperbaiki."*

---

## 6. Approval Pengajuan Fungsi

Setelah seluruh checklist fungsi selesai dan **tidak ada temuan terbuka**, pengajuan dapat disetujui per fungsi.

### 6.1 Syarat Approval Fungsi

- Anda memiliki permission `approve_<fungsi>` yang sesuai
- Status pengajuan: **Dalam Pemeriksaan**, **Perlu Perbaikan**, atau **Menunggu Persetujuan**
- Checklist **sudah lengkap** dan **tidak ada temuan yang menghalangi**

Jika syarat belum terpenuhi, sistem menolak dengan pesan:

> *"Pengajuan belum siap: checklist belum lengkap atau temuan masih terbuka."*

### 6.2 Memberi Keputusan

Dari halaman detail pengajuan, berikan keputusan:

- **Setujui** — tercatat approval fungsi Anda
- **Tolak** — **wajib mengisi alasan**; status pengajuan menjadi **Ditolak**

### 6.3 Urutan Approval

Approval antar fungsi berjalan **paralel** (tidak harus berurutan HSSE → Distribusi → QQ). Ketiganya harus menyetujui.

```
Checklist selesai & tidak ada temuan terbuka
   ↓
Approval HSSE ─┐
Approval Distribusi ─┼─→ (ketiganya harus setuju)
Approval QQ ─┘
   ↓
Status: Menunggu Persetujuan
   ↓
Approval akhir FTM
   ↓
Status: Disetujui
```

> Satu fungsi hanya boleh memberi **satu approval**. Duplikasi ditolak sistem: *"[Fungsi] sudah memberikan approval."*

---

## 7. Menerbitkan KIM

Setelah pengajuan berstatus **Disetujui**, tombol **Terbitkan KIM** tersedia bagi pemegang `issue_kim`.

**Syarat penerbitan:**

- Status pengajuan = **Disetujui**
- Seluruh approval fungsi + final FTM lengkap
- Dokumen wajib masih berlaku pada tanggal penerbitan

Setelah terbit:

- Status pengajuan → **KIM Terbit**
- Nomor KIM dibuat otomatis
- Transportir menerima notifikasi

### Masa Berlaku KIM

- Mengikuti tanggal kedaluwarsa **paling awal** dari dokumen wajib aktif
- Dibatasi maksimum sesuai pengaturan sistem (default **6 bulan**)

### KIM Sementara (Dispensasi)

Jika pengajuan memiliki temuan berstatus **Dispensasi FTM**, KIM tetap terbit namun ditandai **KIM Sementara**:

| Aspek | Keterangan |
|---|---|
| Penanda | `is_dispensation = true` |
| Masa berlaku | Dibatasi tanggal **batas dispensasi** terawal |
| Tampilan | Kartu bertuliskan "KIM — SEMENTARA" |
| Setelah batas | Otomatis dianggap **Kedaluwarsa** |

---

## 8. Melihat KIM Terbit

Menu **KIM Terbit** menampilkan daftar KIM terbit. Anda dapat:

- Melihat detail kartu (nomor KIM, masa berlaku, status, normal/sementara)
- Melihat pratinjau & mengunduh KIM

Status kartu:

| Status | Arti |
|---|---|
| **Aktif** | Berlaku |
| **Kedaluwarsa** | Melewati masa berlaku **atau** batas dispensasi |
| **Dicabut** | Dicabut oleh sistem/petugas |

---

## 9. Status Pengajuan

| Status | Arti |
|---|---|
| Draf | Belum dikirim |
| Terkirim | Menunggu penjadwalan pemeriksaan |
| Dalam Pemeriksaan | Petugas sedang memeriksa |
| Perlu Perbaikan | Ada temuan yang harus ditindaklanjuti transportir |
| Menunggu Persetujuan | Semua fungsi setuju, menunggu approval akhir FTM |
| Disetujui | Siap diterbitkan KIM |
| Ditolak | Ditolak (alasan tertera) |
| KIM Terbit | KIM sudah diterbitkan |
| Dibatalkan | Pengajuan dibatalkan |

---

## 10. Alur Kerja Pengawas (Cheat Sheet)

```
TAD mengisi checklist & membuat temuan
   ↓
Transportir kirim tindak lanjut
   ↓
[1] PENGAWAS verifikasi temuan
   ├── Terima → Terverifikasi
   └── Tolak  → Ditolak (wajib catatan)
   ↓
Semua temuan selesai (atau didispensasi FTM)
   ↓
[2] PENGAWAS beri approval fungsi
   ↓
Ketiga fungsi setuju → Menunggu Persetujuan
   ↓
FTM approval final → Disetujui
   ↓
[3] PENGAWAS terbitkan KIM (bila berwenang)
   ↓
KIM terbit (normal / sementara)
```

---

## 11. Pertanyaan Umum

**Tombol verifikasi tidak muncul padahal temuan "Menunggu Verifikasi"?**

Kemungkinan penyebab:

1. Pembuat temuan **bukan user TAD** → Pengawas memang tidak berwenang; minta Super Admin menangani.
2. Temuan berada di **fungsi lain** — pastikan Anda memverifikasi fungsi Anda sendiri.
3. Anda tidak memiliki `approve_tad_findings`.

**Approval ditolak dengan pesan "belum siap"?**

Masih ada temuan yang terbuka, atau checklist belum lengkap. Tuntaskan temuan terlebih dahulu (atau minta FTM memberi dispensasi bila memang layak).

**Bisa mengisi checklist?**

Tidak. Pengisian checklist adalah kewenangan TAD. Pengawas hanya memverifikasi dan menyetujui.

**Bisa memberi dispensasi temuan?**

Tidak. Dispensasi hanya untuk FTM (`dispensate_findings`). Super Admin pun tidak mendapatkannya secara bawaan.

**KIM terbit tapi status langsung "Kedaluwarsa"?**

Kemungkinan itu **KIM Sementara** dan batas dispensasi sudah terlewati. Koordinasikan dengan FTM untuk perpanjangan dispensasi atau penyelesaian perbaikan.
