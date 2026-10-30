# Alur KIM Sementara (Dispensasi FTM)

Dokumen ini menjelaskan **alur lengkap KIM Sementara** — kartu izin masuk yang diterbitkan ketika masih ada temuan pemeriksaan yang belum tuntas, tetapi FTM memberikan izin penundaan perbaikan.

> **Berlaku untuk role:** Transportir, TAD, Pengawas, Admin Verifikasi, FTM, Super Admin
> **Sumber kewenangan:** FTM (satu-satunya role dengan `dispensate_findings`)

---

## 1. Konsep Dasar

**KIM Sementara** diterbitkan saat ada **temuan pemeriksaan yang belum selesai diperbaiki**, namun **Fuel Terminal Manager (FTM)** memberikan **dispensasi** — izin menunda perbaikan dengan batas waktu tertentu — karena pertimbangan operasional.

Mobil tangki boleh beroperasi sementara, **tetapi masa berlakunya dikunci ke batas waktu perbaikan**, bukan masa berlaku normal.

| Aspek | Keterangan |
|---|---|
| Pemicu | Ada temuan berstatus **Dispensasi FTM** saat KIM diterbitkan |
| Penanda sistem | `kim_cards.is_dispensation = true` |
| Penentu masa berlaku | **Batas dispensasi terawal** |
| Setelah batas lewat | Otomatis **Kedaluwarsa** |

---

## 2. Diagram Alur End-to-End

```
Pemeriksaan Checklist oleh TAD (HSSE / Distribusi / QQ)
   ↓
Ada butir tidak memenuhi standar → TAD memilih "Temuan"
   ↓
Temuan berstatus: Terbuka (Open)  ← menghalangi persetujuan
   ↓
[KONDISI KHUSUS]
Perbaikan butuh waktu (mis. suku cadang indent) & operasional mendesak
   ↓
TRANSPORTIR mengajukan permohonan
   └─ Tombol "Ajukan Dispensasi" → isi Alasan (min 10 karakter)
   ↓
Permohonan berstatus PENDING
   ├─ Notifikasi email + lonceng ke FTM
   └─ Temuan TETAP Terbuka (masih menghalangi approval)
   ↓
FTM membuka menu "Pengajuan KIM Sementara"
   ├─ SETUJUI → isi Batas Perbaikan (min H+1) → temuan jadi "Dispensasi FTM"
   └─ TOLAK   → isi alasan → temuan tetap Terbuka
   ↓
Status temuan: "Dispensasi FTM"
   └─ Temuan TIDAK LAGI menghalangi approval (selama batas belum lewat)
   ↓
Approval fungsi (HSSE, Distribusi, QQ) menyetujui
   ↓
Status pengajuan: Menunggu Persetujuan
   ↓
Approval akhir FTM menyetujui → status: Disetujui
   ↓
Petugas menekan "Terbitkan KIM"
   ↓
SISTEM MENDETEKSI ADA TEMUAN DISPENSASI:
   ├─ is_dispensation        = true
   ├─ dispensation_expires_at = batas dispensasi terawal
   └─ Masa berlaku KIM dipotong ke:
      MIN(dokumen wajib terawal, batas sistem 6 bulan, BATAS DISPENSASI)
   ↓
KIM SEMENTARA TERBIT
   ├─ Kartu: "KARTU IZIN MASUK (KIM) — SEMENTARA"
   ├─ Baris tambahan: "Batas Dispensasi: dd Mmm yyyy"
   └─ Notifikasi ke transportir & petugas internal
```

---

## 3. Tahap 1 — Permohonan Dispensasi (Transportir → FTM)

Dispensasi **selalu dimulai dari permohonan transportir**. FTM tidak lagi memberi dispensasi secara langsung.

### 3.1 Alur Permohonan

```
Transportir membuka detail temuan
   ↓
Tombol "Ajukan Dispensasi" → isi Alasan Permohonan (min 10 karakter)
   ↓
Permohonan berstatus PENDING
   ├─ Notifikasi email + lonceng ke FTM
   └─ Temuan TETAP berstatus Terbuka (tetap menghalangi approval)
   ↓
FTM membuka menu "Pengajuan KIM Sementara"
   ├── Setujui → isi Batas Perbaikan → status temuan: Dispensasi FTM
   └── Tolak   → isi alasan (min 10 karakter) → temuan tetap Terbuka
```

