# PANDUAN INTEGRASI KE MAIN PROJECT
## Cara Menyalin & Menggabungkan Komponen Form FPE ke Aplikasi Utama

Dokumen ini menjelaskan langkah demi langkah cara memindahkan **4 komponen formulir FPE** dan **worker WhatsApp** dari proyek pengujian mandiri (*standalone*) ini ke dalam aplikasi utama (*main project*) yang sudah ada.

> ⚠️ **Catatan**: Dokumen ini terakhir diperbarui pada **19 Agustus 2026** dan mencerminkan seluruh fitur terkini termasuk:
> - Pengiriman manual WhatsApp dengan pembatalan otomatis pengingat terjadwal
> - Hubungan keluarga yang hadir sebagai pilihan ganda (*multiple choice checkbox*)
> - Kalkulasi umur pasien presisi (Tahun, Bulan, Hari) dari tanggal lahir
> - Asal pasien (Poli / IGD, default: Poli) diposisikan di bagian bawah Form Skrining sebelum submit

---

## 1. Ringkasan Arsitektur Komponen

Setiap formulir PHP didesain sebagai **komponen independen (`include`-ready)**. Komponen tidak memuat header, footer, sidebar, atau styling Bootstrap global — hanya merender satu elemen `<div class="card">...</div>`.

### Kontrak Variabel yang Diperlukan
Sebelum meng-`include` file form apa pun, halaman induk di proyek utama **hanya perlu menyediakan 2-3 variabel**:

```php
$conn         // Resource koneksi aktif SQL Server (hasil dari sqlsrv_connect)
$id_pasien    // Integer ID pasien yang sedang aktif/dibuka
$nama_petugas // String nama petugas yang sedang login (opsional, default: 'Petugas')
```

### Dependensi Antar File
```
form_jadwal_fpe.php         ──requires──► includes/helpers.php
                            ──requires──► includes/wa_queue.php (yang juga require helpers.php)

form_dokumentasi_fpe.php    (mandiri, tidak memerlukan file tambahan)

form_kegiatan_pasien.php    (mandiri, tidak memerlukan file tambahan)

form_skrining_bunuh_diri.php ──requires──► includes/helpers.php
                                           (untuk fungsi hitungUmurLengkap & formatTanggalIndo)
```

### Dependensi Tabel Pasien untuk Form Skrining
Form Skrining Risiko Bunuh Diri memerlukan kolom `tanggal_lahir` pada tabel pasien utama untuk menghitung dan menampilkan umur pasien secara presisi. Jika tabel pasien di proyek utama Anda belum memiliki kolom tersebut, silakan tambahkan:
```sql
ALTER TABLE [dbo].[tabel_pasien_anda] ADD [tanggal_lahir] DATE NULL;
```
Dan pastikan query pada `form_skrining_bunuh_diri.php` baris referensi `SELECT nama_pasien, tanggal_lahir FROM tbl_pasien WHERE id_pasien = ?` disesuaikan dengan nama tabel dan kolom yang digunakan di proyek utama Anda.

---

## 2. Daftar Berkas yang Harus & Tidak Boleh Disalin

### ✅ Berkas yang DISALIN ke Proyek Utama:

| Berkas Sumber | Lokasi di Proyek Utama (Rekomendasi) | Keterangan |
|---------------|-------------------------------------|------------|
| `php/form_jadwal_fpe.php` | `app/forms/form_jadwal_fpe.php` | Form penjadwalan FPE, riwayat jadwal, tombol Kirim Manual, antrean WA otomatis H-1 |
| `php/form_dokumentasi_fpe.php` | `app/forms/form_dokumentasi_fpe.php` | Form bukti dokumentasi hasil FPE (hubungan keluarga multiple choice) |
| `php/form_kegiatan_pasien.php` | `app/forms/form_kegiatan_pasien.php` | Form checklist kegiatan harian pasien (grid centang 10 hari) |
| `php/form_skrining_bunuh_diri.php` | `app/forms/form_skrining_bunuh_diri.php` | Form skrining risiko bunuh diri (dengan kalkulasi umur pasien) |
| `php/includes/helpers.php` | `app/includes/fpe_helpers.php` | Fungsi helper: normalisasi no HP, format tanggal Indonesia, kalkulasi umur |
| `php/includes/wa_queue.php` | `app/includes/fpe_wa_queue.php` | Helper insert antrean WA & template pesan resmi |
| `node/` *(seluruh folder)* | `services/wa-worker/` atau root terpisah | Worker latar belakang pengirim WhatsApp (Node.js + Baileys) |

