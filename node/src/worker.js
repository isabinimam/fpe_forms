/**
 * FPE WHATSAPP NOTIFICATION QUEUE WORKER
 * RSKD Duren Sawit
 *
 * Independent background worker that polls SQL Server queue and sends WhatsApp messages
 */

const path = require('path');
const os = require('os');
const http = require('http');
const dotenv = require('dotenv');

// Load .env from node directory, fallback to root directory
const nodeEnv = path.join(__dirname, '../.env');
const rootEnv = path.join(__dirname, '../../.env');
if (require('fs').existsSync(nodeEnv)) {
    dotenv.config({ path: nodeEnv });
} else if (require('fs').existsSync(rootEnv)) {
    dotenv.config({ path: rootEnv });
} else {
    dotenv.config();
}

const { getPool, closePool } = require('./db');
const { recoverStaleJobs, claimDueJobs, markJobSent, markJobFailed } = require('./queue');
const BaileysProvider = require('./whatsapp/BaileysProvider');
const CloudApiProvider = require('./whatsapp/CloudApiProvider');

// Konfigurasi Worker
const WORKER_ID = `worker-${os.hostname()}-${process.pid}-${Date.now().toString(36)}`;
const POLL_INTERVAL_MS = parseInt(process.env.QUEUE_POLL_INTERVAL_MS || '30000', 10);
const STALE_TIMEOUT_MINUTES = parseInt(process.env.QUEUE_PROCESSING_TIMEOUT_MINUTES || '10', 10);
const HEALTH_PORT = parseInt(process.env.HEALTH_PORT || '3001', 10);
const PROVIDER_TYPE = (process.env.WHATSAPP_PROVIDER || 'baileys').toLowerCase();

let pool = null;
let provider = null;
let isPolling = false;
let pollTimer = null;
let isShuttingDown = false;

/**
 * Initializes and starts the WhatsApp provider
 */
async function initProvider() {
    console.log(`[WORKER] Memilih WhatsApp Provider: [${PROVIDER_TYPE.toUpperCase()}]`);

    if (PROVIDER_TYPE === 'cloud_api') {
        provider = new CloudApiProvider();
    } else {
        provider = new BaileysProvider();
    }

    try {
        await provider.connect();
    } catch (err) {
        console.error(`[WORKER WARNING] Gagal menginisialisasi provider WhatsApp: ${err.message}`);
    }
}

/**
 * Single queue polling cycle
 */
async function processQueueCycle() {
    if (isPolling || isShuttingDown) return;
    isPolling = true;

    try {
        if (!pool || !pool.connected) {
            pool = await getPool();
        }

        // 1. Pulihkan antrean yang macet (stale recovery)
        await recoverStaleJobs(pool, STALE_TIMEOUT_MINUTES);

        // 2. Periksa kesiapan WhatsApp provider
        const isProviderReady = provider && provider.isReady();
        if (!isProviderReady) {
            // Jika Baileys sedang menunggu scan QR, beri info berkala
            const status = provider ? provider.getStatus() : {};
            if (status.hasQrWaiting) {
                // QR sedang menunggu
            } else {
                console.log(`[WORKER] Menunggu WhatsApp provider siap (Status: ${JSON.stringify(status)})...`);
            }
            isPolling = false;
            return;
        }

        // 3. Klaim antrean yang sudah jatuh tempo (due jobs)
        const dueJobs = await claimDueJobs(pool, WORKER_ID, 5);

        if (dueJobs.length > 0) {
            console.log(`\n[WORKER] 🔔 Menemukan ${dueJobs.length} antrean notifikasi siap kirim:`);

            for (const job of dueJobs) {
                if (isShuttingDown) break;

                console.log(`[WORKER] ➡️  Memproses antrean #${job.id} (Jadwal #${job.id_jadwal}) ke nomor +${job.nomor_tujuan}...`);

                try {
                    const result = await provider.sendMessage({
                        to: job.nomor_tujuan,
                        message: job.pesan
                    });

                    await markJobSent(pool, job.id, result.messageId);
                } catch (sendErr) {
                    await markJobFailed(pool, job.id, job.attempts, job.max_attempts, sendErr.message);
                }

                // Jeda pengiriman antar nomor (rate limiting aman: 2 detik)
                await new Promise(r => setTimeout(r, 2000));
            }
        }
    } catch (cycleErr) {
        console.error(`[WORKER ERROR] Kesalahan siklus polling: ${cycleErr.message}`);
    } finally {
        isPolling = false;
    }
}

