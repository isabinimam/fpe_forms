# Formulir Psikoedukasi Keluarga (FPE) & Sistem Antrean Notifikasi WhatsApp Otomatis
### RSKD Duren Sawit — Native PHP + Bootstrap 5 + SQL Server (sqlsrv) + Node.js Worker (Baileys / Cloud API)

Sistem ini adalah modul lengkap untuk pengelolaan sesi Psikoedukasi Keluarga (FPE), dokumentasi medis, kegiatan harian pasien, skrining risiko bunuh diri, serta **pengiriman pesan pengingat WhatsApp otomatis satu hari sebelum sesi (H-1)** ke kontak keluarga pasien.

---

## 🌟 Fitur Utama

1. **Otomatisasi Penuh WhatsApp (H-1)**: Petugas cukup menyimpan jadwal FPE, sistem secara atomik menghitung jadwal H-1 dan mendaftarkan antrean pengiriman WhatsApp. Tidak ada checkbox manual *"sudah dikirim"*.
2. **Modular & Portabel (Siap Salin)**: 4 formulir PHP dirancang sebagai komponen mandiri (`include`-ready). Sangat mudah dipindahkan ke aplikasi utama (*main project*) yang sudah berjalan.
3. **Koneksi Langsung SQL Server (`sqlsrv`)**: Menggunakan driver resmi Microsoft `sqlsrv` dengan dukungan penuh Windows Authentication (`localhost\SQLEXPRESS`).
4. **Antrean Tangguh (Queue Worker)**: Ditenagai background service Node.js dengan penguncian atomik (`UPDLOCK, READPAST`), pencegahan duplikasi, pemulihan antrean macet (*stale recovery*), dan mekanisme *retry backoff*.
5. **Abstraksi Multi-Provider**: Menggunakan **Baileys** (QR Login gratis) untuk pengujian lokal dan siap dialihkan ke **WhatsApp Cloud API resmi** saat produksi hanya dengan mengubah file `.env`.
6. **Antarmuka Bahasa Indonesia**: Tampilan modern Bootstrap 5 dan pesan resmi dalam Bahasa Indonesia yang ramah pengguna.

---

## 📁 Struktur Direktori

```text
php_forms/
├── php/
│   ├── config/
│   │   ├── database.php             # Koneksi SQL Server (sqlsrv direct driver)
│   │   └── app.php                  # Konfigurasi aplikasi & .env loader
│   ├── includes/
│   │   ├── helpers.php              # Normalisasi no HP, format tanggal, status badge
│   │   └── wa_queue.php             # Helper pembuatan antrean WA & template pesan
│   ├── form_jadwal_fpe.php          # 📅 1. Form Penjadwalan FPE & Antrean Otomatis
│   ├── form_dokumentasi_fpe.php     # 📝 2. Form Bukti Dokumentasi FPE
│   ├── form_kegiatan_pasien.php     # 📋 3. Form Jadwal Kegiatan Pasien (Grid 10 Hari)
│   ├── form_skrining_bunuh_diri.php # 🛡️ 4. Form Skrining Risiko Bunuh Diri (Auto-score)
│   └── index.php                    # ⚠️ Test Harness Standalone (Pengujian lokal)
│
├── node/
│   ├── src/
│   │   ├── worker.js                # Entry point queue worker background service
│   │   ├── db.js                    # Pool koneksi SQL Server (mssql)
│   │   ├── queue.js                 # Logika penguncian, klaim, & update antrean
│   │   ├── message-template.js      # Generator pesan pengingat resmi FPE
│   │   ├── phone.js                 # Normalisasi nomor telepon
│   │   └── whatsapp/
│   │       ├── WhatsAppProvider.js  # Abstract base class
│   │       ├── BaileysProvider.js   # Provider pengujian lokal (QR login)
│   │       └── CloudApiProvider.js  # Provider resmi produksi (Meta Cloud API)
│   ├── tests/
│   │   └── unit.test.js             # Pengujian unit otomatis
│   ├── package.json
│   └── .env.example
│
├── database/
│   └── sqlserver.sql                # Skema lengkap database SQL Server (form_pfe)
│
├── docs/
│   ├── integration-guide.md         # ⭐ PANDUAN MENYALIN KE PROYEK UTAMA
│   ├── architecture.md              # Diagram & penjelasan arsitektur
│   ├── setup-windows.md             # Panduan instalasi di Windows
│   ├── testing.md                   # Panduan pengujian (sintaks, unit, E2E)
│   └── whatsapp-providers.md        # Panduan beralih ke WhatsApp Cloud API
│
├── .env.example                     # Contoh konfigurasi environment PHP
├── .gitignore
├── implementation_plan.md           # Rencana implementasi teknis
└── README.md
```

---

## 🚀 Panduan Cepat Menjalankan (Pengujian Lokal Standalone)

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
*Catatan: Pada kali pertama dijalankan, terminal akan menampilkan QR Code untuk disambungkan ke aplikasi WhatsApp di ponsel Anda.*

---

## ⭐ Cara Memindahkan ke Proyek Utama (Main Project)

Untuk memindahkan ke-4 komponen form ini ke aplikasi utama yang dimiliki bos Anda, silakan ikuti petunjuk lengkap di:
👉 **[`docs/integration-guide.md`](docs/integration-guide.md)**

Setiap file form hanya membutuhkan 2-3 variabel sebelum di-`include`:
```php
$conn         = $koneksi_db_sqlsrv_anda;
$id_pasien    = (int)$_GET['id_pasien'];
$nama_petugas = $_SESSION['nama'] ?? 'Petugas';

include 'form_jadwal_fpe.php';
```

---

## 🧪 Menjalankan Pengujian

```powershell
# Pengujian sintaks PHP
php -l php/form_jadwal_fpe.php

# Pengujian unit Node.js
cd node
npm test
```

---

## 📄 Lisensi & Hak Cipta
Dikembangkan untuk **RSKD Duren Sawit**.