### ❌ Berkas yang JANGAN Disalin:

| Berkas | Alasan |
|--------|--------|
| `php/index.php` | Hanya *test harness* pengujian lokal. Proyek utama sudah punya tampilan/layout sendiri. |
| `php/config/app.php`, `php/config/database.php` | Konfigurasi lokal untuk pengujian. Proyek utama sudah punya koneksi database sendiri. |
| `tbl_pasien` di `database/sqlserver.sql` | Tabel dummy untuk pengujian. Proyek utama sudah punya tabel pasien sendiri. |

### ⚠️ Path `require_once` yang Harus Disesuaikan Setelah Salin:

Setelah menyalin berkas-berkas di atas, sesuaikan baris `require_once` di dalam file form agar menunjuk ke lokasi baru file helper di proyek utama. Contoh:

| Di file | Baris lama | Baris baru (sesuaikan path) |
|---------|-----------|---------------------------|
| `form_jadwal_fpe.php` | `require_once __DIR__ . '/includes/helpers.php'` | `require_once __DIR__ . '/../includes/fpe_helpers.php'` |
| `form_jadwal_fpe.php` | `require_once __DIR__ . '/includes/wa_queue.php'` | `require_once __DIR__ . '/../includes/fpe_wa_queue.php'` |
| `form_skrining_bunuh_diri.php` | `require_once __DIR__ . '/includes/helpers.php'` | `require_once __DIR__ . '/../includes/fpe_helpers.php'` |

---

## 3. Langkah Migrasi Database (SQL Server)

Buka SQL Server Management Studio (SSMS) pada database proyek utama Anda, lalu jalankan skrip berikut (diambil dari `database/sqlserver.sql`):

### A. Tabel Jadwal FPE
```sql
CREATE TABLE [dbo].[tbl_jadwal_fpe] (
    [id_jadwal]           INT IDENTITY(1,1) PRIMARY KEY,
    [id_pasien]           INT NOT NULL,  -- Sesuaikan FK dengan PK tabel pasien Anda
    [tanggal_pelaksanaan] DATE NOT NULL,
    [jam_pelaksanaan]     TIME NOT NULL,
    [metode]              NVARCHAR(20) NOT NULL
                          CONSTRAINT CHK_jadwal_metode CHECK ([metode] IN ('video_call_wa', 'zoom_meeting')),
    [meeting_id]          NVARCHAR(30) NULL,
    [passcode]            NVARCHAR(30) NULL,
    [slot_waktu]          NVARCHAR(15) NOT NULL
                          CONSTRAINT CHK_jadwal_slot CHECK ([slot_waktu] IN ('10.00-12.00', '14.00-15.00')),
    [nomor_wa]            NVARCHAR(20) NOT NULL,
    [nama_keluarga]       NVARCHAR(100) NULL,
    [status_kirim_wa]     NVARCHAR(20) NULL DEFAULT 'pending',
    [jadwal_kirim_wa]     NVARCHAR(30) NULL,
    [pesan_wa]            NVARCHAR(MAX) NULL,
    [dibuat_oleh]         NVARCHAR(100) NULL,
    [created_at]          DATETIME2 NOT NULL DEFAULT SYSDATETIME(),
    [updated_at]          DATETIME2 NOT NULL DEFAULT SYSDATETIME()
);
CREATE INDEX [idx_jadwal_pasien] ON [dbo].[tbl_jadwal_fpe] ([id_pasien]);
```

