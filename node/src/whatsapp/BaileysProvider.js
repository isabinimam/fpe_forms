/**
 * BAILEYS WHATSAPP PROVIDER (LOCAL / DEVELOPMENT / TESTING)
 * RSKD Duren Sawit - WhatsApp Queue Worker
 *
 * Implements real WhatsApp delivery using @whiskeysockets/baileys with persistent session
 */

const path = require('path');
const fs = require('fs');
const qrcode = require('qrcode-terminal');
const pino = require('pino');
const WhatsAppProvider = require('./WhatsAppProvider');
const { formatBaileysJid } = require('../phone');

class BaileysProvider extends WhatsAppProvider {
    constructor(options = {}) {
        super();
        this.authFolder = options.authFolder || path.join(__dirname, '../../auth_info');
        this.sock = null;
        this.connected = false;
        this.connecting = false;
        this.qrCode = null;
        this.lastDisconnectReason = null;
        this.logger = pino({ level: options.logLevel || 'silent' });
    }

    /**
     * Connects to WhatsApp using Baileys socket with persistent multi-file auth
     */
    async connect() {
        if (this.connected || this.connecting) {
            return;
        }

        this.connecting = true;

        // Ensure auth directory exists
        if (!fs.existsSync(this.authFolder)) {
            fs.mkdirSync(this.authFolder, { recursive: true });
        }

        try {
            // Lazy load Baileys library
            const { default: makeWASocket, DisconnectReason, useMultiFileAuthState, fetchLatestBaileysVersion } = require('@whiskeysockets/baileys');

            const { state, saveCreds } = await useMultiFileAuthState(this.authFolder);
            const { version } = await fetchLatestBaileysVersion().catch(() => ({ version: [2, 3000, 1015901307] }));

            console.log(`[Baileys] Menginisialisasi koneksi WhatsApp (Versi: ${version.join('.')})...`);

            this.sock = makeWASocket({
                version,
                auth: state,
                printQRInTerminal: false, // We use qrcode-terminal directly for cleaner output
                logger: this.logger,
                browser: ['RSKD Duren Sawit', 'Chrome', '1.0.0'],
                connectTimeoutMs: 60000,
                defaultQueryTimeoutMs: 60000,
                keepAliveIntervalMs: 30000
            });

            // Save credentials on update
            this.sock.ev.on('creds.update', saveCreds);

            // Handle connection updates
            this.sock.ev.on('connection.update', (update) => {
                const { connection, lastDisconnect, qr } = update;

                if (qr) {
                    this.qrCode = qr;
                    console.log('\n===============================================================');
                    console.log(' [WHATSAPP LOGIN] SILAKAN SCAN QR CODE INI DENGAN WHATSAPP:');
                    console.log(' Buka WhatsApp di HP > Perangkat Tertaut > Tautkan Perangkat');
                    console.log('===============================================================\n');
                    qrcode.generate(qr, { small: true });
                    console.log('\n===============================================================\n');
                }

                if (connection === 'close') {
                    this.connected = false;
                    this.connecting = false;
                    const statusCode = lastDisconnect?.error?.output?.statusCode;
                    const shouldReconnect = statusCode !== DisconnectReason.loggedOut;

                    this.lastDisconnectReason = statusCode || 'unknown';
                    console.log(`[Baileys] Koneksi WhatsApp terputus (Status: ${statusCode || 'unknown'}).`);

                    if (shouldReconnect) {
                        console.log('[Baileys] Menghubungkan ulang dalam 5 detik...');
                        setTimeout(() => this.connect(), 5000);
                    } else {
                        console.log('[Baileys] Sesi telah keluar (logged out). Hapus folder auth_info dan scan ulang QR code.');
                    }
                } else if (connection === 'open') {
                    this.connected = true;
                    this.connecting = false;
                    this.qrCode = null;
                    console.log('[Baileys] Berhasil terhubung ke WhatsApp! Sesi aktif dan siap mengirim pesan.');
                }
            });

        } catch (err) {
            this.connecting = false;
            this.connected = false;
            console.error(`[Baileys ERROR] Gagal menginisialisasi Baileys: ${err.message}`);
            throw err;
        }
    }

    /**
     * Closes WhatsApp connection gracefully
     */
    async disconnect() {
        if (this.sock) {
            try {
                this.sock.end(undefined);
                console.log('[Baileys] Sesi WhatsApp ditutup.');
            } catch (e) {
                // Ignore error on close
            }
            this.sock = null;
            this.connected = false;
            this.connecting = false;
        }
    }

    /**
     * Checks if Baileys is ready to send
     */
    isReady() {
        return this.connected && this.sock !== null;
    }

    /**
     * Sends message to target phone number via WhatsApp
     * @param {Object} params
     * @param {string} params.to - Phone number (format 628xxx)
     * @param {string} params.message - Text message
     */
    async sendMessage({ to, message }) {
        if (!this.isReady()) {
            throw new Error('WhatsApp provider (Baileys) belum terhubung. Pastikan QR code sudah di-scan.');
        }

        const jid = formatBaileysJid(to);
        if (!jid) {
            throw new Error(`Nomor telepon tujuan tidak valid: ${to}`);
        }

        console.log(`[Baileys] Mengirim pesan ke ${jid}...`);

        try {
            const result = await this.sock.sendMessage(jid, { text: message });
            const messageId = result?.key?.id || 'baileys-' + Date.now();
            console.log(`[Baileys] Pesan berhasil dikirim ke ${to} (Message ID: ${messageId}).`);
            return {
                success: true,
                messageId: messageId
            };
        } catch (err) {
            console.error(`[Baileys ERROR] Gagal mengirim pesan ke ${to}: ${err.message}`);
            throw err;
        }
    }

    /**
     * Returns provider status
     */
    getStatus() {
        return {
            provider: 'baileys',
            ready: this.isReady(),
            connected: this.connected,
            connecting: this.connecting,
            hasQrWaiting: this.qrCode !== null,
            lastDisconnectReason: this.lastDisconnectReason
        };
    }
}

module.exports = BaileysProvider;
