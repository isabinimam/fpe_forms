# Formulir Psikoedukasi Keluarga (FPE) & Sistem Antrean Notifikasi WhatsApp Otomatis
### RSKD Duren Sawit — Native PHP + Bootstrap 5 + SQL Server (sqlsrv) + Node.js Worker (Baileys / Cloud API)

Sistem ini adalah modul formulir medis dan otomatisasi notifikasi WhatsApp terintegrasi yang mencakup 4 kebutuhan klinis utama RSKD Duren Sawit:
1. **Penjadwalan Sesi FPE & Notifikasi WhatsApp** (Pengingat otomatis H-1 dan tombol pengiriman manual seketika).
2. **Bukti Dokumentasi Hasil FPE** (Asesmen kesiapan keluarga, materi FPE, dan relasi keluarga hadir berbasis *multiple choice*).
3. **Jadwal Kegiatan Harian Pasien** (Grid checklist 10 hari adaptif terhadap tanggal mulai periode).
4. **Skrining Risiko Bunuh Diri** (Kalkulasi otomatis umur pasien: *Tahun, Bulan, Hari*, skoring otomatis depresi & risiko, serta penentuan unit asal pasien).

---

## 🌟 Fitur Utama

1. **Otomatisasi Penuh WhatsApp (H-1) & Kirim Manual Seketika**:
   - Sistem secara otomatis menghitung jadwal pengingat H-1 pukul 09:00 WIB saat jadwal FPE disimpan.
   - Tersedia tombol operasional mandiri **"Kirim Manual Sekarang"** dan **"Kirim Ulang"** pada tabel riwayat.
   - **Mekanisme Anti-Duplikasi**: Saat pesan dikirim manual, seluruh pengingat otomatis terjadwal untuk sesi tersebut **langsung digugurkan (*status: cancelled*)** untuk mencegah pengiriman pesan ganda ke keluarga pasien.
2. **Template Pesan Resmi RSKD Duren Sawit**:
   - Pesan WhatsApp terstruktur rapi dengan identitas resmi rumah sakit, nama hari lengkap Bahasa Indonesia (*contoh: Senin, 24 Agustus 2026*), detail sesi (Video Call WA / Link & Passcode Zoom), dan poin catatan penting bagi keluarga.
3. **Hubungan Keluarga Hadir Pilihan Ganda (*Multiple Choice*)**:
   - Formulir Dokumentasi FPE mendukung pencatatan lebih dari satu anggota keluarga yang hadir (misal: Ayah + Ibu, atau Suami + Anak) menggunakan *checkbox* responsif dan tersimpan aman di database SQL Server (`NVARCHAR(500)`).
4. **Kalkulasi Umur Pasien Presisi (*Tahun, Bulan, Hari*)**:
   - Formulir Skrining secara otomatis menghitung umur pasien dari `tanggal_lahir` kalender secara presisi (*contoh: 41 Tahun 4 Bulan 7 Hari*) dan menampilkan badge visual di bagian atas form.
   - Pilihan unit **Asal Pasien (Poli / IGD)** ditempatkan di bagian bawah sebelum tombol simpan dengan **default di Poli**.
5. **Modular, Portabel, & Bebas Dependensi Global (`include`-ready)**:
   - 4 formulir PHP dirancang sebagai komponen mandiri yang tidak memuat layout global. Sangat mudah dipindahkan ke aplikasi utama (*main project*) yang sudah ada.
6. **Koneksi Langsung SQL Server (`sqlsrv`)**:
   - Menggunakan driver resmi Microsoft `sqlsrv` (tanpa PDO) dengan dukungan penuh Windows Authentication (`localhost\SQLEXPRESS`).
7. **Antrean Tangguh (Queue Worker)**:
   - Ditenagai background service Node.js dengan penguncian atomik SQL Server CTE (`UPDLOCK, READPAST`), pemulihan antrean macet (*stale recovery*), dan mekanisme *retry backoff*.
8. **Abstraksi Multi-Provider**:
   - Menggunakan **Baileys** (QR Login gratis) untuk pengujian lokal dan siap dialihkan ke **WhatsApp Cloud API resmi** saat produksi hanya dengan mengubah file `.env`.

---

## 📁 Struktur Direktori