### B. Tabel Antrean Notifikasi WhatsApp
```sql
CREATE TABLE [dbo].[tbl_wa_queue] (
    [id]                  INT IDENTITY(1,1) PRIMARY KEY,
    [id_jadwal]           INT NOT NULL,
    [nomor_tujuan]        NVARCHAR(20) NOT NULL,
    [tipe_notifikasi]     NVARCHAR(30) NOT NULL DEFAULT 'FPE_REMINDER',
    -- Tipe: 'FPE_REMINDER' (otomatis H-1), 'FPE_MANUAL' (kirim manual sekarang)
    [pesan]               NVARCHAR(MAX) NULL,
    [scheduled_at]        DATETIME2 NOT NULL,
    [status]              NVARCHAR(20) NOT NULL DEFAULT 'pending' 
                          CONSTRAINT CHK_wa_queue_status CHECK ([status] IN ('pending', 'processing', 'sent', 'failed', 'cancelled')),
    [attempts]            INT NOT NULL DEFAULT 0,
    [max_attempts]        INT NOT NULL DEFAULT 3,
    [locked_at]           DATETIME2 NULL,
    [locked_by]           NVARCHAR(100) NULL,
    [sent_at]             DATETIME2 NULL,
    [provider_message_id] NVARCHAR(255) NULL,
    [last_error]          NVARCHAR(MAX) NULL,
    [created_at]          DATETIME2 NOT NULL DEFAULT SYSDATETIME(),
    [updated_at]          DATETIME2 NOT NULL DEFAULT SYSDATETIME(),
    CONSTRAINT [UQ_wa_queue_jadwal_tipe] UNIQUE ([id_jadwal], [tipe_notifikasi]),
    CONSTRAINT [FK_wa_queue_jadwal] FOREIGN KEY ([id_jadwal])
        REFERENCES [dbo].[tbl_jadwal_fpe]([id_jadwal]) ON DELETE CASCADE
);
CREATE INDEX [IX_wa_queue_due] ON [dbo].[tbl_wa_queue] ([status], [scheduled_at]);
```

### C. Tabel Dokumentasi FPE
```sql
CREATE TABLE [dbo].[tbl_dokumentasi_fpe] (
    [id_dokumentasi]         INT IDENTITY(1,1) PRIMARY KEY,
    [id_jadwal]              INT NULL,
    [id_pasien]              INT NOT NULL,
    [asesmen]                NVARCHAR(MAX) NULL,
    [hubungan_dengan_pasien] NVARCHAR(500) NULL,
    -- ⚠️ Pilihan ganda (Multiple Choice): menyimpan nilai comma-separated
    -- Contoh isi: 'ayah,ibu,kakak' atau 'suami,lain_lain'
    -- Nilai valid per item: ayah, ibu, suami, istri, anak, kakak, adik, kakek, nenek, lain_lain
    [hubungan_lainnya]       NVARCHAR(100) NULL,   -- Diisi jika 'lain_lain' tercentang
    [hasil_fpe]              NVARCHAR(MAX) NULL,
    [kemampuan_pasien]       NVARCHAR(MAX) NULL,
    [kemampuan_keluarga]     NVARCHAR(MAX) NULL,
    [nama_ppa]               NVARCHAR(100) NULL,
    [tanda_tangan_ppa]       NVARCHAR(255) NULL,
    [created_at]             DATETIME2 NOT NULL DEFAULT SYSDATETIME(),
    CONSTRAINT [FK_dok_jadwal] FOREIGN KEY ([id_jadwal])
        REFERENCES [dbo].[tbl_jadwal_fpe]([id_jadwal]) ON DELETE SET NULL
);
CREATE INDEX [idx_dok_pasien] ON [dbo].[tbl_dokumentasi_fpe] ([id_pasien]);
```

### D. Tabel Master Kegiatan Harian (dengan Seed Data)
```sql
CREATE TABLE [dbo].[tbl_master_kegiatan] (
    [id_kegiatan]   INT IDENTITY(1,1) PRIMARY KEY,
    [waktu]         TIME NOT NULL,
    [nama_kegiatan] NVARCHAR(100) NOT NULL,
    [urutan]        INT NOT NULL DEFAULT 0
);

-- Seed data kegiatan harian standar RSKD Duren Sawit
INSERT INTO [dbo].[tbl_master_kegiatan] ([waktu], [nama_kegiatan], [urutan]) VALUES
('06:00:00', N'Patuh Obat 5 Benar', 1),
('08:00:00', N'Menghardik', 2),
('08:30:00', N'Olah raga', 3),
('09:00:00', N'Ngobrol dengan teman/keluarga', 4),
('10:00:00', N'Nonton TV / kegiatan', 5),
('12:00:00', N'Patuh Obat 5 Benar', 6),
('13:00:00', N'Sholat / ibadah', 7),
('16:00:00', N'Mandi dan berhias', 8),
('16:30:00', N'Teknik relaksasi nafas dalam', 9),
('17:00:00', N'Merapikan TT', 10),
('18:00:00', N'Patuh Obat 5 Benar', 11);
```

