# PANDUAN PENGUJIAN LENGKAP (TESTING GUIDE)
## RSKD Duren Sawit - Formulir FPE & Notifikasi WhatsApp

---

## 1. Pengujian Sintaks PHP (Automated)

Jalankan perintah ini di root direktori proyek untuk memastikan semua berkas PHP bebas dari *syntax error*:

```powershell
php -l php/config/app.php
php -l php/config/database.php
php -l php/includes/helpers.php
php -l php/includes/wa_queue.php
php -l php/form_jadwal_fpe.php
php -l php/form_dokumentasi_fpe.php
php -l php/form_kegiatan_pasien.php
php -l php/form_skrining_bunuh_diri.php
php -l php/index.php
```

---

## 2. Pengujian Unit Node.js (Unit Tests)

Jalankan pengujian unit untuk normalisasi nomor telepon, format tanggal Indonesia, dan template pesan:

```powershell
cd node
npm test
```

Hasil yang diharapkan:
```text
======================================================
 MENJALANKAN PENGUJIAN UNIT (UNIT TESTS)
======================================================
  ✅ PASS: Konversi format 0812... ke 62812...
  ✅ PASS: Konversi format +628... ke 628...
  ✅ PASS: Mempertahankan format 628...
  ✅ PASS: Membersihkan spasi, tanda strip, dan tanda kurung
  ✅ PASS: Menolak nomor yang bukan nomor seluler Indonesia valid
  ✅ PASS: Format JID Baileys WhatsApp
  ✅ PASS: Format tanggal Indonesia
  ✅ PASS: Penyusunan pesan WhatsApp metode Video Call WA
  ✅ PASS: Penyusunan pesan WhatsApp metode Zoom Meeting
  ✅ PASS: WhatsAppProvider abstract class throws when methods not implemented
======================================================
 HASIL: 10 / 10 Pengujian Berhasil (100%)
======================================================
```

---

## 3. Prosedur Pengujian End-to-End (E2E WhatsApp Nyata)

Ikuti langkah-langkah berikut untuk menguji pengiriman pesan WhatsApp sungguhan:

1. **Pastikan SQL Server Aktif** dan skema `database/sqlserver.sql` sudah dieksekusi.
2. **Jalankan PHP Web Server**:
   ```powershell
   php -S localhost:8080 -t php/
   ```
3. **Jalankan Node.js Worker**:
   ```powershell
   cd node
   npm start
   ```
4. **Scan QR Code** jika sesi baru. Tunggu hingga muncul log `[Baileys] Berhasil terhubung ke WhatsApp!`.
5. **Buka Browser** di `http://localhost:8080`.
6. **Pilih Pasien** pada dropdown atas.
7. **Buka Tab 1 (Penjadwalan FPE)**:
   - Pilih tanggal pelaksanaan FPE (misal: besok atau lusa).
   - Pilih metode (misal: *Video Call WA* atau *Zoom Meeting*).
   - Masukkan nomor WhatsApp pengujian Anda (misal: `085159811407`).
   - Tekan tombol **Simpan Jadwal & Jadwalkan Notifikasi**.
8. **Verifikasi Penyimpanan**:
   - Muncul alert hijau: *"Jadwal FPE berhasil disimpan. Notifikasi WhatsApp otomatis dijadwalkan..."*.
   - Tabel riwayat di bawah form menampilkan status badge **Terjadwal** (kuning).
9. **Uji Pengiriman Cepat (Due-Now)**:
   - Buka **Tab 5 (Monitoring Antrean WA & Tes Cepat)**.
   - Masukkan nomor `085159811407` dan tekan **Masukkan Antrean Pengujian**.
   - Perhatikan terminal Node.js worker:
     ```text
     [WORKER] 🔔 Menemukan 1 antrean notifikasi siap kirim:
     [WORKER] ➡️  Memproses antrean #... ke nomor +6285159811407...
     [Baileys] Pesan berhasil dikirim ke 6285159811407.
     [QUEUE] Antrean #... berhasil diperbarui ke status 'sent'.
     ```
10. **Periksa Ponsel Anda**:
    - Pesan WhatsApp dengan format resmi RSKD Duren Sawit berhasil diterima di HP penerima.
    - Refresh halaman tab 5, badge status antrean berubah menjadi **Terkirim** (hijau).
