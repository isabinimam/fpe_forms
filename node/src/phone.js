/**
 * PHONE NUMBER NORMALIZATION UTILITY
 * RSKD Duren Sawit - WhatsApp Queue Worker
 */

/**
 * Normalizes Indonesian phone numbers to canonical 628xxx format
 * @param {string} phone
 * @returns {string|null}
 */
function normalizePhone(phone) {
    if (!phone || typeof phone !== 'string') return null;

    // Remove all non-digits except +
    let clean = phone.trim().replace(/[^\d+]/g, '');

    // Remove leading +
    if (clean.startsWith('+')) {
        clean = clean.substring(1);
    }

    // Convert 08xxx to 628xxx
    if (clean.startsWith('08')) {
        clean = '62' + clean.substring(1);
    } else if (clean.startsWith('8')) {
        clean = '62' + clean;
    }

    // Indonesian mobile validation (starts with 628, 10 to 15 digits total)
    if (/^628\d{8,12}$/.test(clean)) {
        return clean;
    }

    return null;
}

/**
 * Formats canonical phone to Baileys WhatsApp JID (e.g. 628123456789@s.whatsapp.net)
 * @param {string} phone
 * @returns {string|null}
 */
function formatBaileysJid(phone) {
    const clean = normalizePhone(phone);
    if (!clean) return null;
    return `${clean}@s.whatsapp.net`;
}

module.exports = {
    normalizePhone,
    formatBaileysJid
};