### E. Tabel Realisasi Kegiatan Harian Pasien
```sql
CREATE TABLE [dbo].[tbl_kegiatan_pasien] (
    [id]             INT IDENTITY(1,1) PRIMARY KEY,
    [id_pasien]      INT NOT NULL,
    [id_kegiatan]    INT NOT NULL,
    [hari_ke]        TINYINT NOT NULL,
    [tanggal]        DATE NOT NULL,
    [status_centang] BIT NOT NULL DEFAULT 0,
    CONSTRAINT [UQ_kegiatan_pasien] UNIQUE ([id_pasien], [id_kegiatan], [tanggal]),
    CONSTRAINT [FK_kp_kegiatan] FOREIGN KEY ([id_kegiatan])
        REFERENCES [dbo].[tbl_master_kegiatan]([id_kegiatan])
);
CREATE INDEX [idx_kp_pasien] ON [dbo].[tbl_kegiatan_pasien] ([id_pasien]);
```

### F. Tabel Skrining Risiko Bunuh Diri
```sql
CREATE TABLE [dbo].[tbl_skrining_risiko_bunuh_diri] (
    [id_skrining]           INT IDENTITY(1,1) PRIMARY KEY,
    [id_pasien]             INT NOT NULL,
    [tanggal_datang]        DATE NOT NULL,
    [jam_datang]            TIME NOT NULL,
    [status_pasien]         NVARCHAR(20) NOT NULL
                            CONSTRAINT CHK_skr_status CHECK ([status_pasien] IN ('lama', 'baru')),
    [rujukan]               NVARCHAR(10) NOT NULL
                            CONSTRAINT CHK_skr_rujukan CHECK ([rujukan] IN ('ya', 'tidak')),
    [rujukan_dari]          NVARCHAR(100) NULL,
    [disabilitas]           NVARCHAR(20) NOT NULL
                            CONSTRAINT CHK_skr_disabilitas CHECK ([disabilitas] IN ('ada', 'tidak_ada')),
    [diagnosis]             NVARCHAR(150) NULL,
    [keluhan_saat_ini]      NVARCHAR(MAX) NULL,
    [pertanyaan_1]          NVARCHAR(20) NULL
                            CONSTRAINT CHK_skr_p1 CHECK ([pertanyaan_1] IN ('ya','tidak','menyangkal','tidak_menjawab')),
    [pertanyaan_2]          NVARCHAR(20) NULL
                            CONSTRAINT CHK_skr_p2 CHECK ([pertanyaan_2] IN ('ya','tidak','menyangkal','tidak_menjawab')),
    [pertanyaan_3]          NVARCHAR(20) NULL
                            CONSTRAINT CHK_skr_p3 CHECK ([pertanyaan_3] IN ('ya','tidak','menyangkal','tidak_menjawab')),
    [pertanyaan_3a]         NVARCHAR(30) NULL
                            CONSTRAINT CHK_skr_p3a CHECK ([pertanyaan_3a] IN (
                                'dalam_24jam','dalam_bulan_terakhir','1_6bulan',
                                'lebih_6bulan','menyangkal','tidak_menjawab')),
    [hasil_skoring]         NVARCHAR(100) NULL,
    [lokasi]                NVARCHAR(20) NOT NULL DEFAULT 'poli'
                            CONSTRAINT CHK_skr_lokasi CHECK ([lokasi] IN ('igd', 'poli')),
    [nama_petugas_skrining] NVARCHAR(100) NULL,
    [created_at]            DATETIME2 NOT NULL DEFAULT SYSDATETIME()
);
CREATE INDEX [idx_skrining_pasien] ON [dbo].[tbl_skrining_risiko_bunuh_diri] ([id_pasien]);
```

### G. Persyaratan Tabel Pasien di Proyek Utama
Tabel pasien di proyek utama Anda **harus memiliki kolom `tanggal_lahir`** agar Form Skrining dapat menampilkan umur pasien secara presisi. Jika belum ada:
```sql
-- Jalankan ini di tabel pasien proyek utama Anda
ALTER TABLE [dbo].[tabel_pasien_anda] ADD [tanggal_lahir] DATE NULL;
```

---

