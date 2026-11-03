# Panduan Penggunaan O-KIM — TAD (HSSE / Distribusi / QQ)

Panduan untuk **Tenaga Ahli Departemen (TAD)** fungsi HSSE, Distribusi, dan QQ di Fuel Terminal Tegal.

> **Aplikasi:** `https://o-kim.ft-tegal.com`
> **Panel:** `/admin` · **Role:** `hsse`, `distribusi`, `qq`
> **Tugas utama:** mengisi checklist pemeriksaan, membuat temuan, memverifikasi tindak lanjut.

---

## 1. Hak Akses

| Permission | Arti |
|---|---|
| `access_admin_panel` | Masuk panel `/admin` |
| `view_applications` | Melihat pengajuan |
| `inspect_hsse` / `inspect_distribusi` / `inspect_qq` | Mengisi checklist fungsi terkait |
| `manage_findings` | Membuat temuan |
| `view_findings` | Melihat temuan |

TAD **tidak dapat**: mengisi checklist fungsi lain, memberi approval, menjadwalkan pemeriksaan, menerbitkan KIM, atau memberi dispensasi.

---

## 2. Masuk ke Panel

1. Buka **`https://o-kim.ft-tegal.com/admin/login`**.
2. Isi **Email** dan **Kata Sandi** → **Masuk**.
3. Menu yang tampil hanya **Operasional → Pengajuan & Pemeriksaan** dan **Temuan & Verifikasi**.

> Akun dibuat Super Admin (atau daftar mandiri di `/daftar/internal` lalu disetujui). Status harus **Disetujui**.

---

## 3. Mengisi Checklist Pemeriksaan

### Syarat tombol **Isi Checklist** muncul

1. Jadwal pemeriksaan sudah ditetapkan admin
2. Waktu pemeriksaan sudah tiba
3. Status pengajuan: **Terkirim / Dalam Pemeriksaan / Perlu Perbaikan**
4. Anda punya `inspect_<fungsi>` yang sesuai

### Langkah

1. Buka **Operasional → Pengajuan & Pemeriksaan** → klik **⋮ → Isi Checklist**
   (atau dari detail pengajuan).
2. Halaman **"Pemeriksaan: [No. Pengajuan]"** hanya memuat checklist fungsi Anda.
3. Untuk setiap butir isi:
   - **Hasil penilaian**: **Sesuai** / **Temuan** / **N/A**
   - **Deskripsi Temuan** (wajib bila memilih *Temuan*)
   - **Sub-butir** (centang yang sesuai), catatan opsional
   - **Bukti pemeriksaan** (PDF/JPG/PNG, maks 5 MB, maks 10 berkas)
4. Klik **Simpan**.

> Butir yang dikosongkan tidak tersimpan dan bukan butir selesai. Butir bisa dinilai ulang selama pengajuan masih tahap pemeriksaan/perbaikan.

**Setelah disimpan:** status → **Dalam Pemeriksaan**; bila ada temuan → **Perlu Perbaikan**.

**Pengajuan siap approval bila:** seluruh butir wajib di **ketiga fungsi** sudah dinilai **dan** tidak ada temuan yang menghalangi.

---

## 4. Membuat Temuan

Temuan **otomatis dibuat** saat Anda memilih **Temuan** pada sebuah butir.

Isi deskripsi yang spesifik:

- ✅ *"APAR sisi kanan belakang kedaluwarsa (03/2026)"*
- ❌ *"APAR bermasalah"*

Sertakan **foto kondisi awal** — foto ini juga tampil di Berita Acara.

**Status temuan:** Terbuka → Menunggu Verifikasi → Terverifikasi / Tindak Lanjut Ditolak / Dispensasi FTM / Dispensasi Kedaluwarsa.

---

## 5. Memverifikasi Tindak Lanjut

1. Buka **Operasional → Temuan & Verifikasi** → pilih temuan **Menunggu Verifikasi**.
2. Periksa deskripsi temuan, bukti pemeriksaan, dan **Tindak Lanjut Transportir** (deskripsi + berkas).
3. Pilih keputusan:

| Tombol | Efek |
|---|---|
| **Terima Tindak Lanjut** | Temuan → **Terverifikasi**; bila semua temuan beres, pengajuan kembali ke *Dalam Pemeriksaan* |
| **Tolak Tindak Lanjut** | Temuan → **Tindak Lanjut Ditolak**; **wajib isi catatan** |

**Siapa boleh verifikasi:** TAD mana pun pada **fungsi yang sama** (tidak harus pembuat temuan), Pengawas fungsi (bila pembuatnya TAD), atau Super Admin.

Tulis catatan penolakan yang bisa ditindaklanjuti, mis. *"Foto tidak memperlihatkan nomor segel — unggah foto close-up segel dan APAR terpasang."*

---

## 6. Pertanyaan Umum

**Tombol Isi Checklist tidak muncul?** Cek berurutan: jadwal sudah ditetapkan → waktu sudah tiba → status pengajuan masih tahap pemeriksaan → Anda membuka fungsi yang benar.

**Tidak bisa memverifikasi temuan rekan?** Temuan harus pada fungsi yang sama dengan Anda.

**Bisa mengubah checklist yang sudah disimpan?** Bisa, selama pengajuan masih tahap pemeriksaan/perbaikan.

**Kenapa belum masuk persetujuan?** Masih ada butir wajib yang kosong atau temuan yang menghalangi.

**Bisa memberi dispensasi?** Tidak — itu kewenangan FTM.
