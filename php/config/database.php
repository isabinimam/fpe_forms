<?php
/**
 * KONEKSI DATABASE SQL SERVER (sqlsrv Driver)
 * RSKD Duren Sawit - Sistem FPE & Antrean WhatsApp
 *
 * Menggunakan direct sqlsrv_* functions dengan Windows Authentication
 */

require_once __DIR__ . '/app.php';

// Konfigurasi driver sqlsrv: jangan anggap pesan info (seperti perubahan konteks DB/bahasa) sebagai error
sqlsrv_configure("WarningsReturnAsErrors", 0);

$dbServer   = getenv('DB_SERVER') ?: 'localhost\\SQLEXPRESS';
$dbDatabase = getenv('DB_DATABASE') ?: 'form_pfe';

// Konfigurasi koneksi Windows Authentication
$connectionInfo = [
    "Database"               => $dbDatabase,
    "CharacterSet"           => "UTF-8",
    "TrustServerCertificate" => true,
];

// Buat koneksi sqlsrv
$conn = sqlsrv_connect($dbServer, $connectionInfo);

if ($conn === false) {
    $errors = sqlsrv_errors();
    $errorMsg = "Koneksi ke database SQL Server ($dbServer / $dbDatabase) gagal:<br>";
    if ($errors !== null) {
        foreach ($errors as $error) {
            $errorMsg .= "SQLSTATE: " . $error['SQLSTATE'] . " | Kode: " . $error['code'] . " | Pesan: " . htmlspecialchars($error['message']) . "<br>";
        }
    }
    die($errorMsg);
}

return $conn;
