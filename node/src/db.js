/**
 * SQL SERVER DATABASE CONNECTION POOL (msnodesqlv8 - Native Windows Authentication)
 * RSKD Duren Sawit - WhatsApp Queue Worker
 *
 * Menggunakan driver native msnodesqlv8 dengan ODBC Driver 17 / Windows Authentication
 */

const sql = require('mssql/msnodesqlv8');

let pool = null;

/**
 * Membangun konfigurasi koneksi SQL Server
 */
function getConfig() {
    const server = process.env.DB_SERVER || 'localhost';
    const instance = process.env.DB_INSTANCE || 'SQLEXPRESS';
    const serverInstance = instance ? `${server}\\${instance}` : server;
    const database = process.env.DB_DATABASE || 'form_pfe';
    const user = process.env.DB_USER || '';
    const password = process.env.DB_PASSWORD || '';
    const odbcDriver = process.env.DB_ODBC_DRIVER || 'ODBC Driver 17 for SQL Server';

    // Jika username dan password SQL diisi, gunakan SQL Server Auth
    if (user && password) {
        return {
            connectionString: `Server=${serverInstance};Database=${database};Uid=${user};Pwd=${password};Driver={${odbcDriver}};TrustServerCertificate=Yes;`
        };
    }

    // Windows Authentication (Trusted Connection)
    return {
        connectionString: `Server=${serverInstance};Database=${database};Trusted_Connection=Yes;Driver={${odbcDriver}};TrustServerCertificate=Yes;`
    };
}

/**
 * Menghubungkan ke database SQL Server dan mengembalikan connection pool
 */
async function getPool() {
    if (pool && pool.connected) {
        return pool;
    }

    const config = getConfig();
    try {
        const serverName = process.env.DB_SERVER || 'localhost';
        const instanceName = process.env.DB_INSTANCE || 'SQLEXPRESS';
        const database = process.env.DB_DATABASE || 'form_pfe';

        console.log(`[DB] Menghubungkan ke SQL Server (${serverName}\\${instanceName} - DB: ${database}) via Windows Authentication...`);
        pool = await sql.connect(config);
        console.log(`[DB] Berhasil terhubung ke SQL Server [${database}] dengan Windows Authentication.`);
        return pool;
    } catch (err) {
        console.error(`[DB ERROR] Gagal terhubung ke SQL Server: ${err.message || err}`);
        throw err;
    }
}

/**
 * Menutup koneksi database dengan anggun
 */
async function closePool() {
    if (pool) {
        try {
            await pool.close();
            console.log('[DB] Koneksi database ditutup.');
        } catch (err) {
            console.error(`[DB ERROR] Gagal menutup koneksi: ${err.message || err}`);
        }
        pool = null;
    }
}

module.exports = {
    sql,
    getPool,
    closePool
};
