# Rencana Perbaikan (Remediation Plan) — Sistem FPE & Worker WhatsApp

Dokumen rencana perbaikan berdasarkan temuan pada terminal Node.js dan penyesuaian arsitektur tabel pasien.

---

## 1. Analisis Masalah

### Masalah A: Error pada Worker Node.js (`Invalid column name 'updated_at'`)
* **Penyebab**: Pada fungsi `claimDueJobs` di [node/src/queue.js](file:///c:/Users/RSKD%20Duren%20Sawit/Downloads/php_forms/node/src/queue.js), klausa `WITH cte AS (...)` melakukan `UPDATE cte SET status = 'processing', locked_at = ..., locked_by = ..., updated_at = ...`. Namun, pada bagian `SELECT` di dalam CTE, kolom `status`, `locked_at`, `locked_by`, dan `updated_at` belum disertakan dalam daftar proyeksi kolom. SQL Server mewajibkan semua kolom yang di-`UPDATE` melalui CTE harus ada dalam `SELECT` CTE tersebut.
* **Solusi**: Menyertakan kolom `status`, `locked_at`, `locked_by`, dan `updated_at` ke dalam `SELECT` CTE di [node/src/queue.js](file:///c:/Users/RSKD%20Duren%20Sawit/Downloads/php_forms/node/src/queue.js).

---

### Masalah B: Penyederhanaan `tbl_pasien` & Pemisahan Data Dashboard
* **Permintaan Pengguna**: Tabel pasien hanya boleh menyimpan `id_pasien` dan `nama_pasien`. Data lain (nomor WA, nama keluarga, riwayat kegiatan, skrining, dokumentasi) yang ditampilkan di dashboard harus diambil dari tabel *dedicated* masing-masing.
* **Solusi**:
  1. Skema `tbl_pasien` disederhanakan menjadi:
     ```sql
     CREATE TABLE tbl_pasien (
         id_pasien   INT IDENTITY(1,1) PRIMARY KEY,
         nama_pasien NVARCHAR(100) NOT NULL,
         created_at  DATETIME2 NOT NULL DEFAULT SYSDATETIME()
     );
     ```
  2. Data pasien dummy yang disediakan untuk pengujian:
     - `ID 123`: `Ny. Aisyah` (memiliki 7 data riwayat jadwal di `tbl_jadwal_fpe`)
     - `ID 1`: `Budi Santoso`
     - `ID 2`: `Dewi Sartika`
  3. Pada [php/index.php](file:///c:/Users/RSKD%20Duren%20Sawit/Downloads/php_forms/php/index.php), informasi kontak keluarga dan nomor WA di bilah atas diambil dari data jadwal terakhir di `tbl_jadwal_fpe` untuk pasien tersebut (jika ada), bukan dari `tbl_pasien`.

---

### Masalah C: Tampilan Riwayat Jadwal Pasien
* **Penyebab**: Kolom nomor WhatsApp pada tabel `tbl_jadwal_fpe` di SQL Server bernama `nomor_wa`. Query riwayat sebelumnya sempat memanggil `nomor_wa_keluarga`.
* **Solusi**: Query pada [php/form_jadwal_fpe.php](file:///c:/Users/RSKD%20Duren%20Sawit/Downloads/php_forms/php/form_jadwal_fpe.php) disesuaikan secara konsisten ke kolom `nomor_wa` dan mengambil status antrean dari `tbl_wa_queue` dengan fallback ke `status_kirim_wa` untuk data lama.

---

## 2. Rincian Perubahan yang Diusulkan

### Komponen 1: Node.js Queue Worker
#### [MODIFY] [node/src/queue.js](file:///c:/Users/RSKD%20Duren%20Sawit/Downloads/php_forms/node/src/queue.js)
- Perbaiki query CTE `claimDueJobs`:
  ```javascript
  WITH cte AS (
      SELECT TOP (@batchSize) 
          id, id_jadwal, nomor_tujuan, pesan, attempts, max_attempts,
          status, locked_at, locked_by, updated_at
      FROM tbl_wa_queue WITH (UPDLOCK, READPAST)
      WHERE status = 'pending' 
        AND scheduled_at <= SYSDATETIME()
      ORDER BY scheduled_at ASC
  )
  UPDATE cte
  SET status = 'processing',
      locked_at = SYSDATETIME(),
      locked_by = @workerId,
      updated_at = SYSDATETIME()
  OUTPUT 
      INSERTED.id, 
      INSERTED.id_jadwal, 
      INSERTED.nomor_tujuan, 
      INSERTED.pesan, 
      INSERTED.attempts, 
      INSERTED.max_attempts;
  ```

---

### Komponen 2: Database Schema & Seed Data Pasien
#### [MODIFY] [database/sqlserver.sql](file:///c:/Users/RSKD%20Duren%20Sawit/Downloads/php_forms/database/sqlserver.sql)
- Sederhanakan `tbl_pasien` hanya menyimpan `id_pasien` dan `nama_pasien`.
- Pastikan pasien dummy mencakup ID 123, ID 1, dan ID 2.

---

### Komponen 3: Form Penjadwalan FPE & Riwayat
#### [MODIFY] [php/form_jadwal_fpe.php](file:///c:/Users/RSKD%20Duren%20Sawit/Downloads/php_forms/php/form_jadwal_fpe.php)
- Hanya membaca `nama_pasien` dari `tbl_pasien`.
- Mengisi nomor WA default form dari jadwal terakhir pasien tersebut di `tbl_jadwal_fpe`.
- Memastikan query riwayat menampilkan semua catatan jadwal pasien yang dipilih (`id_pasien`).

---

### Komponen 4: Dashboard Test Harness
#### [MODIFY] [php/index.php](file:///c:/Users/RSKD%20Duren%20Sawit/Downloads/php_forms/php/index.php)
- Header pasien hanya menampilkan nama pasien dari `tbl_pasien`.
- Jika ingin menampilkan kontak terakhir, query mengambil langsung dari `tbl_jadwal_fpe` (tabel dedicated).
- Dropdown pemilih pasien memuat ID 123, 1, dan 2.

---

## 3. Rencana Verifikasi

1. **Uji Query CTE Node.js**: Jalankan worker Node.js dan pastikan log tidak lagi menghasilkan error `Invalid column name 'updated_at'`.
2. **Uji Tampilan Riwayat di Browser**:
   - Buka `http://localhost:8080` (Pasien 123 terpilih).
   - Pastikan **7 data riwayat jadwal** langsung tampil di tabel riwayat.
3. **Uji Pengiriman WhatsApp Cepat**:
   - Masukkan antrean pengujian pada Tab 5.
   - Pastikan worker mengklaim antrean dan mengirimkan pesan WhatsApp via Baileys ke HP target.
