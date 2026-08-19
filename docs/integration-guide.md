# PANDUAN INTEGRASI KE MAIN PROJECT
## Cara Menyalin & Menggabungkan Komponen Form FPE ke Aplikasi Utama

Dokumen ini menjelaskan langkah demi langkah cara memindahkan 4 komponen formulir FPE dan worker WhatsApp dari proyek pengujian mandiri (*standalone*) ini ke dalam aplikasi utama (*main project*) yang sudah ada.

---

## 1. Ringkasan Arsitektur Komponen

Setiap formulir PHP didesain sebagai **komponen independen (`include`-ready)**. Komponen tidak memuat header, footer, sidebar, atau styling Bootstrap global, melainkan hanya merender satu elemen `<div class="card">...</div>`.

### Kontrak Variabel yang Diperlukan
Sebelum meng-`include` file form apa pun, halaman induk di proyek utama **hanya perlu menyediakan 2-3 variabel**:

```php
$conn         // Resource koneksi aktif SQL Server (hasil dari sqlsrv_connect)
$id_pasien    // Integer ID pasien yang sedang aktif/dibuka
$nama_petugas // String nama petugas yang sedang login (opsional, default: 'Petugas')
```

---

## 2. Daftar Berkas yang Harus & Tidak Boleh Disalin

### ✅ Berkas yang DISALIN ke Proyek Utama:

| Berkas Sumber | Lokasi di Proyek Utama (Rekomendasi) | Keterangan |
|---------------|-------------------------------------|------------|
| `php/form_jadwal_fpe.php` | `app/forms/form_jadwal_fpe.php` | Form penjadwalan FPE & antrean WA otomatis |
| `php/form_dokumentasi_fpe.php` | `app/forms/form_dokumentasi_fpe.php` | Form bukti dokumentasi hasil FPE |
| `php/form_kegiatan_pasien.php` | `app/forms/form_kegiatan_pasien.php` | Form checklist kegiatan harian (10 hari) |
| `php/form_skrining_bunuh_diri.php` | `app/forms/form_skrining_bunuh_diri.php` | Form skrining risiko bunuh diri |
| `php/includes/helpers.php` | `app/includes/fpe_helpers.php` | Fungsi helper normalisasi no HP & tanggal |
| `php/includes/wa_queue.php` | `app/includes/fpe_wa_queue.php` | Helper insert antrean WA |
| `node/` *(seluruh folder)* | `services/wa-worker/` atau root terpisah | Worker latar belakang pengirim WhatsApp |

### ❌ Berkas yang JANGAN Disalin:

| Berkas | Alasan |
|--------|--------|
| `php/index.php` | Hanya *test harness* pengujian lokal. Proyek utama sudah punya tampilan/layout sendiri. |
| `tbl_pasien` di `sqlserver.sql` | Tabel dummy untuk pengujian. Proyek utama sudah punya tabel pasien sendiri. |

---

## 3. Langkah Migrasi Database (SQL Server)

Buka SQL Server Management Studio (SSMS) pada database proyek utama Anda, lalu jalankan skrip berikut (diambil dari `database/sqlserver.sql`):

