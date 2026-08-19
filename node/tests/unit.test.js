/**
 * UNIT TESTS FOR FPE WHATSAPP NOTIFICATION SYSTEM
 * RSKD Duren Sawit
 *
 * Can be run with: node tests/unit.test.js
 */

const assert = require('assert');
const { normalizePhone, formatBaileysJid } = require('../src/phone');
const { formatTanggalIndo, buildFpeMessage } = require('../src/message-template');
const WhatsAppProvider = require('../src/whatsapp/WhatsAppProvider');

let totalTests = 0;
let passedTests = 0;

function test(name, fn) {
    totalTests++;
    try {
        fn();
        passedTests++;
        console.log(`  ✅ PASS: ${name}`);
    } catch (err) {
        console.error(`  ❌ FAIL: ${name}`);
        console.error(`     ${err.message}`);
    }
}

console.log('\n======================================================');
console.log(' MENJALANKAN PENGUJIAN UNIT (UNIT TESTS)');
console.log('======================================================\n');

// 1. PENGUJIAN NORMALISASI NOMOR TELEPON
console.log('--- 1. Phone Number Normalization Tests ---');

test('Konversi format 0812... ke 62812...', () => {
    assert.strictEqual(normalizePhone('081234567890'), '6281234567890');
});

test('Konversi format +628... ke 628...', () => {
    assert.strictEqual(normalizePhone('+6285159811407'), '6285159811407');
});

test('Mempertahankan format 628...', () => {
    assert.strictEqual(normalizePhone('6285159811407'), '6285159811407');
});

test('Membersihkan spasi, tanda strip, dan tanda kurung', () => {
    assert.strictEqual(normalizePhone('0851 - 5981 - 1407'), '6285159811407');
    assert.strictEqual(normalizePhone('(0851) 59811407'), '6285159811407');
});

test('Menolak nomor yang bukan nomor seluler Indonesia valid', () => {
    assert.strictEqual(normalizePhone('12345'), null);
    assert.strictEqual(normalizePhone('0217654321'), null); // Nomor rumah Jakarta
    assert.strictEqual(normalizePhone('abc0812345'), null);
    assert.strictEqual(normalizePhone(''), null);
    assert.strictEqual(normalizePhone(null), null);
});

test('Format JID Baileys WhatsApp', () => {
    assert.strictEqual(formatBaileysJid('085159811407'), '6285159811407@s.whatsapp.net');
    assert.strictEqual(formatBaileysJid('invalid'), null);
});

// 2. PENGUJIAN FORMAT TANGGAL DAN TEMPLATE PESAN
console.log('\n--- 2. Date Formatting & Message Template Tests ---');

test('Format tanggal Indonesia', () => {
    assert.strictEqual(formatTanggalIndo('2026-08-25'), 'Selasa, 25 Agustus 2026');
    assert.strictEqual(formatTanggalIndo('2026-01-01'), 'Kamis, 1 Januari 2026');
    assert.strictEqual(formatTanggalIndo('2026-12-31'), 'Kamis, 31 Desember 2026');
});

test('Penyusunan pesan WhatsApp metode Video Call WA', () => {
    const msg = buildFpeMessage({
        namaPasien: 'Budi Santoso',
        namaKeluarga: 'Ibu Siti',
        tanggalPelaksanaan: '2026-08-25',
        jamPelaksanaan: '10:00:00',
        metode: 'video_call_wa',
        slotWaktu: '10.00-12.00'
    });

    assert(msg.includes('RSKD Duren Sawit'), 'Harus menyertakan nama RS');
    assert(msg.includes('Budi Santoso'), 'Harus menyertakan nama pasien');
    assert(msg.includes('Ibu Siti'), 'Harus menyertakan nama keluarga');
    assert(msg.includes('25 Agustus 2026'), 'Harus menyertakan tanggal format Indonesia');
    assert(msg.includes('Video Call WhatsApp'), 'Harus menyebutkan metode');
    assert(!msg.includes('Meeting ID'), 'Tidak boleh ada Meeting ID untuk Video Call');
});

test('Penyusunan pesan WhatsApp metode Zoom Meeting', () => {
    const msg = buildFpeMessage({
        namaPasien: 'Budi Santoso',
        namaKeluarga: 'Ibu Siti',
        tanggalPelaksanaan: '2026-08-25',
        jamPelaksanaan: '14:00:00',
        metode: 'zoom_meeting',
        slotWaktu: '14.00-15.00',
        meetingId: '838 1051 3404',
        passcode: 'rskdds'
    });

    assert(msg.includes('Zoom Meeting'), 'Harus menyebutkan metode Zoom');
    assert(msg.includes('838 1051 3404'), 'Harus menyertakan Meeting ID');
    assert(msg.includes('rskdds'), 'Harus menyertakan Passcode');
});

// 3. PENGUJIAN KONTRAK PROVIDER
console.log('\n--- 3. WhatsApp Provider Interface Tests ---');

test('WhatsAppProvider abstract class throws when methods not implemented', async () => {
    const p = new WhatsAppProvider();
    await assert.rejects(async () => await p.connect(), /connect\(\) must be implemented/);
    await assert.rejects(async () => await p.sendMessage({ to: '123', message: 'test' }), /sendMessage\(\) must be implemented/);
});

console.log('\n======================================================');
console.log(` HASIL: ${passedTests} / ${totalTests} Pengujian Berhasil (${Math.round(passedTests/totalTests*100)}%)`);
console.log('======================================================\n');

if (passedTests !== totalTests) {
    process.exit(1);
}
