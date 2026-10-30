# Dokumentasi Penggunaan O-KIM

Dokumentasi ini dipisahkan per **role** agar setiap pengguna hanya membaca bagian yang relevan.

## Daftar Panduan

| Role | File | Ringkasan Tugas |
|---|---|---|
| **Transportir** | [transportir.md](transportir.md) | Registrasi, kelola kendaraan & dokumen, ajukan KIM, tindak lanjut temuan, unduh KIM |
| **TAD (HSSE/Distribusi/QQ)** | [tad.md](tad.md) | Isi checklist pemeriksaan, buat temuan, verifikasi tindak lanjut |
| **Pengawas (HSSE/Distribusi/QQ)** | [pengawas.md](pengawas.md) | Verifikasi temuan, approval fungsi, terbitkan KIM |
| **Admin Verifikasi** | [adminverivikasi.md](adminverivikasi.md) | Verifikasi pendaftaran & dokumen, jadwalkan pemeriksaan, kelola checklist |
| **FTM (Fuel Terminal Manager)** | [ftm.md](ftm.md) | Approval akhir, dispensasi temuan (KIM Sementara), terbitkan KIM |
| **Super Admin** | [superadmin.md](superadmin.md) | Kelola pengguna & role, master data, pengaturan sistem |

## Referensi

| Dokumen | File | Ringkasan |
|---|---|---|
| **Referensi Menu** | [menu.md](menu.md) | **Fungsi setiap menu** pada panel Admin & Transportir, beserta peta akses per role |
| **Skenario: KIM Sementara** | [kim-sementara.md](kim-sementara.md) | Alur lengkap KIM Sementara: permohonan transportir → keputusan FTM, penerbitan KIM, perpanjangan, pencabutan, gugur otomatis |

---

## Peta Alur Utama

```
Transportir registrasi (Pending)
   ↓
Admin Verifikasi menyetujui akun (Approved)
   ↓
Transportir unggah dokumen kendaraan (STNK/KEUR/TERA)
   ↓
Admin Verifikasi / FTM memverifikasi dokumen
   ↓
Transportir membuat & mengirim pengajuan
   ↓
Admin Verifikasi menjadwalkan pemeriksaan
   ↓
TAD mengisi checklist (setelah jadwal tiba)
   ├── Tidak ada temuan → lanjut approval
   └── Ada temuan → status "Perlu Perbaikan"
            ↓
       Transportir mengirim tindak lanjut + bukti
            ↓
       TAD se-fungsi / Pengawas / Super Admin memverifikasi
            ↓
       (Bila perlu) FTM memberikan DISPENSASI
            ↓
Approval fungsi HSSE + Distribusi + QQ (paralel)
   ↓
Menunggu Persetujuan
   ↓
Approval akhir FTM
   ↓
Terbitkan KIM → Normal atau KIM Sementara
```

---

## Dua Panel Aplikasi

| Panel | Alamat | Untuk |
|---|---|---|
| **Transportir** | `/transportir` | Perusahaan angkutan (external) |
| **Admin** | `/admin` | Semua petugas internal |

Akun harus berstatus **Approved** untuk dapat masuk.

---

## Konsep Penting

### Status Pengajuan

| Status | Arti |
|---|---|
| Draf | Belum dikirim |
| Terkirim | Menunggu penjadwalan pemeriksaan |
| Dalam Pemeriksaan | Petugas sedang memeriksa |
| Perlu Perbaikan | Ada temuan yang harus ditindaklanjuti |
| Menunggu Persetujuan | Menunggu approval akhir FTM |
| Disetujui | Siap diterbitkan KIM |
| Ditolak | Ditolak (alasan tertera) |
| KIM Terbit | Selesai |
| Dibatalkan | Dibatalkan |

### Status Temuan

| Status | Arti |
|---|---|
| Terbuka | Belum ditindaklanjuti transportir |
| Menunggu Verifikasi | Bukti dikirim, menunggu diperiksa |
| Terverifikasi | Selesai |
| Tindak Lanjut Ditolak | Bukti ditolak, wajib kirim ulang |
| Dispensasi FTM | Ditunda perbaikan dengan batas waktu |

### KIM Sementara (Dispensasi)

Bila transportir mengajukan permohonan dispensasi dan **FTM menyetujuinya**, KIM tetap terbit namun ditandai **KIM SEMENTARA**.

> Permohonan diajukan transportir lewat tombol **"Ajukan Dispensasi"** pada detail temuan; FTM memutuskan pada menu **Pengajuan KIM Sementara**.

| Aspek | Keterangan |
|---|---|
| Pemicu | Ada temuan berstatus Dispensasi FTM |
| Masa berlaku | Dibatasi **batas dispensasi terawal** |
| Penanda kartu | "KARTU IZIN MASUK (KIM) — SEMENTARA" |
| Setelah batas terlewati | Otomatis berstatus **Kedaluwarsa**, KIM tidak berlaku |
| Notifikasi | Transportir & petugas diberi tahu otomatis |

