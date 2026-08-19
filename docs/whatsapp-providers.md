# PANDUAN PENGGANTIAN PROVIDER WHATSAPP
## Mengalihkan antara Baileys (Pengujian) & WhatsApp Cloud API (Produksi)

Sistem menggunakan pola arsitektur **Provider Abstraction (`WhatsAppProvider`)** yang memisahkan seluruh logika antrean database dari modul pengiriman WhatsApp. Pengalihan dilakukan hanya melalui variabel lingkungan (*environment variables*).

---

## 1. Mode Pengembangan / Pengujian Lokal (Default: Baileys)

Mode ini menggunakan library Baileys untuk menghubungkan nomor WhatsApp pribadi atau kantor secara gratis menggunakan QR code.

### Konfigurasi `.env` pada folder `node/`:
```env
WHATSAPP_PROVIDER=baileys
```

### Karakteristik:
- **Biaya**: Gratis.
- **Autentikasi**: Scan QR Code di terminal satu kali (disimpan di `node/auth_info/`).
- **Ideal untuk**: Uji coba internal, QA testing, dan demonstrasi ke manajemen.

---

## 2. Mode Produksi Resmi (WhatsApp Cloud API)

Mode ini menggunakan API resmi WhatsApp Business Platform dari Meta / Facebook Graph API.

### Konfigurasi `.env` pada folder `node/`:
```env
WHATSAPP_PROVIDER=cloud_api

WA_CLOUD_API_BASE_URL=https://graph.facebook.com
WA_CLOUD_API_VERSION=v19.0
WA_PHONE_NUMBER_ID=123456789012345
WA_ACCESS_TOKEN=EAAG...your_permanent_meta_system_user_token...
```

### Karakteristik:
- **Biaya**: Mengikuti tarif resmi percakapan utilitas Meta (Utility Conversation).
- **Keandalan**: SLA 99.9% dari server Meta, tanpa perlu scan QR atau menjaga koneksi socket tetap hidup.
- **Kapasitas**: Skalabilitas tinggi hingga ribuan pesan per menit.
- **Tanpa Perubahan Kode**: Kode antrean di SQL Server dan PHP tetap sama persis 100%.
