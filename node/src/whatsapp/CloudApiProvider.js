/**
 * OFFICIAL WHATSAPP CLOUD API PROVIDER (PRODUCTION READY)
 * RSKD Duren Sawit - WhatsApp Queue Worker
 *
 * Implements WhatsApp Business Platform Cloud API delivery
 */

const WhatsAppProvider = require('./WhatsAppProvider');
const { normalizePhone } = require('../phone');

class CloudApiProvider extends WhatsAppProvider {
    constructor(options = {}) {
        super();
        this.baseUrl = options.baseUrl || process.env.WA_CLOUD_API_BASE_URL || 'https://graph.facebook.com';
        this.apiVersion = options.apiVersion || process.env.WA_CLOUD_API_VERSION || 'v19.0';
        this.phoneNumberId = options.phoneNumberId || process.env.WA_PHONE_NUMBER_ID || '';
        this.accessToken = options.accessToken || process.env.WA_ACCESS_TOKEN || '';
    }

    async connect() {
        if (!this.phoneNumberId || !this.accessToken) {
            console.warn('[Cloud API] WA_PHONE_NUMBER_ID atau WA_ACCESS_TOKEN belum diset di .env. Pengiriman Cloud API belum aktif.');
            return;
        }
        console.log(`[Cloud API] Provider WhatsApp Cloud API aktif (Phone ID: ${this.phoneNumberId}, Version: ${this.apiVersion}).`);
    }

    async disconnect() {
        // Stateless HTTP API - no persistent connection to close
    }

    isReady() {
        return Boolean(this.phoneNumberId && this.accessToken);
    }

    async sendMessage({ to, message }) {
        if (!this.isReady()) {
            throw new Error('WhatsApp Cloud API belum dikonfigurasi. Periksa WA_PHONE_NUMBER_ID dan WA_ACCESS_TOKEN.');
        }

        const cleanPhone = normalizePhone(to);
        if (!cleanPhone) {
            throw new Error(`Nomor telepon tujuan tidak valid: ${to}`);
        }

        const url = `${this.baseUrl}/${this.apiVersion}/${this.phoneNumberId}/messages`;
        
        const payload = {
            messaging_product: 'whatsapp',
            recipient_type: 'individual',
            to: cleanPhone,
            type: 'text',
            text: {
                preview_url: false,
                body: message
            }
        };

        console.log(`[Cloud API] Mengirim pesan ke +${cleanPhone}...`);

        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${this.accessToken}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const data = await response.json();

        if (!response.ok) {
            const errorMsg = data?.error?.message || `HTTP ${response.status}: ${response.statusText}`;
            console.error(`[Cloud API ERROR] Gagal mengirim pesan ke ${to}: ${errorMsg}`);
            throw new Error(`WhatsApp Cloud API Error: ${errorMsg}`);
        }

        const messageId = data?.messages?.[0]?.id || 'cloud-api-' + Date.now();
        console.log(`[Cloud API] Pesan berhasil dikirim ke +${cleanPhone} (Message ID: ${messageId}).`);

        return {
            success: true,
            messageId: messageId
        };
    }

    getStatus() {
        return {
            provider: 'cloud_api',
            ready: this.isReady(),
            phoneNumberId: this.phoneNumberId ? `${this.phoneNumberId.substring(0, 4)}***` : null,
            apiVersion: this.apiVersion
        };
    }
}

module.exports = CloudApiProvider;
