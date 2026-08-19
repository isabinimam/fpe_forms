# PANDUAN PENGATURAN LOKAL WINDOWS
## RSKD Duren Sawit - Formulir FPE & Notifikasi WhatsApp

Panduan ini berisi instruksi lengkap untuk menyiapkan dan menjalankan modul formulir FPE dan worker WhatsApp di sistem operasi Windows (Windows 10 / Windows 11 / Windows Server).

---

## 1. Prasyarat Sistem

1. **Sistem Operasi**: Windows 10, Windows 11, atau Windows Server 2019/2022.
2. **Microsoft SQL Server**: SQL Server 2019 / 2022 atau SQL Server Express (misal: `localhost\SQLEXPRESS`).
3. **PHP**: Versi 8.1, 8.2, atau 8.3 dengan driver Microsoft `sqlsrv` (Direct SQL Server Driver) aktif.
4. **Node.js**: Versi 18 LTS atau 20 LTS.
5. **WhatsApp**: Ponsel dengan aplikasi WhatsApp aktif untuk pemindaian QR Code (pada mode Baileys).

---

## 2. Pemasangan Ekstensi PHP `sqlsrv` di Windows

Jika menggunakan XAMPP atau PHP mandiri di Windows:

1. Unduh driver Microsoft Drivers for PHP for SQL Server resmi dari Microsoft:
   👉 [https://learn.microsoft.com/en-us/sql/connect/php/download-drivers-php-sql-server](https://learn.microsoft.com/en-us/sql/connect/php/download-drivers-php-sql-server)
2. Ekstrak file `php_sqlsrv_*.dll` yang sesuai dengan versi PHP Anda (Thread Safe / Non-Thread Safe, x64) ke folder `ext/` PHP Anda (misal: `C:\xampp\php\ext\`).
3. Buka `php.ini` dan aktifkan ekstensi:
   ```ini
   extension=php_sqlsrv.dll
   ```
4. Pastikan ekstensi pendukung berikut juga aktif di `php.ini`:
   ```ini
   extension=curl
   extension=mbstring
   extension=openssl
   ```
5. Restart Web Server Apache / IIS.

---

## 3. Menyiapkan Database SQL Server

1. Buka **SQL Server Management Studio (SSMS)**.
2. Sambungkan ke instance database Anda (misal: `localhost\SQLEXPRESS`) menggunakan **Windows Authentication** atau SQL Authentication.
3. Buat database target (jika belum ada):
   ```sql
   CREATE DATABASE form_pfe;
   ```
4. Buka file `database/sqlserver.sql` di SSMS dan jalankan (**Execute**) pada database `form_pfe`.
   > 💡 **Catatan**: Blok pembuatan tabel dummy `tbl_pasien` di dalam berkas tersebut sudah dikomentari (`/* ... */`) secara default agar aman dan tidak menimpa data pasien yang sudah ada di rumah sakit. Jika Anda sedang melakukan pengetesan standalone lokal, Anda dapat meng-uncomment blok tersebut untuk mengisikan pasien dummy.

---

## 4. Menjalankan Server Web PHP (Pengujian Standalone)

Buka terminal PowerShell di folder proyek:

```powershell
php -S localhost:8080 -t php/
```

Buka browser dan akses dashboard pengujian:
👉 **[http://localhost:8080](http://localhost:8080)**

---

## 5. Menjalankan Worker Antrean WhatsApp Node.js

Buka jendela PowerShell / Terminal baru:

```powershell
# 1. Masuk ke direktori node
cd node

# 2. Pasang dependensi npm (hanya pada instalasi pertama)
npm install

# 3. Jalankan worker antrean
npm start
```

### Pertama Kali Menjalankan (Scan QR WhatsApp):
- Saat pertama kali `npm start` dijalankan dengan provider Baileys, terminal akan merender sebuah **QR Code**.
- Buka aplikasi WhatsApp di HP Anda &rarr; **Setelan / Titik Tiga** &rarr; **Perangkat Tertaut** &rarr; **Tautkan Perangkat** &rarr; **Pindai QR Code**.
- Setelah terhubung, sesi autentikasi akan tersimpan di folder `node/auth_info/`. Anda **tidak perlu scan QR lagi** saat worker dinyalakan ulang.

---

## 6. Menjalankan Sebagai Windows Service (Opsional untuk Produksi)

Untuk menjaga worker tetap berjalan di latar belakang server secara otomatis saat server dinyalakan ulang (*auto-start on boot*), gunakan **PM2**:

```powershell
# Pasang PM2 secara global
npm install -g pm2

# Jalankan worker via PM2
pm2 start src/worker.js --name "rskd-fpe-wa-worker"

# Simpan konfigurasi agar auto-start
pm2 startup
pm2 save
```