### 3.2 Sisi Transportir

| Aspek | Keterangan |
|---|---|
| Lokasi tombol | Halaman detail temuan (panel Transportir) → header **"Ajukan Dispensasi"** |
| Field | **Alasan Permohonan** — wajib, min 10, max 1000 karakter |
| Kapan muncul | Temuan berstatus Terbuka / Ditolak **dan** belum ada permohonan menunggu keputusan |
| Pantauan | Section **"Permohonan KIM Sementara (Dispensasi)"** pada detail temuan |

**Validasi:**

| Kondisi | Pesan |
|---|---|
| Bukan pemilik temuan | *"Anda tidak memiliki akses ke temuan ini."* |
| Status pengajuan bukan pemeriksaan | *"Permohonan dispensasi hanya dapat diajukan selama tahap pemeriksaan."* |
| Status temuan bukan Terbuka/Ditolak | *"Dispensasi hanya dapat diajukan untuk temuan berstatus terbuka atau ditolak."* |
| Sudah ada permohonan pending | *"Temuan ini sudah memiliki permohonan dispensasi yang menunggu keputusan FTM."* |
| Alasan < 10 karakter | *"Alasan dispensasi wajib diisi minimal 10 karakter."* |

> Hanya **satu** permohonan aktif per temuan. Setelah ditolak, transportir boleh mengajukan ulang.

### 3.3 Sisi FTM

| Aspek | Keterangan |
|---|---|
| Menu | **Operasional → Pengajuan KIM Sementara** (dengan badge jumlah pending) |
| Aktor | Hanya role `ftm` **dan** permission `dispensate_findings` |
| Isi tabel | **Hanya permohonan berstatus Pending** |
| Tombol **Setujui** | Isi **Batas Perbaikan** (wajib, min H+1) + catatan opsional |
| Tombol **Tolak** | Isi alasan penolakan (wajib, min 10 karakter) |

Efek keputusan:

| Keputusan | Status permohonan | Status temuan |
|---|---|---|
| **Setujui** | `approved` | `dispensation` (Dispensasi FTM) + batas perbaikan tersimpan |
| **Tolak** | `rejected` | Tetap `open` (menghalangi approval) |

### 3.4 Notifikasi

| Momen | Penerima | Notifikasi |
|---|---|---|
| Transportir mengajukan | **FTM** | `DispensationRequestSubmitted` |
| FTM menyetujui | Transportir | `DispensationRequestApproved` (memuat batas perbaikan) |
| FTM menolak | Transportir | `DispensationRequestRejected` (memuat alasan) |

### 3.5 Aturan Tambahan

- Batas perbaikan **harus tanggal di masa depan** (minimal H+1).
- Temuan yang dispensasinya **sudah kedaluwarsa** tidak boleh didispensasi ulang — wajib checklist ulang.
- Setelah dispensasi aktif, FTM masih dapat **Perpanjang**, **Ubah Alasan**, atau **Cabut** lewat menu **Temuan & Verifikasi**.

---

## 4. Tahap 2 — Persetujuan Berjalan Tanpa Hambatan

Normalnya temuan terbuka memblokir persetujuan. Namun temuan berstatus `dispensation` dianggap **selesai sementara** selama batas belum terlewati.

```
Temuan Open          → blocksApproval() = true   ← menghalangi
Temuan Dispensation  → blocksApproval() = false  ← lolos (batas belum lewat)
Temuan Dispensation (batas lewat) → blocksApproval() = true ← menghalangi lagi
```

Urutan persetujuan:

1. **Approval fungsi** HSSE, Distribusi, QQ — berjalan **paralel**
2. Status pengajuan → **Menunggu Persetujuan**
3. **Approval akhir FTM** → status **Disetujui**

---

## 5. Tahap 3 — Penerbitan KIM Sementara

Saat tombol **Terbitkan KIM** ditekan, sistem:

1. Mencari batas dispensasi terawal:
   ```php
   $dispensationDueDate = $this->earliestDispensationDueDate($application);
   ```
