# Panduan Penggunaan O-KIM — Pengawas (HSSE / Distribusi / QQ)

Panduan untuk **Pengawas** fungsi HSSE, Distribusi, dan QQ di Fuel Terminal Tegal.

> **Aplikasi:** `https://o-kim.ft-tegal.com`
> **Panel:** `/admin` · **Role:** `pengawas_hsse`, `pengawas_distribusi`, `pengawas_qq`
> **Tugas utama:** verifikasi tindak lanjut temuan, approval fungsi, penerbitan KIM.

---

## 1. Hak Akses

| Permission | Arti |
|---|---|
| `access_admin_panel` | Masuk panel `/admin` |
| `view_applications` / `view_findings` | Melihat pengajuan & temuan |
| `verify_finding_actions` | Verifikasi tindak lanjut temuan |
| `approve_tad_findings` | Menyetujui/menolak temuan buatan TAD |
| `approve_hsse` / `approve_distribusi` / `approve_qq` | Approval pengajuan fungsi terkait |
| `issue_kim` / `view_kim_cards` | Menerbitkan & melihat KIM |

Pengawas **tidak dapat**: mengisi checklist, memberi dispensasi, atau memberi approval akhir.

---

## 2. Masuk ke Panel

Buka **`https://o-kim.ft-tegal.com/admin/login`** → isi **Email** dan **Kata Sandi** → **Masuk**.

Menu: **Operasional → Pengajuan & Pemeriksaan · Temuan & Verifikasi · KIM Terbit**.

---

## 3. Memverifikasi Tindak Lanjut Temuan

1. Buka **Operasional → Temuan & Verifikasi** → pilih temuan **Menunggu Verifikasi**.
2. Periksa deskripsi temuan, bukti pemeriksaan, dan tindak lanjut transportir.
3. Pilih **Terima Tindak Lanjut** (temuan → Terverifikasi) atau **Tolak Tindak Lanjut** (wajib isi catatan).

**Anda berwenang bila:** punya `approve_tad_findings` **dan** pembuat temuan adalah user TAD pada fungsi Anda.

> Bila temuan dibuat akun non-TAD (mis. Super Admin), verifikasi dilakukan Super Admin atau TAD fungsi terkait.

---

## 4. Approval Fungsi

**Syarat:** punya `approve_<fungsi>`, status pengajuan **Dalam Pemeriksaan / Perlu Perbaikan / Menunggu Persetujuan**, checklist lengkap di semua fungsi, dan **tidak ada temuan yang menghalangi**.

1. Buka detail pengajuan → klik tombol approval fungsi Anda.
2. **Setujui** → approval tercatat; bila ketiga fungsi setuju, status → **Menunggu Persetujuan**.
3. **Tolak** → **wajib isi alasan**; status pengajuan → **Ditolak**.

Approval berjalan **paralel** antar fungsi; ketiganya harus setuju sebelum approval akhir FTM. Satu fungsi hanya boleh memberi satu approval.

```
Checklist selesai & tidak ada temuan yang menghalangi
   ↓
HSSE ∥ Distribusi ∥ QQ  →  Menunggu Persetujuan
   ↓
Approval akhir FTM  →  Disetujui  →  KIM terbit
```

---

## 5. Menerbitkan & Melihat KIM

Setelah pengajuan berstatus **Disetujui**, tombol **Terbitkan KIM** tersedia (permission `issue_kim`). Dokumen wajib harus Terverifikasi dan masih berlaku saat penerbitan.

Di menu **KIM Terbit** Anda bisa melihat detail kartu dan mengunduhnya (**Unduh PDF** / **Unduh Gambar**).

**Masa berlaku** = yang paling awal dari: kedaluwarsa dokumen wajib, batas sistem, atau batas dispensasi. Bila ada dispensasi aktif, KIM terbit sebagai **KIM Sementara** dan otomatis Kedaluwarsa saat batas lewat.

---

## 6. Pertanyaan Umum

**Tombol verifikasi tidak muncul?** Pembuat temuan bukan TAD, temuan di fungsi lain, atau Anda tidak punya `approve_tad_findings`.

**Approval ditolak "belum siap"?** Masih ada temuan terbuka atau checklist belum lengkap.

**Bisa mengisi checklist / memberi dispensasi?** Tidak — checklist milik TAD, dispensasi milik FTM.

**Bisa memberi approval akhir?** Tidak, hanya FTM (`approve_final`).

**KIM terbit langsung kedaluwarsa?** Kemungkinan KIM Sementara dengan batas dispensasi yang sudah lewat.
