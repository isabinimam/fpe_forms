/**
 * MESSAGE TEMPLATE BUILDER
 * RSKD Duren Sawit - WhatsApp Queue Worker
 *
 * Formats official Indonesian notification reminder messages
 */

const bulanIndo = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

/**
 * Formats Y-m-d date to Indonesian format (e.g. 25 Agustus 2026)
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
    const day = d.getDate();
    const month = bulanIndo[d.getMonth()];
    const year = d.getFullYear();
    return `${day} ${month} ${year}`;
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
    const slotWaktu = data.slotWaktu || '';
    const metode = data.metode === 'zoom_meeting' ? 'Zoom Meeting' : 'Video Call WhatsApp (Tab Ruangan)';

    let msg = `*PENGINGAT JADWAL FORMULIR PSIKOEDUKASI (FPE)*\n`;
    msg += `*RSKD Duren Sawit*\n\n`;
    msg += `Yth. Bpk/Ibu *${namaKeluarga}*,\n\n`;
    msg += `Kami menginformasikan jadwal sesi Psikoedukasi Keluarga (FPE) untuk pasien:\n`;
    msg += `👤 *Nama Pasien:* ${namaPasien}\n`;
    msg += `📅 *Tanggal:* ${tanggalIndo}\n`;
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

    msg += `\nMohon mempersiapkan diri 10 menit sebelum jadwal dimulai.\n\n`;
    msg += `Terima kasih atas kerja sama Anda.\n`;
    msg += `_Pesan otomatis dari Sistem Informasi RSKD Duren Sawit_`;

    return msg;
}

module.exports = {
    formatTanggalIndo,
    buildFpeMessage
};
