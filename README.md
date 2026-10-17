<div align="center">

<img src="public/img/logo.png" alt="Logo O-KIM" width="240">

# O-KIM — Online Kartu Izin Masuk

**Sistem digital pengelolaan Kartu Izin Masuk (KIM) kendaraan tangki — Fuel Terminal Tegal**

Dokumentasi & Panduan Penggunaan

</div>

---

## Daftar Isi

1. [Tentang Aplikasi](#1-tentang-aplikasi)
2. [Fitur Utama](#2-fitur-utama)
3. [Peran Pengguna](#3-peran-pengguna)
4. [Alur Bisnis](#4-alur-bisnis)
5. [Teknologi](#5-teknologi)
6. [Persyaratan Sistem](#6-persyaratan-sistem)
7. [Instalasi](#7-instalasi)
8. [Konfigurasi](#8-konfigurasi)
9. [Menjalankan Aplikasi](#9-menjalankan-aplikasi)
10. [Akun & Akses Panel](#10-akun--akses-panel)
11. [Panduan Penggunaan](#11-panduan-penggunaan)
12. [Struktur Proyek](#12-struktur-proyek)
13. [Pengujian](#13-pengujian)
14. [Deployment Produksi](#14-deployment-produksi)
15. [Status & Catatan Penting](#15-status--catatan-penting)

---

## 1. Tentang Aplikasi

**O-KIM** (Online Kartu Izin Masuk) adalah aplikasi web internal untuk mengelola proses pendaftaran
transportir, pendataan kendaraan tangki, pemeriksaan persyaratan, tindak lanjut temuan, persetujuan
petugas, hingga penerbitan **Kartu Izin Masuk (KIM)** di Fuel Terminal Tegal.

Sistem menggantikan proses manual dengan workflow digital yang terstruktur, dapat ditelusuri
(audit trail), dan memiliki pembatasan akses berbasis peran.

Aplikasi terdiri dari tiga area:

| Area | Prefix URL | Pengguna |
|---|---|---|
| **Public website** | `/` | Pengunjung, calon transportir |
| **Panel Admin** | `/admin` | Petugas internal (Super Admin, verifikator, HSSE, Distribusi, QQ, FTM) |
| **Panel Transportir** | `/transportir` | Perusahaan transportir yang sudah disetujui |

---

## 2. Fitur Utama

### Publik
- Landing page informatif (layanan, persyaratan, panduan, FAQ)
- Pendaftaran transportir via **Google OAuth** atau **email + kata sandi**
- Halaman **validasi KIM** publik melalui QR Code

### Transportir
- Profil perusahaan
- Manajemen kendaraan tangki (NOPOL dinormalisasi)
- Unggah dokumen kendaraan (STNK, KEUR, TERA, dll.) dengan **riwayat versi** dan **verifikasi oleh petugas**
- Pengajuan KIM baru & perpanjangan
- Melihat hasil pemeriksaan dan temuan
- Menindaklanjuti temuan dengan bukti
- Mengunduh KIM terbit (PDF + QR)

### Admin
- **Dashboard** dengan widget statistik & tabel peringatan dokumen
- Verifikasi & approval pendaftaran transportir
- **Pengguna Internal** — kelola akun HSE, Distribusi, QQ, FTM, admin (hanya super admin)
- Master jenis dokumen
- Daftar seluruh kendaraan lintas perusahaan
- Pemeriksaan berjenjang (checklist HSE, Distribusi, QQ)
- Manajemen & verifikasi temuan
- Approval berjenjang (HSE, Distribusi, QQ, dan final FTM)
- Penerbitan KIM digital (nomor unik + QR Code)

### Dashboard (Widget)
Kedua panel memiliki dashboard yang **berbeda** sesuai kebutuhannya.

**Panel Admin** — `app/Filament/Widgets/`
| Widget | Isi |
|---|---|
| `AdminStatsOverview` | Pendaftaran menunggu verifikasi · pengajuan dalam pemeriksaan · temuan terbuka · menunggu approval · KIM aktif · dokumen mendekati kedaluwarsa |
| `ExpiringDocumentsTable` | Tabel peringatan dokumen **lintas perusahaan**: perusahaan, NOPOL, jenis dokumen, tanggal kedaluwarsa, sisa waktu, status verifikasi |

**Panel Transportir** — `app/Filament/Transportir/Widgets/`
| Widget | Isi |
|---|---|
| `TransportirStatsOverview` | Total kendaraan · pengajuan berjalan · temuan perlu tindak lanjut · KIM aktif · KIM mendekati kedaluwarsa · dokumen perlu diperbarui |
| `VehicleProgressStepper` | **Stepper horizontal** progres pengajuan per kendaraan: Pendaftaran Kendaraan → Verifikasi Dokumen → Checklist Pemeriksaan → Temuan → KIM Terbit. Dropdown hanya memuat kendaraan yang **sudah memiliki pengajuan**; warna tiap tahap menunjukkan status (selesai/berjalan/perlu tindak lanjut/gagal/menunggu) |
| `TransportirExpiringDocumentsTable` | Tabel peringatan dokumen **milik perusahaannya sendiri**, dengan tombol **Perbarui** menuju halaman kendaraan |

Semua angka berasal dari query database nyata, dan setiap widget menyaring data sesuai
perusahaan pengguna (isolasi data). Ambang peringatan kedaluwarsa dokumen diatur pada
`DocumentExpiryService::WARNING_THRESHOLD_DAYS` (default **60 hari**; batas "kritis" 14 hari,
"peringatan" 30 hari).

Perhitungan tahapan stepper dipusatkan di `App\Services\Application\VehicleProgressService`
(lima langkah dengan state `done`/`active`/`warning`/`failed`/`pending`), sehingga aturan progres
tidak bergantung pada tampilan.

---

## 3. Peran Pengguna

| Peran | Kewenangan Utama |
|---|---|
| **Super Admin** | Administrasi penuh, kelola pengguna, role, master data, audit log |
| **Admin Verifikasi** | Memeriksa, menyetujui, atau menolak pendaftaran transportir |
| **HSSE** | Checklist HSSE, buat/verifikasi temuan, approval HSSE |
| **Distribusi** | Checklist Distribusi, buat/verifikasi temuan, approval Distribusi |
| **QQ** | Checklist QQ, buat/verifikasi temuan, approval QQ |
| **Fuel Terminal Manager (FTM)** | Approval final setelah 3 fungsi menyetujui, lihat KIM terbit |
| **Transportir** | Kelola perusahaan, kendaraan, dokumen, pengajuan, tindak lanjut |

> Aturan keamanan: transportir **hanya** dapat mengakses data milik perusahaannya. Isolasi data
> ditegakkan di backend (Policy + query scope), bukan hanya penyembunyian menu.

### Matriks Akses Menu Panel Admin

| Menu | super_admin | admin_verifikasi | HSE | Distribusi | QQ | FTM |
|---|:---:|:---:|:---:|:---:|:---:|:---:|
| Dashboard | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Verifikasi Transportir | ✅ | ✅ | — | — | — | — |
| Pengguna Internal | ✅ | — | — | — | — | — |
| Kendaraan | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Jenis Dokumen | ✅ | — | — | — | — | — |
| Checklist Pemeriksaan | ✅ | — | — | — | — | — |
| Pengajuan & Pemeriksaan | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| Temuan & Verifikasi | ✅ | — | ✅ | ✅ | ✅ | 👁 lihat saja |
| KIM Terbit | ✅ | — | — | — | — | ✅ |
| Pengaturan Sistem | ✅ | — | — | — | — | — |

Catatan:
- **Isi checklist dibatasi per fungsi** — petugas HSE hanya melihat & mengisi butir HSE, Distribusi
  hanya butir Distribusi, dst. Ditegakkan di `InspectionService` (backend), bukan hanya tampilan.
- **Data pengajuan** (NOPOL, perusahaan, status) terlihat oleh semua fungsi pemeriksa; yang dibatasi
  adalah bagian checklist dan akses tulis.
- **Akses tulis** hanya untuk fungsi sendiri (HSE tidak bisa mengubah hasil Distribusi).
- **Pembatasan berlapis:** `canAccessPanel()` (status akun + permission) → `canAccess()` per Resource →
  **Policy** (otorisasi per-record) → `->visible()` pada setiap aksi sensitif.

### Policy (otorisasi per-record)
| Policy | Cakupan |
|---|---|
| `UserPolicy` | Pengguna internal; **delete selalu ditolak** (gunakan Nonaktifkan) |
| `KimApplicationPolicy` | `view` (semua fungsi / pemilik), `inspect` & `approve` **per fungsi** |
| `KimCardPolicy` | KIM milik perusahaan sendiri atau permission `view_kim_cards` |
| `VehiclePolicy` / `VehicleDocumentPolicy` | Isolasi data per perusahaan transportir |
| `DocumentTypePolicy` / `InspectionChecklistPolicy` | Sesuai permission pengelolaan masing-masing |

> **Catatan untuk pengembangan berikutnya:** Filament mengizinkan akses bila Resource tidak punya
> `canAccess()` **dan** tidak ada Policy (default `allow`). Setiap Resource baru **wajib** menambahkan
> `canAccess()` atau Policy-nya.

### Mengelola pengguna internal
Menu **Pengguna Internal** (hanya super admin) untuk membuat akun HSE, Distribusi, QQ, FTM, dan
admin verifikasi — lengkap dengan pemilihan role dan status. Akun **tidak dihapus permanen**;
gunakan tombol **Nonaktifkan** (status `suspended`). Sistem mencegah menonaktifkan akun sendiri
dan menonaktifkan Super Admin aktif terakhir.

---

## 4. Alur Bisnis

### 4.1 Registrasi Transportir
1. Pengunjung mendaftar via Google atau email + kata sandi.
2. Akun dibuat dengan status `pending` — **belum bisa mengakses fitur operasional**.
3. Transportir melengkapi profil perusahaan.
4. Admin memeriksa, lalu menyetujui atau menolak (dengan alasan).
5. Akun `approved` dapat mengakses panel transportir.

### 4.2 Pengajuan KIM
1. Transportir menambahkan kendaraan dan mengunggah **dokumen wajib**.
1a. Petugas memverifikasi dokumen (Terima/Tolak) melalui menu **Kendaraan**.
2. Transportir membuat pengajuan lalu mengirimkannya.
3. Pengajuan masuk antrean pemeriksaan (HSSE, Distribusi, QQ).
4. Jika ada temuan → status `revision_required`, transportir mengirim tindak lanjut + bukti.
5. Petugas berwenang memverifikasi bukti (terima/tolak).
6. Setelah semua checklist selesai & tanpa temuan terbuka → tahap approval.
7. HSSE, Distribusi, dan QQ menyetujui → FTM memberi approval final.
8. Sistem menerbitkan KIM (nomor unik + QR Code + PDF).

### 4.3 Status Pengajuan
```
draft → submitted → inspection ⇄ revision_required → pending_approval → approved → issued
                          ↘ rejected                     ↘ rejected
```
Setiap transisi divalidasi oleh state machine (`ApplicationStatus`). Transisi bebas ditolak.

---

## 5. Teknologi

| Komponen | Teknologi |
|---|---|
| Backend | Laravel 13 |
| PHP | 8.3+ |
| Admin & Transportir panel | Filament 5 |
| Database | MariaDB (dev: SQLite) |
| Frontend | Blade + Tailwind CSS 4 |
| Autentikasi | Laravel Socialite (Google) + email/password |
| Role & Permission | Spatie Laravel Permission |
| PDF | barryvdh/laravel-dompdf |
| QR Code | bacon/bacon-qr-code |
| Ikon | Blade Heroicons |
| Testing | PHPUnit |

### Palet Halaman Publik
Landing page, login, daftar, dan form pendaftaran memakai palet **lavender + mint** (tema terang
pastel). Token warna didefinisikan di `resources/css/app.css`:

| Token | Warna | Pemakaian |
|---|---|---|
| `lavender-50 … 950` | `#f7f6fe` → `#241c48` | warna utama: tombol, tautan, heading, teks |
| `mint-50 … 700` | `#f2fbfd` → `#2a7e93` | aksen sekunder: ikon centang, highlight, glow |
| `bg-pastel` | gradien `135deg` lavender → putih → mint | latar hero, CTA, kartu panduan, panel auth |
| `bg-pastel-soft` | gradien `160deg` lavender sangat muda → putih | latar section CTA |

> Panel admin dan transportir **tidak** memakai palet ini — keduanya memakai CSS Filament sendiri.

---

## 6. Persyaratan Sistem

- PHP **8.3** atau lebih baru (ekstensi: `pdo_mysql`/`pdo_sqlite`, `gd`, `mbstring`, `openssl`)
- Composer 2.x
- Node.js 18+ & npm
- MariaDB 10.4+ (untuk produksi) / SQLite (untuk development)
- Web server Nginx (produksi)

---

## 7. Instalasi

```bash
# 1. Clone repositori
git clone <url-repositori> o-kim
cd o-kim

# 2. Pasang dependency PHP
composer install

# 3. Salin konfigurasi environment
cp .env.example .env

# 4. Generate application key
php artisan key:generate

# 5. Pasang dependency frontend & build aset
npm install
npm run build

# 6. Jalankan migrasi & seeder (role, permission, master data)
php artisan migrate --seed

# 7. Publish aset Filament (wajib agar tampilan panel muncul)
php artisan filament:assets
```

---

## 8. Konfigurasi

Sunting file `.env` sesuai lingkungan Anda.

### Aplikasi
```env
APP_NAME="O-KIM"
APP_URL=http://localhost:8000
APP_LOCALE=id
APP_TIMEZONE=Asia/Jakarta
```

### Database (MariaDB — produksi)
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=okim
DB_USERNAME=okim
DB_PASSWORD=rahasia
```

> Untuk development cepat, SQLite dapat dipakai: `DB_CONNECTION=sqlite` dan
> `touch database/database.sqlite`.

### Google OAuth
```env
GOOGLE_CLIENT_ID=xxxxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-xxxxx
GOOGLE_REDIRECT_URI=/auth/google/callback
```
Daftarkan **Authorized redirect URI** di Google Cloud Console:
`{APP_URL}/auth/google/callback`

### Email
Tahap awal menggunakan driver `log` (email tercatat di `storage/logs/laravel.log`).
Untuk produksi, atur SMTP setelah penyedia diputuskan:
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.contoh.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=noreply@okim.co.id
```

### Storage
Dokumen sensitif disimpan di **disk private** (`storage/app/private`).
Akses hanya melalui endpoint yang memeriksa otorisasi.
```env
FILESYSTEM_DISK=local
```

Setelah mengubah `.env`, jalankan:
```bash
php artisan config:clear
```

---

## 9. Menjalankan Aplikasi

### Development
```bash
# Terminal 1 — server aplikasi
php artisan serve

# Terminal 2 — Vite (hot reload aset frontend)
npm run dev

# Terminal 3 (opsional) — monitor log
php artisan pail
```

Akses: **http://localhost:8000**

### Menjalankan migration & seeder ulang
```bash
# Reset total database development (HATI-HATI: menghapus semua data)
php artisan migrate:fresh --seed

# Hanya menjalankan seeder (idempotent, aman diulang)
php artisan db:seed
```

### Membuat akun Super Admin

Tidak ada akun admin default (kebijakan keamanan). Gunakan command khusus O-KIM:

```bash
php artisan okim:make-admin admin@okim.test \
  --name="Super Admin" \
  --role=super_admin \
  --password="GantiDenganPasswordKuat"
```

| Opsi | Keterangan |
|---|---|
| `email` (argumen) | Email akun |
| `--name` | Nama pengguna (default: bagian sebelum `@`) |
| `--role` | Role internal (default `super_admin`). Pilihan: `super_admin`, `admin_verifikasi`, `hsse`, `distribusi`, `qq`, `ftm` |
| `--password` | Password akun (minimal 8 karakter). Bila dikosongkan, password acak digenerate dan ditampilkan |

Perilaku:
- Akun baru dibuat dengan **status `approved`** dan role yang diminta.
- Bila email sudah ada, akun **diperbarui** (status dijadikan `approved`, role disinkronkan). Password lama **tidak** diubah kecuali `--password` diberikan.
- Idempotent — aman dijalankan berulang.

> **Kenapa bukan `make:filament-user`?** Command bawaan Filament hanya mengisi `name`, `email`,
> dan `password`. Akibatnya `account_status` tetap `pending` dan tidak ada role, sehingga
> `canAccessPanel()` menolak login ke panel admin. `okim:make-admin` mengisi keduanya.

Jika muncul pesan **role tidak ditemukan**, jalankan seeder terlebih dahulu (wajib setelah pindah database):
```bash
php artisan migrate --seed
```


---

## 10. Akun & Akses Panel

| Panel | URL Login | Keterangan |
|---|---|---|
| Publik (transportir) | `/masuk` | Login email/password atau Google |
| Pendaftaran | `/daftar` | Registrasi akun baru |
| Admin | `/admin/login` | Login petugas internal |
| Panel Transportir | `/transportir` | Diakses setelah login publik & disetujui |
| Validasi KIM | `/validasi-kim/{token}` | Publik, tanpa login |

**Akun development** (hanya contoh, buat sendiri):

| Peran | Email | Password |
|---|---|---|
| Super Admin | `admin@okim.test` | *(dibuat manual via tinker)* |
| Transportir | `transportir@okim.test` | `demo12345` |

> ⚠️ Jangan gunakan password contoh di lingkungan produksi.

---

## 11. Panduan Penggunaan

### 11.1 Sebagai Transportir

**A. Mendaftar**
1. Buka `/daftar`, isi nama PIC, email, telepon, dan kata sandi (min. 8 karakter dengan huruf besar, kecil, dan angka) — atau klik **Daftar dengan Google**.
2. Setelah akun dibuat, Anda diarahkan ke form **Profil Perusahaan**. Lengkapi **nama PIC**, nama, alamat, dan telepon perusahaan, lalu simpan.
3. Tunggu verifikasi admin. Status akun `pending` belum bisa mengakses panel.

**B. Setelah Disetujui**
1. Login di `/masuk`, Anda diarahkan ke panel `/transportir`.
2. **Profil Perusahaan** — perbarui **nama PIC** (juga mengubah nama akun Anda) dan data perusahaan bila perlu. Berguna bagi pendaftar via Google yang belum sempat mengatur nama akun.
3. **Kendaraan** — tambah kendaraan tangki (NOPOL, merek, model, tahun, kapasitas, **nomor rangka, nomor mesin, jenis angkutan, bahan tangki**).
4. Buka kendaraan → **Dokumen Kendaraan** → unggah dokumen wajib (PDF/JPG/PNG, maks 5 MB). Dokumen lama otomatis tersimpan sebagai riwayat. Tombol **Pratinjau** membuka berkas di tab baru, **Unduh** menyimpan berkasnya.
5. **Pengajuan KIM** → *Buat* → pilih kendaraan & jenis (Baru/Perpanjangan) → **Kirim Pengajuan**.
   Dropdown kendaraan **hanya menampilkan NOPOL yang seluruh dokumen wajibnya sudah Terverifikasi**
   dan tidak memiliki pengajuan berjalan. Bila belum ada yang muncul, periksa kembali dokumen kendaraan
   (harus diunggah **dan** diverifikasi petugas).
6. Pantau status. Bila ada temuan, buka **Temuan & Tindak Lanjut** → kirim deskripsi perbaikan + bukti.
7. Setelah KIM terbit, tombol **Unduh KIM** muncul pada halaman pengajuan.

**C. Validasi KIM (publik)**
Pindai QR Code pada KIM → halaman `/validasi-kim/{token}` menampilkan nomor KIM, NOPOL, tanggal terbit/kedaluwarsa, dan status (Aktif/Kedaluwarsa/Dicabut).

---

### 11.2 Sebagai Admin / Petugas Internal

Login di `/admin/login`.

**A. Verifikasi Transportir**
1. Menu **Verifikasi Transportir** → daftar pendaftar.2. Klik satu baris untuk melihat data perusahaan.
3. **Setujui Pendaftaran** atau **Tolak Pendaftaran** (alasan wajib diisi).
4. Akun yang disetujui dapat mengakses panel transportir.

**B. Master Data**
- **Jenis Dokumen** — kelola STNK, KEUR, TERA, PTO (wajib/opsional, ada tanggal kedaluwarsa).
- **Checklist Pemeriksaan** — kelola checklist per fungsi beserta **butir** dan **sub-butir** (poin rinci). Setiap butir punya kode, urutan, dan status wajib/aktif.
- **Kendaraan** — lihat seluruh kendaraan dari semua transportir, filter per perusahaan/status.
  Buka detail kendaraan untuk melihat & mengelola **dokumen kendaraan**: **Pratinjau** (buka berkas
  di tab baru), **Unduh**, serta **Verifikasi** / **Tolak** dokumen. Kolom **Perlu Verifikasi** pada
  daftar menandai kendaraan yang dokumennya masih menunggu verifikasi.

**B2. Verifikasi Dokumen Kendaraan**
Dokumen yang diunggah transportir berstatus **Menunggu Verifikasi**. Petugas dengan permission
`verify_documents` (seluruh petugas internal) membuka **Kendaraan → detail → tabel Dokumen Kendaraan**
lalu memilih:
- **Verifikasi** — dokumen dinyatakan sah.
- **Tolak** — wajib mengisi alasan; transportir menerima notifikasi dan harus mengunggah ulang.

**Dampak:** pengajuan KIM **tidak dapat dikirim** dan KIM **tidak dapat diterbitkan** selama masih ada
dokumen wajib yang belum berstatus *Terverifikasi*. Dokumen yang sudah digantikan versi baru tidak
dapat diverifikasi.

**C. Pemeriksaan**
1. Menu **Pengajuan & Pemeriksaan** → buka pengajuan.
2. *(Opsional)* Klik **Tetapkan Tanggal** untuk mencatat jadwal pelaksanaan pemeriksaan (bersifat informasi).
3. Klik **Isi Checklist Pemeriksaan** — hanya checklist fungsi Anda yang tampil (sesuai permission).
4. Untuk setiap butir pilih **Sesuai / Temuan / Tidak Berlaku**, lalu **centang sub-butir** (poin rinci seperti lokasi pemasangan/komponen) yang sesuai kondisi kendaraan. Bila *Temuan*, isi deskripsi.
5. Simpan. Temuan otomatis dibuat dan status pengajuan menjadi `revision_required`.

> **Checklist bertingkat:** setiap butir (mis. *Sakelar Keamanan / Safety Switch*) memiliki status
> di level butir, dan daftar **sub-butir** yang dapat dicentang petugas. Butir dan sub-butir
> dikelola admin melalui menu **Checklist Pemeriksaan** (`/admin/inspection-checklists`).

**D. Verifikasi Temuan**
1. Menu **Temuan & Verifikasi** → buka temuan.
2. Lihat tindak lanjut + bukti dari transportir.
3. **Terima** (temuan ditutup) atau **Tolak** (catatan wajib; temuan tetap terbuka).

**E. Approval**
Pada halaman pengajuan, tersedia action sesuai kewenangan:
- **Approval HSSE / Distribusi / QQ** — ketiganya wajib menyetujui.
- **Approval Final (FTM)** — hanya aktif setelah ketiga fungsi menyetujui.
- Tombol **Tolak** pada tiap fungsi wajib disertai alasan.

**F. Penerbitan KIM**
Setelah status `approved`, FTM klik **Terbitkan KIM**. Sistem membuat nomor unik, QR token, dan
masa berlaku (mengikuti kedaluwarsa dokumen terawal, dibatasi pengaturan masa berlaku).

**G. Pengaturan Sistem** dan **Notifikasi**

Menu **Pengaturan Sistem** (hanya super admin) berisi:
| Pengaturan | Default | Keterangan |
|---|---|---|
| Masa berlaku KIM | 6 bulan | Batas maksimum; tetap diambil yang **paling awal** antara nilai ini dan kedaluwarsa dokumen wajib |
| Penomoran KIM | `KIM/FT-TGL` · periode `Y/m` · 4 digit | Prefix + format periode + digit urutan → `KIM/FT-TGL/2026/10/0001` |
| **Penomoran Pengajuan KIM** | `APP` · periode `Ymd` · 4 digit | Prefix + format periode + digit urutan → `APP/20261014/0001` |

Setiap bagian penomoran menampilkan **pratinjau nomor** yang ikut berubah saat pengaturan disunting.
Format periode tersedia: tanggal (`Ymd`), tahun/bulan (`Y/m`), tahun (`Y`), atau tanpa periode.

**H. Notifikasi Database (Lonceng) & Pencarian Global**

Kedua panel (`/admin` dan `/transportir`) menampilkan **ikon lonceng** di sudut kanan atas.
Setiap peristiwa penting memicu notifikasi yang tersimpan di database:

| Peristiwa | Penerima | Contoh |
|---|---|---|
| Pendaftaran transportir baru | Petugas verifikator | Akun + nama perusahaan |
| Kendaraan baru ditambahkan | Petugas dokumen | Perusahaan + NOPOL |
| Pengajuan KIM baru dikirim | Petugas pemeriksa | Nomor pengajuan + NOPOL |
| Jadwal pemeriksaan ditetapkan | Transportir pemilik | Nomor pengajuan + jadwal |
| Temuan pemeriksaan baru | Transportir pemilik | Fungsi + deskripsi temuan |
| Tindak lanjut dikirim | Petugas fungsi terkait | Pengajuan + fungsi |
| Approval fungsi selesai | FTM (menunggu final) | Pengajuan siap diputuskan |
| Pengajuan disetujui / diterbitkan / ditolak | Transportir pemilik | Nomor pengajuan / nomor KIM |
| Dokumen diverifikasi / ditolak | Transportir pemilik | Jenis dokumen + NOPOL |

Klik notifikasi untuk menandainya dibaca; setiap notifikasi memiliki tombol **Lihat** yang menuju
langsung ke data terkait.

**Pencarian global** tersedia di kolom pencarian atas kedua panel (ketik NOPOL, nomor pengajuan,
nomor KIM, nama perusahaan, nama/pic, atau email). Hasil difilter sesuai akses role masing-masing.

---

## 12. Struktur Proyek

```
app/
├── Actions/                 # Operasi bisnis tunggal (approve/reject registrasi)
├── Enums/                   # Status & kategori tetap (11 enum)
├── Filament/
│   ├── Resources/           # Resource panel Admin
│   ├── Widgets/             # Widget dashboard Admin
│   └── Transportir/         # Resource, Page & Widget panel Transportir
├── Http/
│   ├── Controllers/         # Auth, download, validasi KIM
│   └── Requests/            # Validasi form
├── Models/                  # Eloquent models & relasi
├── Notifications/           # Notifikasi email
├── Policies/                # Otorisasi per-record (isolasi perusahaan)
└── Services/                # Proses bisnis multi-langkah
    ├── Application/         # Pengajuan KIM
    ├── Approval/            # Approval berjenjang
    ├── Document/            # Upload & versi dokumen
    ├── Finding/             # Tindak lanjut & verifikasi temuan
    ├── Inspection/          # Checklist, sub-butir & kesiapan approval
    ├── Setting/             # Pengaturan sistem (key-value)
    ├── Kim/                 # Penerbitan, masa berlaku, PDF, QR
    └── Registration/        # Pembuatan akun pending

database/
├── factories/  migrations/  seeders/

resources/views/
├── landing/     # Landing, login, register, profil
├── kim/         # Template PDF & halaman validasi
└── components/  # Komponen Blade (button, layout, dll.)

tests/Feature/   # 93 test otomatis
```

### Prinsip Arsitektur
- **Business logic tidak berada di Filament Resource** — ditempatkan pada Service/Action.
- **Semua aturan penting ditegakkan di backend**, bukan hanya UI.
- **Policy** untuk otorisasi per-record; **Enum** untuk status tetap.
- **Database transaction** untuk operasi atomik (penerbitan KIM, upload dokumen).
- **Isolasi data** transportir ditegakkan lewat query scope + Policy.

---

## 13. Pengujian

```bash
# Seluruh test
php artisan test

# Test tertentu
php artisan test --filter=ApprovalAndIssuanceTest

# Format kode (Laravel Pint)
vendor/bin/pint
```

Cakupan pengujian business rule kritis:

| Berkas | Fokus |
|---|---|
| `FoundationTest` | Role, permission, akses panel |
| `RegistrationTest` | Registrasi, approval akun, notifikasi |
| `CompanyProfilePicNameTest` | Nama PIC di profil perusahaan & pendaftaran publik |
| `EmailPasswordAuthTest` | Login/daftar email-password, jalur terpisah |
| `VehicleDocumentTest` | Isolasi data, keamanan upload, riwayat dokumen |
| `VehicleDocumentVerificationTest` | Verifikasi/tolak dokumen & dampaknya ke pengajuan |
| `VehicleDetailsTest` | Field kendaraan baru (rangka, mesin, jenis angkutan, bahan tangki) |
| `InspectionWorkflowTest` | Checklist, temuan, transisi status |
| `InspectionChecklistResourceTest` | CRUD checklist, butir, sub-butir (master data) |
| `InspectionPointsTest` | Sub-butir pada form pemeriksaan & penyimpanannya |
| `InspectionSchedulingTest` | Jadwal pelaksanaan pemeriksaan |
| `SettingsTest` | Pengaturan sistem (masa berlaku & penomoran KIM/pengajuan) |
| `ApplicationNumberingTest` | Penomoran pengajuan mengikuti pengaturan |
| `DatabaseNotificationTest` | Helper notifikasi & pemicu event (10 test) |
| `GlobalSearchTest` | Konfigurasi pencarian global per resource (8 test) |
| `MakeAdminCommandTest` | Command `okim:make-admin` (akun admin & validasinya) |
| `UserManagementTest` | Pengguna Internal: create/edit role, proteksi nonaktifkan |
| `RoleAccessMatrixTest` | Matriks akses role × resource + akses URL langsung |
| `TransportirPanelAccessTest` | Isolasi akses panel transportir |
| `KimCardListTest` | Daftar & detail KIM Terbit di panel admin (9 test) |
| `ApprovalAndIssuanceTest` | Approval berjenjang, penerbitan, QR, anti-ganda |
| `LandingNavigationTest` | Navigasi & redirect sesuai status akun |
| `DashboardWidgetTest` | Widget dashboard, isolasi data, peringatan kedaluwarsa dokumen |
| `VehicleProgressStepperTest` | Stepper progres pengajuan: 5 tahap, dropdown, isolasi data |
| `AdminVehicleListTest` | Daftar kendaraan lintas perusahaan |

---

## 14. Deployment Produksi

1. Set `APP_ENV=production` dan `APP_DEBUG=false`.
2. Konfigurasi MariaDB, jalankan `php artisan migrate --force --seed`.
3. Build aset: `npm ci && npm run build`.
4. Optimasi: `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
5. Arahkan document root web server (Nginx) ke folder `public/`.
6. Atur queue worker untuk notifikasi: `php artisan queue:work`.
7. Konfigurasikan SMTP sebelum email produksi diaktifkan.
8. Aktifkan backup berkala untuk database dan `storage/app/private`.
9. **Ganti semua password default** dan hapus akun demo.
10. Batasi akses panel admin sesuai jaringan internal bila memungkinkan.

---

## 15. Status & Catatan Penting

### Sudah Berjalan
- Phase 1 — Foundation (dua panel, role/permission, testing)
- Phase 2 — Landing page & registrasi (Google + email/password)
- Phase 3 — Master data & dokumen (kendaraan, jenis dokumen, private storage)
- Phase 4 — Pengajuan & pemeriksaan (checklist, temuan, tindak lanjut)
- Phase 5 — Approval & KIM (approval berjenjang, PDF, QR, validasi publik)

### Belum Selesai / Menunggu Konfirmasi
- **Phase 6** — Hardening (audit log, optimasi, dokumentasi deployment lanjutan)
- **Database produksi** — konfigurasi MariaDB
- **SMTP produksi** — pengiriman email nyata (saat ini driver `log`)
- **Verifikasi email** — struktur disiapkan, belum diwajibkan
- **Master checklist** — butir HSSE/Distribusi/QQ masih placeholder

### Keputusan Bisnis Menunggu Klien (PRD bagian 15)
- Format final nomor KIM (sementara: `KIM/FT-TGL/YYYY/MM/####`)
- Batas maksimum masa berlaku KIM (sementara: 12 bulan)
- Butir checklist final per fungsi
- Dokumen wajib per jenis kendaraan
- Kebijakan keanggotaan (satu perusahaan banyak PIC?)
- Aturan pencabutan KIM sebelum kedaluwarsa

### Aturan Keamanan
- Dokumen sensitif disimpan di **private disk**, diakses via endpoint ber-otorisasi
- Upload divalidasi berdasarkan **MIME type** (bukan ekstensi) & ukuran
- Rate limiting pada endpoint autentikasi & validasi publik
- QR token acak 48 karakter (tidak dapat ditebak)
- Akun `pending`/`rejected`/`suspended` tidak dapat mengakses area operasional

---

<div align="center">

**O-KIM** — Online Kartu Izin Masuk · Fuel Terminal Tegal

</div>