> KIM Sementara **bukan** pengganti kewajiban perbaikan — hanya penundaan terbatas.

📖 **Alur lengkap (pemberian, perpanjangan, pencabutan, gugur otomatis):** [kim-sementara.md](kim-sementara.md)

### Dokumen Wajib

- **STNK**, **KEUR**, **TERA** harus berstatus **Terverifikasi** sebelum jadwal pemeriksaan bisa ditetapkan.
- Masa berlaku KIM mengikuti tanggal kedaluwarsa dokumen **terawal**, maksimum sesuai pengaturan (default 6 bulan).

---

## Dashboard per Role

Setiap role melihat widget berbeda pada halaman dashboard — hanya yang relevan dengan tugasnya.

| Role | Widget |
|---|---|
| **Transportir** | Ringkasan kendaraan & KIM, Progres Pengajuan, **Tindak Lanjut Ditolak**, **Permohonan KIM Sementara Saya**, Dokumen hampir kedaluwarsa, Aktivitas Terbaru |
| **TAD** | Ringkasan tugas fungsi, **Antrean Pemeriksaan Saya**, Aktivitas Terbaru |
| **Pengawas** | Ringkasan fungsi, **Siap Di-approve Fungsi Saya**, Aktivitas Terbaru |
| **Admin Verifikasi** | Statistik operasional, **Dokumen Menunggu Verifikasi**, **Jadwal Pemeriksaan Hari Ini**, Aktivitas Terbaru |
| **FTM** | Ringkasan keputusan, **Antrean Keputusan Saya**, **Dispensasi Akan Berakhir** (≤14 hari), Grafik KIM Sementara, **Tren Pengajuan** & **Tren Temuan**, Aktivitas Terbaru |
| **Super Admin** | Statistik operasional, **Tren Pengajuan** & **Tren Temuan**, Aktivitas Terbaru |

### Filter Periode Grafik

Kedua grafik (Tren Pengajuan & Tren Temuan) punya **dropdown periode**:

| Pilihan | Jumlah Bulan |
|---|---|
| 1 Bulan | 1 |
| 3 Bulan | 3 |
| **6 Bulan** | **6 (default)** |
| 1 Tahun | 12 |

> Untuk periode 1 tahun, label sumbu diringkas menjadi nama bulan (Jan, Feb, ...) agar tetap terbaca.

---

## Dokumen PDF yang Bisa Dicetak

| Dokumen | Syarat Muncul | Isi |
|---|---|---|
| **Laporan Pemeriksaan** | Sudah ada hasil pemeriksaan | Seluruh butir checklist HSSE/Distribusi/QQ + hasil & catatan. Nama berkas berpenanda `[ON-PROSES]` atau `[DONE]` |
| **Berita Acara (BA)** | Ada temuan yang **sudah ditindaklanjuti & terverifikasi** | Halaman 1: BA (No. BA = No. Pengajuan) + tabel temuan. Halaman 2: lampiran foto temuan awal & foto verifikasi berdampingan dengan tanggal |

---

## Matriks Kewenangan

| Aktivitas | Transportir | TAD | Pengawas | Admin Verifikasi | FTM | Super Admin |
|---|:---:|:---:|:---:|:---:|:---:|:---:|
| Registrasi akun | ✅ | — | — | — | — | — |
| Kelola kendaraan & unggah dokumen | ✅ | — | — | ✅ | — | ✅ |
| Verifikasi dokumen | — | — | — | ✅ | ✅ | ✅ |
| Jadwalkan pemeriksaan | — | — | — | ✅ | — | ✅ |
| Isi checklist | — | ✅ | — | — | — | — |
| Buat temuan | — | ✅ | — | — | — | — |
| Tindak lanjut temuan (bukti) | ✅ | — | — | — | — | — |
| Verifikasi tindak lanjut | — | ✅ | ✅ | — | — | ✅ |
| Approval fungsi | — | — | ✅ | — | — | — |
| Approval akhir | — | — | — | — | ✅ | — |
| Ajukan dispensasi (KIM Sementara) | ✅ | — | — | — | — | — |
| Setujui/tolak permohonan dispensasi | — | — | — | — | ✅ | ❌ |
| Perpanjang/cabut dispensasi | — | — | — | — | ✅ | ❌ |
| Terbitkan KIM | — | — | ✅ | ✅ | ✅ | ✅ |
| Unduh KIM | ✅ | — | — | — | — | — |
| Kelola pengguna & role | — | — | — | — | — | ✅ |
| Pengaturan sistem | — | — | — | — | — | ✅ |

---

## Catatan Teknis

- **Scheduler** harus berjalan agar KIM sementara otomatis kedaluwarsa (`php artisan schedule:work` atau cron).
- Setelah mengubah permission sebuah role, pengguna perlu **login ulang** (cache permission).
- Semua berkas unggahan disimpan pada disk privat; akses hanya melalui tautan ber-otorisasi.
