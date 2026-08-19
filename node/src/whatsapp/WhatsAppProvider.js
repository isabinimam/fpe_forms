/**
 * WHATSAPP PROVIDER ABSTRACT BASE CLASS / INTERFACE
 * RSKD Duren Sawit - WhatsApp Notification Queue
 *
 * Defines the contract for WhatsApp delivery providers (Baileys, WhatsApp Cloud API, etc.)
 */

class WhatsAppProvider {
    /**
     * Connects to WhatsApp service / authenticates session
     * @returns {Promise<void>}
     */
    async connect() {
        throw new Error('connect() must be implemented by provider');
    }

    /**
     * Disconnects / closes session
     * @returns {Promise<void>}
     */
    async disconnect() {
        throw new Error('disconnect() must be implemented by provider');
    }

    /**
     * Checks if provider is ready to send messages
     * @returns {boolean}
     */
    isReady() {
        throw new Error('isReady() must be implemented by provider');
    }

    /**
     * Sends a WhatsApp text message
     * @param {Object} params
     * @param {string} params.to - Canonical phone number (e.g. 628123456789)
     * @param {string} params.message - Text message content
     * @returns {Promise<{ success: boolean, messageId?: string }>}
     */
    async sendMessage({ to, message }) {
        throw new Error('sendMessage() must be implemented by provider');
    }

    /**
     * Returns provider status information
     * @returns {Object}
     */
    getStatus() {
        throw new Error('getStatus() must be implemented by provider');
    }
}

module.exports = WhatsAppProvider;