2. Bila ditemukan, kartu KIM diberi penanda:
   - `is_dispensation = true`
   - `dispensation_expires_at` = batas dispensasi **terawal** (bila ada beberapa temuan, dipakai yang paling cepat)
3. Masa berlaku dihitung dengan rumus:

```
Masa Berlaku = MIN(
    tanggal kedaluwarsa dokumen wajib terawal,
    tanggal terbit + batas sistem (default 6 bulan),
    batas dispensasi terawal          ← selalu menang bila paling awal
)
```

4. Status kartu: **Aktif (Sementara)**

### 5.1 Tampilan Kartu

| Elemen | Isi |
|---|---|
| Judul | **KARTU IZIN MASUK (KIM) — SEMENTARA** |
| Baris tambahan | **Batas Dispensasi: dd Mmm yyyy** |
| Catatan kaki | *"KIM SEMENTARA — diterbitkan karena terdapat temuan dalam dispensasi FTM. KIM tidak berlaku setelah batas dispensasi terlampaui."* |

### 5.2 Halaman Validasi (Scan QR)

Hasil pemindaian QR menampilkan:

- Badge oranye: **"Sementara (Dispensasi)"**
- Baris **Jenis KIM** dan **Batas Dispensasi**

---

## 6. Skenario Lanjutan

### Skenario A — Perbaikan Selesai Tepat Waktu (Normal)

```
Transportir memperbaiki fisik kendaraan
   ↓
Unggah bukti tindak lanjut (foto/dokumen)
   ↓
TAD se-fungsi / Pengawas memverifikasi
   ↓
Status temuan: Terverifikasi → temuan ditutup permanen
```

> Kartu KIM yang sudah terbit tetap berjalan hingga tanggal kedaluwarsanya. Pada pengajuan berikutnya kendaraan kembali bersih tanpa dispensasi.

---

### Skenario B — FTM Memperpanjang Dispensasi

| Aspek | Detail |
|---|---|
| **Aktor** | FTM |
| **Aksi** | Detail temuan → **Perpanjang Dispensasi** |
| **Syarat** | Status masih `dispensation` **dan belum melewati batas** |
| **Efek** | Batas diperbarui; **kartu KIM aktif otomatis disinkronkan** (`syncKimCard`) |
| **Riwayat** | Tercatat dengan action `extended` |

> Dispensasi yang **sudah** kedaluwarsa tidak dapat diperpanjang — harus checklist ulang.

---

### Skenario C — Batas Waktu Terlewati (Gugur Otomatis)

Perlindungan otomatis agar mobil tangki tidak beroperasi tanpa batas.

**Penjadwalan:** setiap hari pukul **00:05** (timezone aplikasi)

```bash
php artisan okim:expire-dispensations
```

**Kriteria yang diproses:**
- `is_dispensation = true`
- `status != revoked`
- `dispensation_expires_at < hari ini`
- `dispensation_lapsed_at IS NULL` (belum pernah diproses)

**Akibat:**

| Dampak | Detail |
|---|---|
| Database | `dispensation_lapsed_at = now()` |
| Status kartu | Otomatis **Kedaluwarsa** (`currentStatus()` → Expired) |
| Validasi QR | Menampilkan status **Kedaluwarsa** |
| Notifikasi Transportir | *KIM Sementara Kedaluwarsa* — No. KIM, batas, jumlah temuan terbuka |
| Notifikasi Staff | Dikirim ke role `ftm`, `pengawas_hsse`, `pengawas_distribusi`, `pengawas_qq` |
| Approval | Temuan kembali menghalangi; wajib checklist ulang |

**Opsi aman — dry run:**

```bash
php artisan okim:expire-dispensations --dry-run
```

Menampilkan daftar kartu yang akan diproses **tanpa mengubah data**.

---

### Skenario D — FTM Mencabut Dispensasi

| Aspek | Detail |
|---|---|
| **Aktor** | FTM |
| **Aksi** | Detail temuan → **Cabut Dispensasi** |
| **Efek temuan** | Status kembali **Terbuka**; data dispensasi dikosongkan |
| **Efek kartu KIM** | KIM sementara aktif langsung **DICABUT** (`status = Revoked`) |
| **Riwayat** | Tercatat dengan action `revoked` |

