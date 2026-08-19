# ARSITEKTUR SISTEM FORMULIR FPE & NOTIFIKASI WHATSAPP
## RSKD Duren Sawit

---

## 1. Diagram Alur Keseluruhan Sistem

```text
┌─────────────────────────────────────────────────────────────┐
│                       PENGGUNA / BROWSER                    │
│    Petugas membuka form FPE dan menyimpan jadwal / data     │
└──────────────────────────────┬──────────────────────────────┘
                               │ HTTP POST
                               ▼
┌─────────────────────────────────────────────────────────────┐
│                    NATIVE PHP (BOOTSTRAP 5)                 │
│  - Validasi input & normalisasi nomor WA (standar 628xxx)   │
│  - Transaksi Atomik Database (sqlsrv direct driver)         │
│  - Hitung waktu pengingat otomatis (H-1 pukul 09:00 WIB)    │
│  - Opsi: Kirim Manual Langsung (Due-Now)                    │
│  - Form 2: Pilihan Ganda (Multiple Choice) Keluarga Hadir   │
│  - Form 3: Grid Checklist Kegiatan 10 Hari Adaptif          │
│  - Form 4: Kalkulasi Umur Pasien (Tahun, Bulan, Hari)       │
└──────────────────────────────┬──────────────────────────────┘
                               │
                ┌───────────────┴───────────────┐
                │ Transaksi Database SQL Server │
                ▼                               ▼
     ┌────────────────────┐          ┌──────────────────────┐
     │   tbl_jadwal_fpe   │          │     tbl_wa_queue     │
     │  (Data Jadwal FPE) │          │ (Antrean Notifikasi) │
     └────────────────────┘          └──────────┬───────────┘
                                                │
                                                │ Polling berkala (setiap 15 detik)
                                                │ Safe Claim: CTE + UPDLOCK, READPAST
                                                ▼
┌─────────────────────────────────────────────────────────────┐
│                    NODE.JS QUEUE WORKER                     │
│  - Background process independen berbasis Node.js           │
│  - Klaim antrean due (status='pending', scheduled_at<=NOW)  │
│  - Eksekusi atomic lock (status='processing', locked_by)    │
│  - Pemulihan antrean macet (Stale Processing Recovery)      │
│  - Retry backoff jika gagal (maksimal 3x percobaan)         │
│  - Sinkronisasi status 'sent' ke tbl_jadwal_fpe             │
│  - Pembatalan otomatis pengingat terjadwal jika sudah kirim │
│  - HTTP Health Endpoint (http://localhost:3001/health)      │
└──────────────────────────────┬──────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────┐
│                  WHATSAPP PROVIDER LAYER                    │
│                 (Abstraksi WhatsAppProvider)                │
└──────────────┬───────────────────────────────┬──────────────┘
               │                               │
               ▼                               ▼
  ┌────────────────────────┐      ┌────────────────────────┐
  │    BaileysProvider     │      │    CloudApiProvider    │
  │ (Development & Testing)│      │  (Official Production) │
  │ - Multi-file auth info │      │ - Graph API Meta v19.0 │
  │ - QR login di terminal │      │ - Token & Phone ID env │
  │ - Sesi tersimpan lokal │      │ - SLA Enterprise 99.9% │
  └────────────┬───────────┘      └────────────┬───────────┘
               │                               │
               ▼                               ▼
       WhatsApp Keluarga               WhatsApp Keluarga
         (Penerima)                      (Penerima)
```

---

## 2. Fitur Arsitektur Utama

### A. Pengiriman Manual & Pencegahan Pesan Ganda (*De-duplication & Invalidation*)
- Saat petugas memilih untuk mengirim pesan secara manual (melalui toggle formulir atau tombol **"Kirim Manual Sekarang"** di tabel riwayat):
  1. Antrean diubah menjadi tipe `FPE_MANUAL` dengan waktu jatuh tempo saat itu juga (*due-now*).
  2. Seluruh antrean pengingat otomatis terjadwal yang masih berstatus `pending` untuk sesi tersebut **langsung digugurkan (*status = 'cancelled'*)** dengan catatan log: *"Otomatis gugur karena telah dikirim secara manual"*.
  3. Setelah pesan terkirim oleh worker, status pada `tbl_jadwal_fpe.status_kirim_wa` disinkronkan ke `'sent'` sehingga tidak ada duplikasi pengiriman pesan ke keluarga pasien.

### B. Penguncian Baris Atomik SQL Server CTE
- Worker mengklaim antrean menggunakan *Common Table Expression* (CTE) dengan petunjuk penguncian `WITH (UPDLOCK, READPAST)`:
  ```sql
  WITH ClaimedJobs AS (
      SELECT TOP (@limit) id, status, locked_at, locked_by, updated_at
      FROM tbl_wa_queue WITH (UPDLOCK, READPAST)
      WHERE status = 'pending' AND scheduled_at <= SYSDATETIME()
      ORDER BY scheduled_at ASC
  )
  UPDATE ClaimedJobs
  SET status = 'processing',
      locked_at = SYSDATETIME(),
      locked_by = @workerId,
      updated_at = SYSDATETIME()
  OUTPUT INSERTED.*;
  ```
- Menjamin keamanan dari kondisi balapan (*race condition*) apabila terdapat lebih dari satu instance worker berjalan paralel.

### C. Keandalan (*Reliability*) & *Retry Backoff*
- Jika koneksi WhatsApp atau server terputus saat pengiriman, status diubah menjadi `failed`, jumlah percobaan `attempts` dinaikkan (+1), dan waktu pengiriman dijadwalkan ulang dengan jeda eksponensial (1 menit, 5 menit, 15 menit) hingga maksimal 3 kali percobaan sebelum ditandai gagal permanen.

### D. Abstraksi Multi-Provider
- Pola *Strategy Pattern* melalui kelas abstrak `WhatsAppProvider` memisahkan secara bersih antara logika antrean database dan mekanisme pengiriman pesan. Pengalihan dari **Baileys** (uji coba QR gratis) ke **WhatsApp Cloud API Meta resmi** dilakukan hanya dengan mengubah variabel `WHATSAPP_PROVIDER` di file `.env` tanpa menyentuh satu baris kode pun di aplikasi PHP.
