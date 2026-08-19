# ARSITEKTUR SISTEM FORMULIR FPE & NOTIFIKASI WHATSAPP
## RSKD Duren Sawit

---

## 1. Diagram Alur Keseluruhan

```text
┌─────────────────────────────────────────────────────────────┐
│                       PENGGUNA / BROWSER                    │
│    Petugas membuka form FPE dan menekan "Simpan Jadwal"    │
└──────────────────────────────┬──────────────────────────────┘
                               │ HTTP POST
                               ▼
┌─────────────────────────────────────────────────────────────┐
│                    NATIVE PHP (BOOTSTRAP 5)                 │
│  - Validasi input & normalisasi nomor WA (+628xxx)          │
│  - Transaksi Atomik Database (sqlsrv_begin_transaction)    │
│  - Hitung tanggal H-1 pengiriman                            │
│  - Tampilkan riwayat & badge status antrean                 │
└──────────────────────────────┬──────────────────────────────┘
                               │
               ┌───────────────┴───────────────┐
               │ Transaksi Atomik SQL Server   │
               ▼                               ▼
    ┌────────────────────┐          ┌──────────────────────┐
    │   tbl_jadwal_fpe   │          │     tbl_wa_queue     │
    │  (Data Jadwal FPE) │          │ (Antrean Notifikasi) │
    └────────────────────┘          └──────────┬───────────┘
                                               │
                                               │ Polling berkala (15-30 dtk)
                                               │ UPDLOCK & READPAST (Safe Claim)
                                               ▼
┌─────────────────────────────────────────────────────────────┐
│                    NODE.JS QUEUE WORKER                     │
│  - Background process independen                            │
│  - Klaim antrean due (scheduled_at <= SYSDATETIME())        │
│  - Pemulihan antrean macet (Stale Processing Recovery)      │
│  - Retry backoff jika gagal (maksimal 3x percobaan)         │
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
  │ - Multi-file auth info │      │ - Graph API Meta       │
  │ - QR login di terminal │      │ - Token & Phone ID env │
  └────────────┬───────────┘      └────────────┬───────────┘
               │                               │
               ▼                               ▼
       WhatsApp Keluarga               WhatsApp Keluarga
         (Penerima)                      (Penerima)
```

---

## 2. Mengapa Menggunakan Sistem Antrean (Queue)?

1. **Keandalan (Reliability)**: Jika koneksi WhatsApp atau internet sedang terputus, pesan tidak hilang. Sistem mencatatnya sebagai `pending` dan akan mengirim ulang secara otomatis.
2. **Kinerja UI Cepat**: Pengguna di browser tidak perlu menunggu proses pengiriman pesan WhatsApp selesai untuk menyimpan formulir. Form tersimpan secara instan dalam hitungan milidetik.
3. **Pengiriman Tepat Waktu (H-1)**: Pesan dijadwalkan secara otomatis untuk dikirim satu hari sebelum sesi FPE berlangsung tanpa perlu petugas mengingat atau mengirim manual.
4. **Idempotensi & Pencegahan Duplikasi**: Kolom unik `UQ_wa_queue_jadwal_tipe (id_jadwal, tipe_notifikasi)` mencegah pengiriman pesan berulang kali untuk jadwal yang sama.
5. **Abstraksi Provider**: Memungkinkan beralih dari Baileys (pengujian lokal) ke WhatsApp Cloud API resmi di masa produksi tanpa mengubah kode form PHP sama sekali.
