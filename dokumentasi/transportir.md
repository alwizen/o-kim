# Panduan Penggunaan O-KIM — Transportir

Panduan untuk perusahaan angkutan / pemilik mobil tangki di Fuel Terminal Tegal.

> **Aplikasi:** `https://o-kim.ft-tegal.com`
> **Panel:** `/transportir` · **Login:** akun harus berstatus **Disetujui**

---

## 1. Yang Bisa Anda Lakukan

1. Mendaftar & melengkapi profil perusahaan
2. Mengelola kendaraan dan dokumennya
3. Mengajukan KIM dan memantau statusnya
4. Menindaklanjuti temuan pemeriksaan
5. Mengajukan KIM Sementara (dispensasi)
6. Mengunduh KIM serta mencetak laporan & berita acara

---

## 2. Mendaftar Akun

1. Buka **`https://o-kim.ft-tegal.com`**.
2. Klik **Daftar** di kanan atas.
3. Isi **Nama PIC**, **Email**, **Nomor Telepon**, **Kata Sandi**, dan **Ulangi Kata Sandi** — atau klik **Daftar dengan Google**.
4. Setelah submit, Anda diarahkan ke form **Profil Perusahaan**.
5. Isi **Nama PIC**, **Nama Perusahaan**, **Alamat**, **Telepon** — lalu kirim.
6. Data terkunci setelah dikirim. Anda masuk halaman **menunggu verifikasi admin**.

> **Jangan campur jalur login:** akun Google tidak punya kata sandi, dan email yang sudah terdaftar lewat email+sandi tidak bisa memakai jalur Google.

---

## 3. Status Akun

| Status | Arti |
|---|---|
| **Menunggu Verifikasi** | Baru daftar; hanya bisa melengkapi profil lalu menunggu |
| **Disetujui** | Bisa masuk panel `/transportir` |
| **Ditolak / Ditangguhkan** | Tidak bisa masuk; hubungi admin |

---

## 4. Masuk & Keluar

1. Buka halaman utama → klik **Masuk → Transportir**, atau langsung ke **`/masuk`**.
2. Isi **Email** dan **Kata Sandi** (atau **Masuk dengan Google**), lalu klik **Masuk**.
3. Lupa sandi? Klik **Lupa kata sandi** (hanya untuk akun email+sandi).
4. Keluar lewat menu profil di pojok kanan atas.

---

## 5. Mengenal Panel

Menu yang tersedia:

| Grup | Menu | Fungsi |
|---|---|---|
| **Data & Profil** | Profil Perusahaan | Data perusahaan (dipakai di dokumen resmi) |
| | Kendaraan | Daftar mobil tangki + dokumen |
| **Operasional KIM** | Pengajuan KIM | Buat & pantau permohonan KIM |
| | Temuan & Tindak Lanjut | Kirim bukti perbaikan, ajukan dispensasi |
| | KIM Terbit | Lihat & unduh KIM |

Dashboard menampilkan ringkasan kendaraan, pengajuan berjalan, temuan, dan pengingat dokumen kedaluwarsa.

---

## 6. Mengelola Kendaraan

1. Buka **Data & Profil → Kendaraan** → **Tambah**.
2. Isi **NOPOL** (wajib, harus unik), lalu merek, tahun, karoseri, nomor rangka/mesin, dan spesifikasi teknis sesuai form.
3. Simpan, lalu buka kendaraan tersebut → bagian **Dokumen** → **Unggah Dokumen**.
4. Unggah **STNK**, **KEUR**, dan **TERA** (wajib). Format PDF/JPG/PNG, maks **5 MB** per berkas, sertakan tanggal kedaluwarsa.

**Status dokumen:** Menunggu Verifikasi → Terverifikasi / Ditolak (unggah ulang bila ditolak).

> Seluruh dokumen wajib **harus Terverifikasi** sebelum admin bisa menjadwalkan pemeriksaan.

---

## 7. Mengajukan KIM

1. Buka **Operasional KIM → Pengajuan KIM** → **Tambah**.
2. Pilih **Kendaraan** (hanya yang dokumen wajibnya Terverifikasi dan belum punya pengajuan berjalan) dan **Jenis** (Baru / Perpanjangan) → simpan sebagai **Draf**.
3. Buka detail pengajuan → klik **Kirim Pengajuan** → konfirmasi.

