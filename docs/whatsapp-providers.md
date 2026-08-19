# PANDUAN PENGGANTIAN PROVIDER WHATSAPP
## Mengalihkan antara Baileys (Pengujian Lokal) & WhatsApp Cloud API (Produksi Resmi)

Sistem notifikasi WhatsApp dirancang dengan arsitektur **Strategy Pattern (`WhatsAppProvider`)** yang memisahkan seluruh logika antrean database dari modul gateway WhatsApp. Pengalihan antara mode pengujian dan mode produksi dilakukan **hanya melalui variabel lingkungan (`.env`) tanpa mengubah satu baris kode pun**.

---

## 1. Mode Pengembangan & Pengujian Lokal (Default: Baileys)

Mode ini menggunakan library socket Baileys untuk menghubungkan nomor WhatsApp pribadi atau operasional kantor secara gratis menggunakan pemindaian QR Code.

### Konfigurasi `.env` pada folder `node/`:
```env
# Provider WhatsApp
WHATSAPP_PROVIDER=baileys

# Database SQL Server
DB_SERVER=localhost
DB_INSTANCE=SQLEXPRESS
DB_DATABASE=form_pfe
DB_TRUST_SERVER_CERTIFICATE=true

# Polling & Antrean
QUEUE_POLL_INTERVAL_MS=15000
QUEUE_PROCESSING_TIMEOUT_MINUTES=10
WA_MAX_ATTEMPTS=3
HEALTH_PORT=3001
TZ=Asia/Jakarta
```

### Karakteristik:
- **Biaya**: 100% Gratis.
- **Autentikasi**: Pindai QR Code di terminal satu kali menggunakan menu *Perangkat Tertaut* di WhatsApp ponsel Anda.
- **Penyimpanan Sesi**: Sesi disimpan secara terenkripsi di folder `node/auth_info/`.
- **Ideal untuk**: Uji coba internal, QA testing, dan demonstrasi operasional.

---

## 2. Mode Produksi Resmi (WhatsApp Cloud API Meta)

Mode ini menggunakan API resmi WhatsApp Business Platform dari Meta / Facebook Graph API.

### Konfigurasi `.env` pada folder `node/`:
```env
# Provider WhatsApp
WHATSAPP_PROVIDER=cloud_api

# Database SQL Server
DB_SERVER=localhost
DB_INSTANCE=SQLEXPRESS
DB_DATABASE=nama_database_utama
DB_TRUST_SERVER_CERTIFICATE=true

# Konfigurasi WhatsApp Cloud API Meta
WA_CLOUD_API_BASE_URL=https://graph.facebook.com
WA_CLOUD_API_VERSION=v19.0
WA_PHONE_NUMBER_ID=123456789012345
WA_ACCESS_TOKEN=EAAG...your_permanent_system_user_token_from_meta...

# Polling & Antrean
QUEUE_POLL_INTERVAL_MS=15000
QUEUE_PROCESSING_TIMEOUT_MINUTES=10
WA_MAX_ATTEMPTS=3
HEALTH_PORT=3001
TZ=Asia/Jakarta
```

### Karakteristik:
- **Keandalan Tinggi**: Server-to-server HTTP request langsung ke Meta dengan SLA 99.9%.
- **Tanpa Scan QR**: Tidak memerlukan ponsel fisik yang harus selalu terhubung ke internet.
- **Skalabilitas**: Mampu mengirimkan puluhan hingga ratusan notifikasi per detik tanpa resiko koneksi terputus.
- **Portabilitas Penuh**: Skema tabel antrean `tbl_wa_queue` dan seluruh kode PHP di aplikasi utama tetap berjalan identik 100%.