## 4. Deskripsi Fitur Setiap Form

### 4.1 Form 1: Penjadwalan FPE & WhatsApp (`form_jadwal_fpe.php`)
- **Input**: Tanggal, Jam, Metode (Video Call WA / Zoom Meeting), Slot Waktu, Nomor WhatsApp Keluarga, Nama Keluarga, Pengingat Otomatis H-N hari, Opsi Kirim Manual Langsung.
- **Fitur Otomatis**: Saat disimpan, pengingat WhatsApp otomatis dijadwalkan (default H-1 pukul 09:00 WIB).
- **Fitur Manual**: Toggle "Langsung Kirim Notifikasi WhatsApp Sekarang (Manual)".
- **Tabel Riwayat**: Menampilkan riwayat jadwal dengan status pengiriman WA, waktu terkirim, dan kolom **Aksi** berisi tombol:
  - **"Kirim Manual Sekarang"**: Mengirim notifikasi seketika.
  - **"Kirim Ulang"**: Untuk jadwal yang sudah pernah terkirim.
- **Mekanisme Anti Pesan Ganda**: Saat pesan dikirim manual, seluruh pengingat otomatis pending untuk jadwal yang sama otomatis dibatalkan (`status = 'cancelled'`) dengan keterangan `'Otomatis gugur karena telah dikirim secara manual'`.
- **Template Pesan Resmi**: Pesan WhatsApp menggunakan template resmi RSKD Duren Sawit dengan format terstruktur (emoji, garis pembatas, nama hari Indonesia, catatan penting).

### 4.2 Form 2: Dokumentasi FPE (`form_dokumentasi_fpe.php`)
- **Input**: Jadwal FPE Terkait (opsional), Hubungan dengan Pasien (**pilihan ganda checkbox**), Asesmen, Hasil FPE, Kemampuan Pasien, Kemampuan Keluarga.
- **Hubungan Keluarga**: Input berupa **checkbox pilihan ganda** (bukan dropdown) — petugas dapat mencentang lebih dari satu anggota keluarga yang hadir dalam sesi FPE: Ayah, Ibu, Suami, Istri, Anak, Kakak, Adik, Kakek, Nenek, Lain-lain.
- **Data Tersimpan**: Nilai disimpan sebagai string comma-separated di kolom `hubungan_dengan_pasien` (contoh: `'ayah,ibu,kakak'`).
- **Tabel Riwayat**: Menampilkan keluarga yang hadir sebagai badge warna biru per anggota.

### 4.3 Form 3: Jadwal Kegiatan Pasien (`form_kegiatan_pasien.php`)
- **Input**: Tanggal Mulai Periode (Hari I), Grid centang 10 hari × N kegiatan dari tabel master.
- **Fitur**: Saat memilih tanggal baru, header tabel otomatis menampilkan tanggal yang sesuai (Hari I = tanggal terpilih, Hari II = tanggal berikutnya, dst.).
- **Penyimpanan**: Menggunakan `MERGE` statement SQL Server untuk upsert data checklist.

### 4.4 Form 4: Skrining Risiko Bunuh Diri (`form_skrining_bunuh_diri.php`)
- **Panel Info Pasien**: Di bagian atas menampilkan Nama Pasien, Tanggal Lahir (format Indonesia), dan **Umur Pasien (X Tahun Y Bulan Z Hari)** dalam badge biru kontras.
- **Input**: Tanggal Datang, Jam Datang, Status Pasien, Rujukan, Disabilitas, Diagnosis, Keluhan Saat Ini, 3 Pertanyaan Skrining + sub-pertanyaan 3a.
- **Asal Pasien (Lokasi Skrining)**: Radio button pilihan **IGD** (Instalasi Gawat Darurat) atau **Poli** (Poliklinik) diposisikan di bagian **bawah sebelum tombol submit**.
- **Skoring Otomatis**:
  - Pertanyaan 1 = Ya → "Depresi"
  - Pertanyaan 2 = Ya atau Pertanyaan 3 = Ya → "Risiko Bunuh Diri"
  - Semua Tidak → "Tidak Berisiko"
- **Tabel Riwayat**: Menampilkan Tanggal & Jam, Asal Pasien (badge IGD/Poli), hasil skoring berwarna (hijau/kuning/merah), status pasien & rujukan, diagnosis & keluhan, serta nama petugas.