**Status pengajuan:** Draf → Terkirim → Dalam Pemeriksaan → (Perlu Perbaikan) → Menunggu Persetujuan → Disetujui → KIM Terbit. Status **Ditolak** disertai alasan di detail pengajuan.

---

## 8. Menindaklanjuti Temuan

1. Buka **Temuan & Tindak Lanjut** → klik temuan.
2. Periksa **deskripsi temuan** dan **bukti pemeriksaan** dari petugas.
3. Klik **Kirim Tindak Lanjut** → isi deskripsi perbaikan + unggah bukti (maks 5 berkas, 5 MB/berkas).
4. Tunggu verifikasi petugas.

**Status temuan:** Terbuka → Menunggu Verifikasi → Terverifikasi / Tindak Lanjut Ditolak (kirim ulang) / Dispensasi FTM.

> Tips: foto harus jelas memperlihatkan bagian yang diperbaiki, dan deskripsi menyebut apa yang diperbaiki.

---

## 9. Mengajukan KIM Sementara (Dispensasi)

Gunakan bila perbaikan butuh waktu (mis. suku cadang indent) sementara operasional mendesak. Keputusan sepenuhnya di FTM.

1. Buka detail temuan → klik **Ajukan Dispensasi**.
2. Isi **Alasan Permohonan** (minimal 10 karakter) → kirim.
3. Pantau statusnya di dashboard (**Permohonan KIM Sementara Saya**) atau di detail temuan.

| Status Permohonan | Arti |
|---|---|
| Menunggu FTM | Belum diputuskan |
| Disetujui | Temuan menjadi **Dispensasi FTM** |
| Ditolak | Lihat catatan FTM; boleh ajukan ulang |

> 📖 Detail alur: Baca Dan Pahami Alur KIM Sementara

---

## 10. KIM Terbit

Buka **KIM Terbit** untuk melihat nomor KIM, masa berlaku, jenis (Normal/Sementara), dan status (Aktif/Kedaluwarsa/Dicabut).

Pada detail KIM tersedia **Unduh PDF** dan **Unduh Gambar (Kartu ATM)**.

**Masa berlaku** = yang paling awal dari: kedaluwarsa dokumen wajib, batas sistem, atau batas dispensasi.

Bila KIM sementara sudah kedaluwarsa, buka **Pengajuan KIM** lalu klik **Ajukan Ulang** (jenis otomatis *Perpanjangan*).

---

## 11. Cetak Laporan & Berita Acara

Dari detail pengajuan:

| Tombol | Muncul bila |
|---|---|
| **Cetak Laporan Pemeriksaan** | Sudah ada hasil checklist |
| **Cetak Berita Acara** | Ada temuan yang sudah ditindaklanjuti & terverifikasi |

---

## 12. Alur Singkat

```
Daftar → isi profil → tunggu disetujui
   ↓
Masuk → tambah kendaraan + unggah STNK/KEUR/TERA
   ↓
Admin verifikasi dokumen
   ↓
Buat pengajuan → Kirim
   ↓
Admin jadwalkan → TAD isi checklist
   ├── tanpa temuan → lanjut approval
   └── ada temuan → kirim tindak lanjut (+ bukti)
             └── bila butuh waktu → Ajukan Dispensasi → FTM
   ↓
Approval Pengawas (HSSE ∥ Distribusi ∥ QQ) → Approval FTM
   ↓
KIM terbit → unduh PDF / gambar
```

---

## 13. Pertanyaan Umum

**Tidak bisa masuk panel?** Akun masih *Menunggu Verifikasi* — pastikan profil perusahaan sudah dikirim.

**Tombol Kirim Pengajuan tidak aktif?** Dokumen wajib belum semuanya **Terverifikasi**.

**Pemeriksaan tidak dijadwalkan?** Cek status dokumen; jika masih *Menunggu Verifikasi*, admin belum bisa menjadwalkan.

**Tindak lanjut ditolak terus?** Baca **catatan penolakan** dan unggah bukti yang lebih jelas.

**KIM cepat kedaluwarsa?** Kemungkinan **KIM Sementara** dan batas dispensasi terlewat — ajukan Perpanjangan lewat **Ajukan Ulang**.

**Bisa 2 pengajuan untuk 1 kendaraan?** Tidak; kendaraan tidak muncul di pilihan sampai pengajuan sebelumnya selesai.
