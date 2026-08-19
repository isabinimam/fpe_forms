/**
 * MESSAGE TEMPLATE BUILDER
 * RSKD Duren Sawit - WhatsApp Queue Worker
 *
 * Formats official Indonesian notification reminder messages
 */

const hariIndo = [
    'Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'
];

const bulanIndo = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

/**
 * Formats Y-m-d date to Indonesian format with day name (e.g. Rabu, 25 Agustus 2026)
 * @param {string|Date} dateInput
 * @returns {string}
 */
function formatTanggalIndo(dateInput) {
    if (!dateInput) return '';
    const d = new Date(dateInput);
    if (isNaN(d.getTime())) {
        // Fallback parse YYYY-MM-DD
        const parts = String(dateInput).split('-');
        if (parts.length === 3) {
            const year = parts[0];
            const month = parseInt(parts[1], 10);
            const day = parseInt(parts[2], 10);
            const bulanName = bulanIndo[month - 1] || parts[1];
            return `${day} ${bulanName} ${year}`;
        }
        return String(dateInput);
    }
    const namaHari = hariIndo[d.getDay()];
    const day = d.getDate();
    const month = bulanIndo[d.getMonth()];
    const year = d.getFullYear();
    return `${namaHari}, ${day} ${month} ${year}`;
}

/**
 * Builds standard Indonesian FPE reminder message
 * @param {Object} data
 * @returns {string}
 */
function buildFpeMessage(data) {
    const namaPasien = data.namaPasien || 'Pasien';
    const namaKeluarga = data.namaKeluarga || 'Keluarga Pasien';
    const tanggalIndo = formatTanggalIndo(data.tanggalPelaksanaan);
    const jam = (data.jamPelaksanaan || '').substring(0, 5) + ' WIB';
    const slotWaktu = data.slotWaktu || '-';
    const metode = data.metode === 'zoom_meeting' ? 'Zoom Meeting' : 'Video Call WhatsApp (Tab Ruangan)';

    let msg = `*PENGINGAT JADWAL PSIKOEDUKASI KELUARGA (FPE)*\n`;
    msg += `*RSKD DUREN SAWIT*\n`;
    msg += `━━━━━━━━━━━━━━━━━━━━━━━━\n\n`;

    if (/^(bpk|bapak|ibu|sdr|sdri)\.?\s+/i.test(namaKeluarga)) {
        msg += `Yth. *${namaKeluarga}*,\n\n`;
    } else {
        msg += `Yth. *Bpk/Ibu ${namaKeluarga}*,\n\n`;
    }

    msg += `Kami dari *Instalasi Pelayanan Jiwa RSKD Duren Sawit* bermaksud mengingatkan agenda sesi *Psikoedukasi Keluarga (FPE)* yang telah dijadwalkan untuk pasien:\n\n`;
    msg += `👤 *Nama Pasien:* ${namaPasien}\n`;
    msg += `📅 *Hari/Tanggal:* ${tanggalIndo}\n`;
    msg += `⏰ *Waktu:* ${jam} (Slot: ${slotWaktu})\n`;
    msg += `📱 *Metode:* ${metode}\n`;

    if (data.metode === 'zoom_meeting') {
        if (data.meetingId) {
            msg += `🔹 *Meeting ID:* ${data.meetingId}\n`;
        }
        if (data.passcode) {
            msg += `🔹 *Passcode:* ${data.passcode}\n`;
        }
    }

    msg += `\n━━━━━━━━━━━━━━━━━━━━━━━━\n`;
    msg += `📌 *Catatan Penting:*\n`;
    msg += `1. Mohon bersiap 5–10 menit sebelum sesi dimulai.\n`;
    msg += `2. Pastikan koneksi internet stabil dan berada di tempat yang tenang.\n`;
    msg += `3. Sesi ini bertujuan untuk berbagi informasi perkembangan kondisi dan panduan pendampingan pasien di rumah.\n\n`;
    msg += `Jika ada kendala kehadiran atau ingin konfirmasi ulang, silakan balas pesan ini.\n\n`;
    msg += `Terima kasih atas perhatian dan kerja samanya.\n`;
    msg += `Salam sehat,\n`;
    msg += `*Tim Pelayanan FPE RSKD Duren Sawit*\n`;
    msg += `━━━━━━━━━━━━━━━━━━━━━━━━\n`;
    msg += `_Pesan otomatis oleh Sistem FPE RSKD Duren Sawit_`;

    return msg;
}

module.exports = {
    formatTanggalIndo,
    buildFpeMessage
};