---

### Skenario E — FTM Mengubah Alasan Dispensasi

| Aspek | Detail |
|---|---|
| **Aktor** | FTM |
| **Aksi** | Detail temuan → **Ubah Alasan** |
| **Syarat** | Status temuan masih `dispensation`; alasan min 10 karakter |
| **Efek** | Hanya alasan yang berubah; **kartu KIM tidak terpengaruh** |

---

## 7. Tabel Perbandingan: KIM Normal vs KIM Sementara

| Pembeda | KIM Normal | KIM Sementara |
|---|---|---|
| **Kondisi terbit** | Semua butir sesuai / temuan terverifikasi tuntas | Masih ada temuan terbuka yang didispensasi FTM |
| **Pemberi wewenang** | Alur pengajuan standar | Wajib dispensasi **FTM** |
| **Penanda sistem** | `is_dispensation = false` | `is_dispensation = true` |
| **Judul kartu** | `KARTU IZIN MASUK (KIM)` | `KARTU IZIN MASUK (KIM) — SEMENTARA` |
| **Penentu masa berlaku** | Terawal antara dokumen wajib & batas sistem (6 bulan) | **Terkunci batas dispensasi** |
| **Baris tambahan kartu** | — | **Batas Dispensasi** |
| **Tampilan scan QR** | Status Aktif | Badge **"Sementara (Dispensasi)"** + batas |
| **Jika batas lewat** | Menunggu perpanjangan normal | **Otomatis Kedaluwarsa** (cron 00:05) |
| **Jika dicabut** | — | Kartu langsung **Revoked** |

---

## 8. Kewenangan per Role

| Aktivitas | Transportir | TAD | Pengawas | Admin Verifikasi | FTM | Super Admin |
|---|:---:|:---:|:---:|:---:|:---:|:---:|
| Ajukan permohonan dispensasi | ✅ | — | — | — | — | — |
| Setujui/tolak permohonan | — | — | — | — | ✅ | ❌ |
| Beri dispensasi langsung | — | — | — | — | ❌ | ❌ |
| Perpanjang dispensasi | — | — | — | — | ✅ | ❌ |
| Ubah alasan dispensasi | — | — | — | — | ✅ | ❌ |
| Cabut dispensasi | — | — | — | — | ✅ | ❌ |
| Kirim tindak lanjut | ✅ | — | — | — | — | — |
| Verifikasi tindak lanjut | — | ✅ | ✅ | — | — | ✅ |
| Approval fungsi | — | — | ✅ | — | — | — |
| Approval akhir | — | — | — | — | ✅ | — |
| Terbitkan KIM | — | — | ✅ | ✅ | ✅ | ✅ |
| Unduh KIM | ✅ | — | — | — | — | — |

> **Catatan:** Super Admin **tidak** memiliki `dispensate_findings` (sengaja, keputusan bisnis). Namun Super Admin **selalu** dapat memverifikasi tindak lanjut temuan sebagai jalan terakhir.

---

## 9. Referensi Teknis

### 9.1 Field Database

**Tabel `findings`**
| Kolom | Keterangan |
|---|---|
| `status` | `open`, `submitted_for_verification`, `verified`, `rejected`, `dispensation` |
| `dispensation_due_date` | Batas perbaikan |
| `dispensation_reason` | Alasan dispensasi |
| `dispensated_by` | User FTM pemberi dispensasi |
| `dispensated_at` | Waktu dispensasi diberikan |

**Tabel `finding_dispensation_requests`** (permohonan dari transportir)
| Kolom | Keterangan |
|---|---|
| `finding_id` | Temuan yang dimohonkan |
| `requested_by` | User transportir pengaju |
| `reason` | Alasan permohonan |
| `status` | `pending`, `approved`, `rejected` |
| `due_date` | Batas perbaikan (diisi FTM saat menyetujui) |
| `decided_by` / `decided_at` | FTM pemutus & waktu keputusan |
| `decision_notes` | Catatan/alasan penolakan |

**Tabel `kim_cards`**
| Kolom | Keterangan |
|---|---|
| `is_dispensation` | Penanda KIM sementara |
| `dispensation_expires_at` | Batas dispensasi pada kartu |
| `dispensation_lapsed_at` | Waktu kartu ditandai gugur |
| `status` | `active`, `expired`, `revoked` |