---

## 5. Daftar Fungsi Helper yang Tersedia

### `helpers.php` — Fungsi Utilitas Umum

| Fungsi | Parameter | Return | Keterangan |
|--------|-----------|--------|------------|
| `normalizePhoneNumber($phone)` | `string` | `string\|false` | Normalisasi nomor HP ke format `628xxx`. Return `false` jika tidak valid. |
| `calculateScheduledAt($tgl, $hariSebelum, $jam)` | `string, int, string` | `string` | Hitung waktu kirim WA (H-N, default H-1 pukul 09:00). Jika sudah lewat, return *due-now*. |
| `formatTanggalIndo($date, $withDay)` | `string\|DateTime, bool` | `string` | Format tanggal ke Bahasa Indonesia. Contoh: `"Selasa, 19 Agustus 2026"` atau `"19 Agustus 2026"`. |
| `hitungUmurLengkap($tglLahir, $tglRujukan)` | `string\|DateTime\|null, string\|null` | `array` | Kalkulasi umur presisi. Return: `['tahun' => 41, 'bulan' => 4, 'hari' => 7, 'teks' => '41 Tahun 4 Bulan 7 Hari']`. |

### `wa_queue.php` — Fungsi Antrean WhatsApp

| Fungsi | Parameter | Return | Keterangan |
|--------|-----------|--------|------------|
| `buildFpeReminderMessage($data)` | `array` | `string` | Menyusun pesan WA resmi RSKD Duren Sawit dengan template terstruktur (emoji, garis, nama hari). |
| `createWaQueueJob($conn, $idJadwal, $nomor, $pesan, $scheduledAt, $tipe)` | `resource, int, string, string, string, string` | `int` | Insert job ke `tbl_wa_queue` dan return ID job yang dibuat. |

---

## 6. Contoh Pemanggilan Form di Halaman Utama

Berikut adalah contoh bagaimana meng-`include` formulir ke dalam file view/halaman detail pasien di aplikasi utama:

```php
<?php
// =====================================================================
// CONTOH: detail_pasien.php di Aplikasi Utama
// =====================================================================

// 1. Dapatkan koneksi database sqlsrv yang sudah ada di aplikasi utama
$conn = $koneksi_db_aplikasi; 

// 2. Tentukan ID Pasien aktif dari URL atau Session
$id_pasien = (int)$_GET['id_pasien'];

// 3. Tentukan nama petugas yang sedang login
$nama_petugas = $_SESSION['user_nama'] ?? 'Petugas Ruangan';

// 4. Pastikan Bootstrap 5 & Bootstrap Icons sudah dimuat di template utama
?>
<!DOCTYPE html>
<html>
<head>
    <!-- Template Utama Aplikasi Anda -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body>

<div class="container my-4">
    <h3>Rekam Medis Pasien: <?= htmlspecialchars($dataPasien['nama']) ?></h3>

    <!-- Include Form Penjadwalan FPE & WhatsApp -->
    <?php include 'app/forms/form_jadwal_fpe.php'; ?>

    <!-- Include Form Dokumentasi FPE -->
    <?php include 'app/forms/form_dokumentasi_fpe.php'; ?>

    <!-- Include Form Kegiatan Harian (10 Hari) -->
    <?php include 'app/forms/form_kegiatan_pasien.php'; ?>

    <!-- Include Form Skrining Bunuh Diri (dengan info umur pasien otomatis) -->
    <?php include 'app/forms/form_skrining_bunuh_diri.php'; ?>
</div>

</body>
</html>
```

> **Catatan untuk Form Skrining**: Form ini secara otomatis mengambil `nama_pasien` dan `tanggal_lahir` dari tabel pasien menggunakan `$id_pasien`. Pastikan nama tabel dan kolom pada query di dalam file disesuaikan jika berbeda dari `tbl_pasien`.

---

## 7. Menjalankan Node.js Worker di Server Produksi

Worker Node.js berjalan sebagai proses latar belakang (*background service*) yang terpisah dari web server Apache/IIS. Worker ini bertugas:
1. **Polling** tabel `tbl_wa_queue` untuk mencari pesan yang sudah jatuh tempo (`status = 'pending'` dan `scheduled_at <= NOW`).
2. **Mengirim** pesan WhatsApp via provider yang dikonfigurasi (Baileys untuk dev, Cloud API untuk produksi).
3. **Menyinkronkan** status pengiriman ke `tbl_jadwal_fpe` dan membatalkan antrean duplikat.