```text
php_forms/
├── php/
│   ├── includes/
│   │   ├── helpers.php              # Normalisasi no HP, format tanggal Indo, kalkulasi umur lengkap
│   │   └── wa_queue.php             # Helper pembuatan antrean WA & template pesan resmi RSKD
│   ├── form_jadwal_fpe.php          # 📅 1. Form Penjadwalan FPE, Antrean H-1, & Kirim Manual
│   ├── form_dokumentasi_fpe.php     # 📝 2. Form Bukti Dokumentasi FPE (Multiple Choice Checkbox)
│   ├── form_kegiatan_pasien.php     # 📋 3. Form Jadwal Kegiatan Pasien (Grid 10 Hari Adaptif)
│   └── form_skrining_bunuh_diri.php # 🛡️ 4. Form Skrining Risiko Bunuh Diri (Umur Akurat & Asal Pasien)
│
├── node/
│   ├── src/
│   │   ├── worker.js                # Entry point queue worker background service
│   │   ├── db.js                    # Pool koneksi SQL Server (mssql)
│   │   ├── queue.js                 # Logika penguncian atomik CTE, klaim, & update antrean
│   │   ├── message-template.js      # Generator pesan pengingat resmi FPE
│   │   ├── phone.js                 # Normalisasi nomor telepon standar 628xxx
│   │   └── whatsapp/
│   │       ├── WhatsAppProvider.js  # Abstract base class provider
│   │       ├── BaileysProvider.js   # Provider pengujian lokal (QR login)
│   │       └── CloudApiProvider.js  # Provider resmi produksi (Meta Cloud API)
│   ├── tests/
│   │   └── unit.test.js             # Pengujian unit otomatis (10/10 PASS)
│   ├── package-lock.json            # Lockfile dependensi Node.js
│   ├── package.json                 # Manifest dependensi & skrip Node.js
│   └── .env.example                 # Template konfigurasi environment worker
│
├── database/
│   └── sqlserver.sql                # Skema DDL lengkap SQL Server (6 tabel produksi)
│
├── docs/
│   ├── integration-guide.md         # ⭐ PANDUAN LENGKAP INTEGRASI KE PROYEK UTAMA
│   ├── architecture.md              # Diagram & penjelasan arsitektur sistem
│   ├── setup-windows.md             # Panduan instalasi di Windows & driver sqlsrv
│   ├── testing.md                   # Panduan pengujian (sintaks, unit, E2E)
│   └── whatsapp-providers.md        # Panduan beralih ke WhatsApp Cloud API Meta
│
├── .env.example                     # Contoh konfigurasi environment PHP
├── .gitignore                       # Konfigurasi ignore Git (hanya melacak berkas produksi)
└── README.md                        # Dokumentasi utama proyek
```

---

## 🚀 Panduan Menjalankan Pengujian Lokal (Standalone)

### 1. Eksekusi Skema Database
Buka **SQL Server Management Studio (SSMS)** pada instance `localhost\SQLEXPRESS`, lalu jalankan berkas:
📄 `database/sqlserver.sql` (pada database `form_pfe`).

### 2. Jalankan Web Server PHP
Buka terminal / PowerShell di root proyek:
```powershell
php -S localhost:8080 -t php/
```
Akses halaman pengujian: 👉 **[http://localhost:8080](http://localhost:8080)**

### 3. Jalankan Worker Antrean WhatsApp Node.js
Buka terminal baru:
```powershell
cd node
npm install
npm start
```
*Catatan: Pada kali pertama dijalankan, terminal akan menampilkan QR Code untuk ditautkan ke WhatsApp di ponsel Anda via menu Perangkat Tertaut.*

---

## ⭐ Cara Menggabungkan ke Proyek Utama (Main Project)

Untuk memindahkan ke-4 komponen form ini ke aplikasi utama yang dimiliki bos Anda, silakan ikuti petunjuk lengkap di:
👉 **[`docs/integration-guide.md`](docs/integration-guide.md)**

Setiap file form hanya membutuhkan 2-3 variabel sebelum di-`include`:
```php
$conn         = $koneksi_db_sqlsrv_anda; // Resource koneksi aktif sqlsrv_connect
$id_pasien    = (int)$_GET['id_pasien'];  // ID pasien yang sedang dibuka
$nama_petugas = $_SESSION['nama'] ?? 'Petugas Ruangan';

include 'app/forms/form_jadwal_fpe.php';
```

---

## 🧪 Pengujian Otomatis

```powershell
# Pengujian sintaks seluruh file PHP
php -l php/form_jadwal_fpe.php
php -l php/form_dokumentasi_fpe.php
php -l php/form_kegiatan_pasien.php
php -l php/form_skrining_bunuh_diri.php
php -l php/includes/helpers.php
php -l php/includes/wa_queue.php

# Pengujian unit worker Node.js (10/10 test pass)
cd node
npm test
```

---

## 📄 Lisensi & Hak Cipta
Dikembangkan untuk **RSKD Duren Sawit**.
