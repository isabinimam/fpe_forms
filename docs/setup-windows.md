# PANDUAN PENGATURAN LOKAL WINDOWS
## RSKD Duren Sawit - Formulir FPE & Notifikasi WhatsApp

Panduan ini berisi instruksi lengkap untuk menjalankan aplikasi secara lokal di sistem operasi Windows.

---

## 1. Prasyarat Sistem

1. **Sistem Operasi**: Windows 10 atau Windows 11
2. **Microsoft SQL Server**: SQL Server Express (misal: `localhost\SQLEXPRESS`)
3. **PHP**: Versi 8.1 atau yang lebih baru dengan ekstensi `sqlsrv` aktif
4. **Node.js**: Versi 18 LTS atau 20 LTS
5. **WhatsApp**: Ponsel dengan aplikasi WhatsApp aktif untuk scan QR Baileys

---

## 2. Pemasangan Ekstensi PHP sqlsrv di Windows

Jika Anda menggunakan XAMPP atau PHP Standalone di Windows:

1. Unduh driver Microsoft Drivers for PHP for SQL Server resmi dari:
   [https://learn.microsoft.com/en-us/sql/connect/php/download-drivers-php-sql-server](https://learn.microsoft.com/en-us/sql/connect/php/download-drivers-php-sql-server)
2. Ekstrak file `php_sqlsrv_*.dll` yang sesuai dengan versi PHP Anda (Thread Safe / x64) ke folder `php/ext/` (misal: `C:\xampp\php\ext`).
3. Buka `php.ini` dan tambahkan baris berikut:
   ```ini
   extension=php_sqlsrv.dll
   ```
4. Pastikan juga ekstensi berikut aktif di `php.ini`:
   ```ini
   extension=curl
   extension=mbstring
   extension=openssl
   ```
5. Restart Apache / PHP.

---

## 3. Menyiapkan Database SQL Server

1. Buka **SQL Server Management Studio (SSMS)**.
2. Sambungkan ke server `localhost\SQLEXPRESS` menggunakan **Windows Authentication**.
3. Jika database `form_pfe` belum ada, buat database:
   ```sql
   CREATE DATABASE form_pfe;
   ```
4. Buka file `database/sqlserver.sql` di SSMS dan jalankan (**Execute**) untuk membuat tabel-tabel yang diperlukan.

---

## 4. Menjalankan Aplikasi Web PHP

Buka PowerShell / Command Prompt di folder proyek:

```powershell
# Jalankan PHP Built-in Web Server
php -S localhost:8080 -t php/
```

Buka browser dan akses:
👉 **[http://localhost:8080](http://localhost:8080)**

---

## 5. Menjalankan Worker Antrean Node.js

Buka jendela PowerShell / Terminal baru:

```powershell
# 1. Masuk ke direktori node
cd node

# 2. Pasang dependensi npm (hanya pada kali pertama)
npm install

# 3. Jalankan worker antrean
npm start
```

### Pertama Kali Menjalankan (Scan QR WhatsApp):
- Pada kali pertama `npm start` dijalankan, terminal akan menampilkan **QR Code**.
- Buka aplikasi WhatsApp di HP Anda &rarr; **Perangkat Tertaut** &rarr; **Tautkan Perangkat** &rarr; **Scan QR**.
- Setelah terhubung, sesi akan tersimpan di folder `node/auth_info/`. Anda **tidak perlu** scan QR lagi saat worker direstart.