### Cara Menjalankan:
1. Salin folder `node/` ke server (tanpa `node_modules/` dan `auth_info/`).
2. Buka terminal/PowerShell di folder `node/`:
   ```bash
   npm install
   ```
3. Konfigurasi file `.env` di folder `node/` (salin dari `.env.example`):
   ```env
   # Database SQL Server
   DB_SERVER=localhost
   DB_INSTANCE=SQLEXPRESS
   DB_DATABASE=nama_database_utama
   DB_TRUST_SERVER_CERTIFICATE=true

   # Provider WhatsApp
   WHATSAPP_PROVIDER=baileys   # atau cloud_api untuk produksi

   # Konfigurasi Polling & Antrean
   QUEUE_POLL_INTERVAL_MS=15000
   QUEUE_PROCESSING_TIMEOUT_MINUTES=10
   WA_MAX_ATTEMPTS=3

   # Health Check
   HEALTH_PORT=3001
   TZ=Asia/Jakarta
   ```
4. Jalankan worker:
   ```bash
   npm start
   ```
5. Pada pertama kali menggunakan provider Baileys, scan QR Code yang muncul di terminal menggunakan WhatsApp di HP untuk menghubungkan sesi.

### Rekomendasi Menjalankan Sebagai Windows Service (Opsional untuk Produksi):
Gunakan **PM2** agar worker otomatis restart jika server menyala ulang:
```bash
npm install -g pm2
pm2 start src/worker.js --name "fpe-wa-worker"
pm2 startup
pm2 save
```

---

## 8. Alur Pengiriman WhatsApp (Ringkasan Teknis)

```
┌──────────────────────────────────┐
│  Petugas menyimpan jadwal FPE    │
│  via form_jadwal_fpe.php         │
└────────────┬─────────────────────┘
             │
             ▼
┌──────────────────────────────────┐
│  PHP menyimpan ke tbl_jadwal_fpe │
│  + insert job ke tbl_wa_queue    │
│  (tipe: FPE_REMINDER,            │
│   scheduled_at: H-1 pukul 09:00) │
└────────────┬─────────────────────┘
             │                        ┌─────────────────────────────────┐
             │  ATAU                  │  Petugas menekan "Kirim Manual" │
             │                        │  → job diubah ke FPE_MANUAL     │
             │                        │  → scheduled_at = NOW           │
             │                        │  → pending otomatis digugurkan  │
             │                        └──────────┬──────────────────────┘
             ▼                                   ▼
┌──────────────────────────────────────────────────┐
│  Node.js Worker (polling setiap 15 detik)        │
│  ↳ Ambil job: status='pending', scheduled_at<=NOW│
│  ↳ Kirim via Baileys / Cloud API                 │
│  ↳ Update status → 'sent', sinkron tbl_jadwal    │
│  ↳ Batalkan pending duplikat untuk id_jadwal sama │
└──────────────────────────────────────────────────┘
```

---

## 9. Checklist Integrasi

Gunakan checklist berikut untuk memastikan integrasi berjalan lancar:

- [ ] Salin 4 file form + 2 file helper ke lokasi yang sesuai di proyek utama
- [ ] Sesuaikan path `require_once` di dalam file form
- [ ] Jalankan skrip SQL Server (Bagian 3A–3F) di database proyek utama
- [ ] Pastikan tabel pasien proyek utama memiliki kolom `tanggal_lahir` (untuk Form Skrining)
- [ ] Sesuaikan nama tabel pasien di `form_skrining_bunuh_diri.php` jika berbeda dari `tbl_pasien`
- [ ] Pastikan Bootstrap 5 dan Bootstrap Icons sudah dimuat di template halaman induk
- [ ] Sediakan variabel `$conn`, `$id_pasien`, dan `$nama_petugas` sebelum `include`
- [ ] Salin folder `node/` dan konfigurasi `.env` untuk worker WhatsApp
- [ ] Jalankan `npm install` dan `npm start` di folder worker
- [ ] Scan QR Code WhatsApp (jika menggunakan provider Baileys)
- [ ] Uji simpan jadwal FPE, kirim manual, dan verifikasi pesan terkirim di WhatsApp
