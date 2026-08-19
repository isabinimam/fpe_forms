/**
 * QUEUE MANAGER & TRANSACTIONAL CLAIMING
 * RSKD Duren Sawit - WhatsApp Queue Worker
 *
 * Implements safe, concurrency-proof queue operations on SQL Server
 */

const { sql } = require('./db');

/**
 * Recovers stale jobs that were left in 'processing' status due to worker crash
 * @param {Object} pool - mssql connection pool
 * @param {number} timeoutMinutes - threshold in minutes (default 10)
 */
async function recoverStaleJobs(pool, timeoutMinutes = 10) {
    try {
        const query = `
            UPDATE tbl_wa_queue
            SET status = 'pending',
                locked_at = NULL,
                locked_by = NULL,
                last_error = 'Stale processing timeout recovered',
                updated_at = SYSDATETIME()
            WHERE status = 'processing'
              AND locked_at < DATEADD(minute, -@timeoutMinutes, SYSDATETIME())
        `;
        const request = pool.request();
        request.input('timeoutMinutes', sql.Int, timeoutMinutes);
        const result = await request.query(query);

        if (result.rowsAffected[0] > 0) {
            console.log(`[QUEUE] Memulihkan ${result.rowsAffected[0]} antrean macet (stale processing > ${timeoutMinutes} menit) kembali ke 'pending'.`);
        }
    } catch (err) {
        console.error(`[QUEUE ERROR] Gagal memulihkan stale jobs: ${err.message}`);
    }
}

/**
 * Atomically claims due jobs using UPDLOCK and READPAST
 * Prevents race conditions when multiple worker processes are running
 *
 * @param {Object} pool - mssql connection pool
 * @param {string} workerId - Identifier of current worker process
 * @param {number} batchSize - Maximum jobs to claim in one cycle
 * @returns {Promise<Array>} Array of claimed job objects
 */
async function claimDueJobs(pool, workerId, batchSize = 5) {
    try {
        const query = `
            WITH cte AS (
                SELECT TOP (@batchSize) 
                    id, id_jadwal, nomor_tujuan, pesan, attempts, max_attempts,
                    status, locked_at, locked_by, updated_at
                FROM tbl_wa_queue WITH (UPDLOCK, READPAST)
                WHERE status = 'pending' 
                  AND scheduled_at <= SYSDATETIME()
                ORDER BY scheduled_at ASC
            )
            UPDATE cte
            SET status = 'processing',
                locked_at = SYSDATETIME(),
                locked_by = @workerId,
                updated_at = SYSDATETIME()
            OUTPUT 
                INSERTED.id, 
                INSERTED.id_jadwal, 
                INSERTED.nomor_tujuan, 
                INSERTED.pesan, 
                INSERTED.attempts, 
                INSERTED.max_attempts;
        `;

        const request = pool.request();
        request.input('batchSize', sql.Int, batchSize);
        request.input('workerId', sql.NVarChar(100), workerId);

        const result = await request.query(query);
        return result.recordset || [];
    } catch (err) {
        console.error(`[QUEUE ERROR] Gagal mengklaim antrean: ${err.message}`);
        return [];
    }
}

/**
 * Marks a queue job as successfully sent
 * @param {Object} pool
 * @param {number} jobId
 * @param {string} providerMessageId
 */
async function markJobSent(pool, jobId, providerMessageId = null) {
    try {
        const query = `
            UPDATE tbl_wa_queue
            SET status = 'sent',
                sent_at = SYSDATETIME(),
                provider_message_id = @providerMessageId,
                attempts = attempts + 1,
                locked_at = NULL,
                locked_by = NULL,
                last_error = NULL,
                updated_at = SYSDATETIME()
            WHERE id = @jobId
        `;
        const request = pool.request();
        request.input('jobId', sql.Int, jobId);
        request.input('providerMessageId', sql.NVarChar(255), providerMessageId);
        await request.query(query);
        console.log(`[QUEUE] Antrean #${jobId} berhasil diperbarui ke status 'sent'.`);
    } catch (err) {
        console.error(`[QUEUE ERROR] Gagal memperbarui status sent #${jobId}: ${err.message}`);
    }
}

/**
 * Marks a queue job as failed with retry logic and exponential backoff
 * @param {Object} pool
 * @param {number} jobId
 * @param {number} currentAttempts
 * @param {number} maxAttempts
 * @param {string} errorMessage
 */
async function markJobFailed(pool, jobId, currentAttempts, maxAttempts, errorMessage) {
    try {
        const nextAttempts = currentAttempts + 1;
        const isPermanentlyFailed = nextAttempts >= maxAttempts;
        const newStatus = isPermanentlyFailed ? 'failed' : 'pending';

        // Backoff: 2 minutes * attempt count
        const backoffMinutes = nextAttempts * 2;

        const query = `
            UPDATE tbl_wa_queue
            SET status = @newStatus,
                attempts = @nextAttempts,
                scheduled_at = ${isPermanentlyFailed ? 'scheduled_at' : 'DATEADD(minute, @backoffMinutes, SYSDATETIME())'},
                locked_at = NULL,
                locked_by = NULL,
                last_error = @errorMessage,
                updated_at = SYSDATETIME()
            WHERE id = @jobId
        `;

        const request = pool.request();
        request.input('jobId', sql.Int, jobId);
        request.input('newStatus', sql.NVarChar(20), newStatus);
        request.input('nextAttempts', sql.Int, nextAttempts);
        request.input('backoffMinutes', sql.Int, backoffMinutes);
        request.input('errorMessage', sql.NVarChar(sql.MAX), errorMessage ? errorMessage.substring(0, 1000) : 'Unknown error');

        await request.query(query);

        if (isPermanentlyFailed) {
            console.log(`[QUEUE] Antrean #${jobId} GAGAL PERMANEN setelah ${nextAttempts} percobaan. Error: ${errorMessage}`);
        } else {
            console.log(`[QUEUE] Antrean #${jobId} gagal (${nextAttempts}/${maxAttempts}). Dijadwalkan ulang dalam ${backoffMinutes} menit. Error: ${errorMessage}`);
        }
    } catch (err) {
        console.error(`[QUEUE ERROR] Gagal mencatat kegagalan #${jobId}: ${err.message}`);
    }
}

module.exports = {
    recoverStaleJobs,
    claimDueJobs,
    markJobSent,
    markJobFailed
};
