# PANDUAN PENGUJIAN LENGKAP (TESTING GUIDE)
## RSKD Duren Sawit - Formulir FPE & Notifikasi WhatsApp

---

## 1. Pengujian Sintaks PHP (Automated Linting)

Jalankan perintah berikut di PowerShell root proyek untuk memverifikasi bahwa seluruh berkas form dan helper bebas dari kesalahan sintaks:

```powershell
php -l php/form_jadwal_fpe.php
php -l php/form_dokumentasi_fpe.php
php -l php/form_kegiatan_pasien.php
php -l php/form_skrining_bunuh_diri.php
php -l php/includes/helpers.php
php -l php/includes/wa_queue.php
```

Output yang diharapkan untuk setiap file:
```text
No syntax errors detected in [nama_file].php
```

---

## 2. Pengujian Unit Worker Node.js (Unit Tests)

Jalankan pengujian unit otomatis untuk menguji modul normalisasi telepon, kalkulasi waktu, template pesan resmi, dan interface provider:

```powershell
cd node
npm test
```

Hasil yang diharapkan (**10/10 PASS - 100%**):
```text
======================================================
 MENJALANKAN PENGUJIAN UNIT (UNIT TESTS)
======================================================

--- 1. Phone Number Normalization Tests ---
  ✅ PASS: Konversi format 0812... ke 62812...
  ✅ PASS: Konversi format +628... ke 628...
  ✅ PASS: Mempertahankan format 628...
  ✅ PASS: Membersihkan spasi, tanda strip, dan tanda kurung
  ✅ PASS: Menolak nomor yang bukan nomor seluler Indonesia valid
  ✅ PASS: Format JID Baileys WhatsApp

--- 2. Date Formatting & Message Template Tests ---
  ✅ PASS: Format tanggal Indonesia
  ✅ PASS: Penyusunan pesan WhatsApp metode Video Call WA
  ✅ PASS: Penyusunan pesan WhatsApp metode Zoom Meeting

--- 3. WhatsApp Provider Interface Tests ---
  ✅ PASS: WhatsAppProvider abstract class throws when methods not implemented

======================================================
 HASIL: 10 / 10 Pengujian Berhasil (100%)
======================================================
```

---

## 3. Prosedur Pengujian End-to-End (E2E) per Komponen

### A. Pengujian Form 1: Penjadwalan FPE, Antrean H-1, & Kirim Manual
1. Buka browser di `http://localhost:8080` &rarr; **Tab 1: Penjadwalan FPE**.
2. **Uji Pengingat Otomatis**:
   - Pilih tanggal pelaksanaan 3-5 hari ke depan (misal: `24/08/2026`).
   - Masukkan nomor WhatsApp pengujian Anda (misal: `085159811407`).
   - Tekan **Simpan Jadwal**.
   - Verifikasi baris jadwal baru di tabel riwayat berstatus badge kuning **Terjadwal** dengan jadwal kirim H-1 pukul 09:00.
3. **Uji Tombol "Kirim Manual Sekarang"**:
   - Pada baris jadwal tadi di tabel riwayat, klik tombol hijau **"Kirim Manual"**.
   - Perhatikan terminal Node.js worker: pesan langsung dikirim detik itu juga.
   - Refresh browser: badge status berubah menjadi **Terkirim** (hijau).
   - Pengingat otomatis H-1 untuk jadwal tersebut otomatis dibatalkan (*cancelled*) agar tidak terjadi pengiriman ganda.

---

### B. Pengujian Form 2: Dokumentasi FPE (Multiple Choice Keluarga Hadir)
1. Buka **Tab 2: Dokumentasi FPE**.
2. Pada bagian **Hubungan dengan Pasien (Siapa Saja yang Hadir)**, centang lebih dari satu pilihan (misal: `Ayah`, `Ibu`, dan `Lain-lain`).
3. Tuliskan keterangan pada input lainnya (misal: `Paman / Wali`).
4. Isi kolom Asesmen dan Hasil FPE, lalu klik **Simpan Dokumentasi**.
5. Verifikasi di tabel riwayat: seluruh relasi keluarga yang hadir tampil sebagai **badge biru individual yang rapi** (`[Ayah] [Ibu] [Lainnya: Paman / Wali]`).

---

### C. Pengujian Form 3: Jadwal Kegiatan Pasien (10 Hari Adaptif)
1. Buka **Tab 3: Jadwal Kegiatan (10 Hari)**.
2. Ubah tanggal mulai periode pada datepicker (misal: `29/08/2026`).
3. Verifikasi bahwa header kolom Hari I s/d X langsung terhitung dan ter-update secara otomatis (`29/08`, `30/08`, `31/08`, `01/09`, dst.).
4. Centang beberapa kegiatan harian dan klik **Simpan Jadwal Kegiatan**.
5. Refresh halaman: centang kegiatan pada rentang tanggal tersebut tetap tersimpan secara presisi.

---

### D. Pengujian Form 4: Skrining Risiko Bunuh Diri (Umur Akurat & Asal Pasien)
1. Buka **Tab 4: Skrining Risiko Bunuh Diri**.
2. Verifikasi panel banner di bagian atas:
   - Nama Pasien: `Ny. Aisyah`
   - Tanggal Lahir: `12 April 1985`
   - Umur Pasien: terhitung otomatis kalender presisi (contoh: **`41 Tahun 4 Bulan 7 Hari`**).
3. Di bagian bawah formulir (sebelum tombol submit), pastikan opsi **Asal Pasien** tercentang **Poli** secara default.
4. Pilih jawaban skrining (misal Pertanyaan 1 = *Ya*).
5. Klik **Simpan Hasil Skrining**.
6. Verifikasi di tabel riwayat:
   - Kolom **Asal Pasien** menampilkan badge `Poli` (hijau).
   - Kolom **Hasil Skoring** menampilkan badge `Depresi` (kuning) secara otomatis.