/**
 * Starts the lightweight HTTP health check endpoint
 */
function startHealthServer() {
    const server = http.createServer((req, res) => {
        if (req.url === '/health' || req.url === '/status') {
            const dbConnected = pool ? pool.connected : false;
            const waStatus = provider ? provider.getStatus() : { ready: false };

            const response = {
                status: dbConnected && waStatus.ready ? 'ok' : 'degraded',
                workerId: WORKER_ID,
                timestamp: new Date().toISOString(),
                database: {
                    connected: dbConnected,
                    server: process.env.DB_SERVER || 'localhost',
                    instance: process.env.DB_INSTANCE || 'SQLEXPRESS',
                    database: process.env.DB_DATABASE || 'form_pfe'
                },
                whatsapp: waStatus
            };

            res.writeHead(response.status === 'ok' ? 200 : 503, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify(response, null, 2));
        } else {
            res.writeHead(404, { 'Content-Type': 'text/plain' });
            res.end('Endpoint tidak ditemukan. Gunakan /health.');
        }
    });

    server.listen(HEALTH_PORT, () => {
        console.log(`[WORKER] Health check server aktif di http://localhost:${HEALTH_PORT}/health`);
    });

    return server;
}

/**
 * Main application bootstrap
 */
async function main() {
    console.log('===============================================================');
    console.log(' RSKD DUREN SAWIT - FPE WHATSAPP NOTIFICATION QUEUE WORKER');
    console.log(` Worker ID        : ${WORKER_ID}`);
    console.log(` Database Server  : ${process.env.DB_SERVER || 'localhost'}\\${process.env.DB_INSTANCE || 'SQLEXPRESS'}`);
    console.log(` Database Name    : ${process.env.DB_DATABASE || 'form_pfe'}`);
    console.log(` WhatsApp Provider: ${PROVIDER_TYPE.toUpperCase()}`);
    console.log(` Polling Interval : ${POLL_INTERVAL_MS / 1000} detik`);
    console.log('===============================================================\n');

    // 1. Hubungkan ke database
    try {
        pool = await getPool();
    } catch (err) {
        console.warn('[WORKER] Koneksi DB awal gagal, worker akan mencoba lagi pada siklus polling berikutnya.');
    }

    // 2. Inisialisasi WhatsApp Provider
    await initProvider();

    // 3. Jalankan HTTP Health Server
    const healthServer = startHealthServer();

    // 4. Mulai interval polling
    console.log(`[WORKER] Memulai loop polling antrean setiap ${POLL_INTERVAL_MS / 1000} detik...`);
    // Jalankan siklus pertama segera
    processQueueCycle();
    pollTimer = setInterval(processQueueCycle, POLL_INTERVAL_MS);

    // 5. Penanganan Shutdown Anggun (Graceful Shutdown)
    const handleShutdown = async (signal) => {
        if (isShuttingDown) return;
        isShuttingDown = true;
        console.log(`\n[WORKER] Menerima sinyal ${signal}. Menutup proses secara anggun...`);

        if (pollTimer) clearInterval(pollTimer);
        healthServer.close();

        if (provider) {
            await provider.disconnect();
        }

        await closePool();
        console.log('[WORKER] Selesai. Worker berhenti.');
        process.exit(0);
    };

    process.on('SIGINT', () => handleShutdown('SIGINT'));
    process.on('SIGTERM', () => handleShutdown('SIGTERM'));
}

main().catch((err) => {
    console.error(`[FATAL ERROR] Worker berhenti karena kesalahan fatal: ${err.message}`);
    process.exit(1);
});
