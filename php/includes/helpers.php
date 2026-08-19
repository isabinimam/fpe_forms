<?php
/**
 * FUNGSI HELPER SISTEM FPE & NOTIFIKASI WHATSAPP
 * RSKD Duren Sawit
 */

/**
 * Normalisasi nomor telepon WhatsApp ke format standar 628xxx
 *
 * @param string $phone
 * @return string|false Nomor ternormalisasi (misal: 6281234567890) atau false jika tidak valid
 */
function normalizePhoneNumber(string $phone) {
    // Hapus karakter non-digit kecuali +
    $clean = preg_replace('/[^\d+]/', '', trim($phone));

    // Hapus tanda +
    if (strpos($clean, '+') === 0) {
        $clean = substr($clean, 1);
    }

    // Ubah 08xxx menjadi 628xxx
    if (strpos($clean, '08') === 0) {
        $clean = '62' . substr($clean, 1);
    } elseif (strpos($clean, '8') === 0) {
        $clean = '62' . $clean;
    }

    // Validasi panjang dan pola nomor Indonesia (harus diawali 628 dan panjang 10-15 digit)
    if (preg_match('/^628\d{8,12}$/', $clean)) {
        return $clean;
    }

    return false;
}

/**
 * Hitung waktu pengiriman notifikasi WhatsApp (H-1 sebelum tanggal FPE)
 * Sesuai kebijakan deterministik: jika waktu notifikasi yang dihitung sudah lewat, jadwalkan langsung saat ini (due-now).
 *
 * @param string $fpeDate Format Y-m-d
 * @param int $leadDays Jumlah hari sebelumnya (default: 1)
 * @param string $notifTime Format H:i (default: 09:00)
 * @return string Datetime string format Y-m-d H:i:s
 */
function calculateScheduledAt(string $fpeDate, int $leadDays = 1, string $notifTime = '09:00'): string {
    $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
    
    // Hitung tanggal H-leadDays
    $targetDate = new DateTime($fpeDate . ' ' . $notifTime . ':00', new DateTimeZone('Asia/Jakarta'));
    if ($leadDays > 0) {
        $targetDate->modify("-{$leadDays} days");
    }

    // Jika waktu notifikasi yang dihitung <= sekarang, set ke sekarang (due-now job)
    if ($targetDate <= $now) {
        return $now->format('Y-m-d H:i:s');
    }

    return $targetDate->format('Y-m-d H:i:s');
}

/**
 * Label status notifikasi WhatsApp dalam Bahasa Indonesia
 *
 * @param string|null $status
 * @return string
 */
function waStatusLabel(?string $status): string {
    switch ($status) {
        case 'pending':
            return 'Terjadwal';
        case 'processing':
            return 'Sedang Diproses';
        case 'sent':
            return 'Terkirim';
        case 'failed':
            return 'Gagal';
        case 'cancelled':
            return 'Dibatalkan';
        default:
            return 'Belum Ada Notifikasi';
    }
}

/**
 * Class badge Bootstrap 5 untuk status notifikasi
 *
 * @param string|null $status
 * @return string
 */
function waStatusBadgeClass(?string $status): string {
    switch ($status) {
        case 'pending':
            return 'bg-warning text-dark';
        case 'processing':
            return 'bg-info text-dark';
        case 'sent':
            return 'bg-success text-white';
        case 'failed':
            return 'bg-danger text-white';
        case 'cancelled':
            return 'bg-secondary text-white';
        default:
            return 'bg-light text-dark border';
    }
}

/**
 * Format tanggal ke Bahasa Indonesia (Contoh: Rabu, 25 Agustus 2026)
 *
 * @param string|DateTimeInterface $date
 * @param bool $withDay Sertakan nama hari (default: true)
 * @return string
 */
function formatTanggalIndo($date, bool $withDay = true): string {
    if ($date instanceof DateTimeInterface) {
        $timestamp = $date->getTimestamp();
    } else {
        $timestamp = strtotime($date);
    }
    if (!$timestamp) {
        return (string)$date;
    }

    $hariIndo = [
        'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
    ];

    $bulanIndo = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];

    $namaHari = $hariIndo[date('l', $timestamp)] ?? '';
    $hari = date('d', $timestamp);
    $bulan = $bulanIndo[(int)date('m', $timestamp)];
    $tahun = date('Y', $timestamp);

    if ($withDay && $namaHari !== '') {
        return "{$namaHari}, {$hari} {$bulan} {$tahun}";
    }
    return "{$hari} {$bulan} {$tahun}";
}