### A. Tabel Jadwal FPE & Antrean WhatsApp
```sql
-- 1. Tabel Jadwal FPE
CREATE TABLE tbl_jadwal_fpe (
    id_jadwal           INT IDENTITY(1,1) PRIMARY KEY,
    id_pasien           INT NOT NULL, -- Sesuaikan tipe dengan PK tabel pasien Anda jika berbeda
    tanggal_pelaksanaan DATE NOT NULL,
    jam_pelaksanaan     TIME NOT NULL,
    metode              NVARCHAR(20) NOT NULL CHECK (metode IN ('video_call_wa', 'zoom_meeting')),
    meeting_id          NVARCHAR(30) NULL,
    passcode            NVARCHAR(30) NULL,
    slot_waktu          NVARCHAR(15) NOT NULL CHECK (slot_waktu IN ('10.00-12.00', '14.00-15.00')),
    nomor_wa_keluarga   NVARCHAR(20) NOT NULL,
    nama_keluarga       NVARCHAR(100) NULL,
    dibuat_oleh         NVARCHAR(100) NULL,
    created_at          DATETIME2 NOT NULL DEFAULT SYSDATETIME(),
    updated_at          DATETIME2 NOT NULL DEFAULT SYSDATETIME()
);
CREATE INDEX idx_jadwal_pasien ON tbl_jadwal_fpe (id_pasien);

-- 2. Tabel Antrean Notifikasi WhatsApp
CREATE TABLE tbl_wa_queue (
    id                  INT IDENTITY(1,1) PRIMARY KEY,
    id_jadwal           INT NOT NULL,
    nomor_tujuan        NVARCHAR(20) NOT NULL,
    tipe_notifikasi     NVARCHAR(30) NOT NULL DEFAULT 'FPE_REMINDER',
    pesan               NVARCHAR(MAX) NULL,
    scheduled_at        DATETIME2 NOT NULL,
    status              NVARCHAR(20) NOT NULL DEFAULT 'pending' 
                        CHECK (status IN ('pending', 'processing', 'sent', 'failed', 'cancelled')),
    attempts            INT NOT NULL DEFAULT 0,
    max_attempts        INT NOT NULL DEFAULT 3,
    locked_at           DATETIME2 NULL,
    locked_by           NVARCHAR(100) NULL,
    sent_at             DATETIME2 NULL,
    provider_message_id NVARCHAR(255) NULL,
    last_error          NVARCHAR(MAX) NULL,
    created_at          DATETIME2 NOT NULL DEFAULT SYSDATETIME(),
    updated_at          DATETIME2 NOT NULL DEFAULT SYSDATETIME(),
    CONSTRAINT UQ_wa_queue_jadwal_tipe UNIQUE (id_jadwal, tipe_notifikasi),
    CONSTRAINT FK_wa_queue_jadwal FOREIGN KEY (id_jadwal) REFERENCES tbl_jadwal_fpe(id_jadwal) ON DELETE CASCADE
);
CREATE INDEX IX_wa_queue_due ON tbl_wa_queue (status, scheduled_at);
```

### B. Tabel Dokumentasi, Master Kegiatan, & Skrining
Jalankan bagian tabel dokumentasi, master kegiatan (+ seed data), kegiatan pasien, dan skrining risiko bunuh diri sesuai kebutuhan dari berkas `database/sqlserver.sql`.

---

## 4. Contoh Pemanggilan Form di Halaman Utama

Berikut adalah contoh bagaimana meng-`include` formulir ke dalam file view/halaman detail pasien di aplikasi utama:

```php
<?php
// =====================================================================
// CONTOH: detail_pasien.php di Aplikasi Utama Bos Anda
// =====================================================================

// 1. Dapatkan koneksi database sqlsrv yang sudah ada di aplikasi utama
// Misal aplikasi utama sudah mendefinisikan $conn via sqlsrv_connect:
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

    <!-- Include Form Penjadwalan FPE -->
    <?php include 'app/forms/form_jadwal_fpe.php'; ?>

    <!-- Include Form Dokumentasi FPE -->
    <?php include 'app/forms/form_dokumentasi_fpe.php'; ?>

    <!-- Include Form Kegiatan Harian -->
    <?php include 'app/forms/form_kegiatan_pasien.php'; ?>

    <!-- Include Form Skrining Bunuh Diri -->
    <?php include 'app/forms/form_skrining_bunuh_diri.php'; ?>
</div>

</body>
</html>
```

---

## 5. Menjalankan Node.js Worker di Server Produksi

Worker Node.js berjalan sebagai proses latar belakang (*background service*) yang terpisah dari web server Apache/IIS.

### Cara Menjalankan:
1. Salin folder `node/` ke server.
2. Buka terminal/PowerShell di folder `node/`:
   ```bash
   npm install
   ```
3. Konfigurasi file `.env` di folder `node/`:
   ```env
   DB_SERVER=localhost
   DB_INSTANCE=SQLEXPRESS
   DB_DATABASE=nama_database_utama
   WHATSAPP_PROVIDER=baileys # atau cloud_api
   QUEUE_POLL_INTERVAL_MS=30000
   ```
4. Jalankan worker:
   ```bash
   npm start
   ```

### Rekomendasi Menjalankan Sebagai Windows Service (Opsional untuk Produksi):
Gunakan **PM2** agar worker otomatis restart jika server menyala ulang:
```bash
npm install -g pm2
pm2 start src/worker.js --name "fpe-wa-worker"
pm2 startup
pm2 save
```
