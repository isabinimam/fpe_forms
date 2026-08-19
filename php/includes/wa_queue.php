<?php
/**
 * PENGELOLA ANTREAN NOTIFIKASI WHATSAPP (QUEUE HELPER)
 * RSKD Duren Sawit
 *
 * Helper untuk pembuatan dan manajemen antrean pesan WhatsApp via sqlsrv
 */

require_once __DIR__ . '/helpers.php';

/**
 * Susun pesan pengingat jadwal FPE resmi dalam Bahasa Indonesia
 *
 * @param array $data
 *   - nama_pasien (string)
 *   - nama_keluarga (string|null)
 *   - tanggal_pelaksanaan (string)
 *   - jam_pelaksanaan (string)
 *   - metode (string) 'video_call_wa' | 'zoom_meeting'
 *   - slot_waktu (string)
 *   - meeting_id (string|null)
 *   - passcode (string|null)
 * @return string Pesan lengkap
 */
function buildFpeReminderMessage(array $data): string {
    $namaPasien   = $data['nama_pasien'] ?? 'Pasien';
    $namaKeluarga = !empty($data['nama_keluarga']) ? $data['nama_keluarga'] : 'Keluarga Pasien';
    $tanggalIndo  = formatTanggalIndo($data['tanggal_pelaksanaan']);
    $jam          = substr($data['jam_pelaksanaan'], 0, 5) . ' WIB';
    $slotWaktu    = $data['slot_waktu'] ?? '-';
    $metode       = $data['metode'] === 'zoom_meeting' ? 'Zoom Meeting' : 'Video Call WhatsApp (Tab Ruangan)';

    $msg  = "*PENGINGAT JADWAL PSIKOEDUKASI KELUARGA (FPE)*\n";
    $msg .= "*RSKD DUREN SAWIT*\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    // Format sapaan (hindari redundansi jika nama sudah diawali Bpk/Ibu/Bapak/Sdr/Sdri)
    if (preg_match('/^(bpk|bapak|ibu|sdr|sdri)\.?\s+/i', $namaKeluarga)) {
        $msg .= "Yth. *" . $namaKeluarga . "*,\n\n";
    } else {
        $msg .= "Yth. *Bpk/Ibu " . $namaKeluarga . "*,\n\n";
    }

    $msg .= "Kami dari *Instalasi Pelayanan Jiwa RSKD Duren Sawit* bermaksud mengingatkan agenda sesi *Psikoedukasi Keluarga (FPE)* yang telah dijadwalkan untuk pasien:\n\n";
    $msg .= "👤 *Nama Pasien:* " . $namaPasien . "\n";
    $msg .= "📅 *Hari/Tanggal:* " . $tanggalIndo . "\n";
    $msg .= "⏰ *Waktu:* " . $jam . " (Slot: " . $slotWaktu . ")\n";
    $msg .= "📱 *Metode:* " . $metode . "\n";

    if ($data['metode'] === 'zoom_meeting') {
        if (!empty($data['meeting_id'])) {
            $msg .= "🔹 *Meeting ID:* " . $data['meeting_id'] . "\n";
        }
        if (!empty($data['passcode'])) {
            $msg .= "🔹 *Passcode:* " . $data['passcode'] . "\n";
        }
    }

    $msg .= "\n━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "📌 *Catatan Penting:*\n";
    $msg .= "1. Mohon bersiap 5–10 menit sebelum sesi dimulai.\n";
    $msg .= "2. Pastikan koneksi internet stabil dan berada di tempat yang tenang.\n";
    $msg .= "3. Sesi ini bertujuan untuk berbagi informasi perkembangan kondisi dan panduan pendampingan pasien di rumah.\n\n";
    $msg .= "Jika ada kendala kehadiran atau ingin konfirmasi ulang, silakan balas pesan ini.\n\n";
    $msg .= "Terima kasih atas perhatian dan kerja samanya.\n";
    $msg .= "Salam sehat,\n";
    $msg .= "*Tim Pelayanan FPE RSKD Duren Sawit*\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "_Pesan otomatis oleh Sistem FPE RSKD Duren Sawit_";

    return $msg;
}

/**
 * Buat catatan antrean notifikasi WhatsApp di SQL Server
 *
 * @param resource $conn Resource koneksi sqlsrv
 * @param int $idJadwal ID jadwal FPE terkait
 * @param string $nomorTujuan Nomor HP tujuan (format 628xxx)
 * @param string $pesan Teks pesan WhatsApp
 * @param string $scheduledAt Format Y-m-d H:i:s
 * @param string $tipeNotifikasi Default: 'FPE_REMINDER'
 * @return int ID antrean yang baru dibuat
 * @throws Exception Jika insert gagal
 */
function createWaQueueJob($conn, int $idJadwal, string $nomorTujuan, string $pesan, string $scheduledAt, string $tipeNotifikasi = 'FPE_REMINDER'): int {
    // Validasi nomor telepon
    $cleanPhone = normalizePhoneNumber($nomorTujuan);
    if (!$cleanPhone) {
        throw new Exception("Nomor WhatsApp keluarga ($nomorTujuan) tidak valid. Gunakan format nomor Indonesia (contoh: 08123456789).");
    }

    $tsql = "
        INSERT INTO tbl_wa_queue
            (id_jadwal, nomor_tujuan, tipe_notifikasi, pesan, scheduled_at, status, attempts, created_at, updated_at)
        OUTPUT INSERTED.id
        VALUES
            (?, ?, ?, ?, ?, 'pending', 0, SYSDATETIME(), SYSDATETIME())
    ";

    $params = [$idJadwal, $cleanPhone, $tipeNotifikasi, $pesan, $scheduledAt];
    $stmt = sqlsrv_query($conn, $tsql, $params);

    if ($stmt === false) {
        $errors = sqlsrv_errors();
        $errorDetails = '';
        if ($errors !== null) {
            foreach ($errors as $e) {
                $errorDetails .= " [{$e['message']}]";
            }
        }
        throw new Exception("Gagal membuat antrean notifikasi WhatsApp:" . $errorDetails);
    }

    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $newId = (int)$row['id'];
    sqlsrv_free_stmt($stmt);

    return $newId;
}