### 9.2 Komponen Kode

| Komponen | Fungsi |
|---|---|
| `DispensationService` | `request`, `approveRequest`, `rejectRequest`, `grant`, `extend`, `updateReason`, `revoke`, `syncKimCard` |
| `DispensationRequestResource` | Menu FTM **Pengajuan KIM Sementara** (hanya pending) |
| `DispensationActions` | Tombol FTM (Perpanjang/Ubah Alasan/Cabut) — tombol *Beri* sudah dihapus |
| `FindingDispensationRequest` | Model permohonan dispensasi |
| `DispensationRequestStatus` | Enum `pending` / `approved` / `rejected` |
| `DispensationRequestSubmitted` | Notifikasi ke FTM saat permohonan diajukan |
| `DispensationRequestApproved` / `Rejected` | Notifikasi ke transportir atas keputusan FTM |
| `KimIssuanceService` | Mendeteksi dispensasi & menandai kartu |
| `KimValidityService` | Menghitung masa berlaku (termasuk batas dispensasi) |
| `KimCard::currentStatus()` | Menentukan status efektif kartu |
| `KimCard::isDispensationLapsed()` | Cek batas dispensasi terlewati |
| `ExpireDispensationCards` | Command penandaan otomatis + notifikasi |
| `Finding::blocksApproval()` | Menentukan temuan menghalangi approval atau tidak |

### 9.3 Penjadwalan

```php
// routes/console.php
Schedule::command(ExpireDispensationCards::class)
    ->dailyAt('00:05')
    ->withoutOverlapping()
    ->onOneServer();
```

> **Wajib di produksi:** pastikan scheduler berjalan
> ```bash
> php artisan schedule:work       # pengembangan
> # atau cron: * * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1
> ```

---

## 10. Pertanyaan Umum

**Siapa yang mengajukan dispensasi?**
Transportir, lewat tombol **"Ajukan Dispensasi"** pada detail temuan di panel Transportir. FTM kemudian menyetujui atau menolak di menu **Pengajuan KIM Sementara**.

**Apakah FTM masih bisa memberi dispensasi langsung?**
Tidak. Tombol "Beri Dispensasi (FTM)" sudah dihapus agar alur selalu dimulai dari permohonan transportir.

**Bisakah Super Admin memberi dispensasi?**
Tidak. `dispensate_findings` eksklusif FTM. Sampaikan kebutuhan dispensasi ke FTM.

**Berapa kali transportir boleh mengajukan?**
Satu permohonan aktif per temuan. Setelah ditolak, transportir boleh mengajukan ulang.

**Bisakah temuan yang dispensasinya sudah kedaluwarsa didispensasi ulang?**
Tidak. Sistem menolak dan meminta **checklist ulang** terlebih dahulu.

**Bagaimana bila ada beberapa temuan didispensasi dengan tanggal berbeda?**
KIM memakai **batas terawal** di antara seluruh dispensasi aktif.

**KIM sementara tidak otomatis kedaluwarsa?**
Periksa scheduler server. Jalankan manual:
```bash
php artisan okim:expire-dispensations
```

**Apakah perpanjangan memperbarui kartu yang sudah terbit?**
Ya, otomatis disinkronkan (`syncKimCard`) — `dispensation_expires_at` dan `expires_at` ikut menyesuaikan batas baru.

**Apakah mengubah alasan memengaruhi masa berlaku kartu?**
Tidak. Hanya teks alasan yang berubah.

**Apa yang terjadi pada KIM bila dispensasi dicabut?**
KIM sementara aktif langsung berstatus **Dicabut (Revoked)**.

**Apakah transportir bisa mengajukan dispensasi sendiri?**
Tidak. Dispensasi sepenuhnya kewenangan FTM. Transportir cukup mengirim tindak lanjut perbaikan.

**Setelah perbaikan selesai diverifikasi, apakah KIM berubah jadi normal?**
Tidak otomatis. Kartu yang sudah terbit tetap berjalan s/d tanggal kedaluwarsanya. Pengajuan berikutnya akan menerbitkan KIM normal.
