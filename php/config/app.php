<?php
/**
 * KONFIGURASI APLIKASI & ENVIRONMENT LOADER
 * RSKD Duren Sawit - Formulir FPE & Sistem Antrean WhatsApp
 */

// Set Zona Waktu Default ke WIB (Asia/Jakarta)
date_default_timezone_set('Asia/Jakarta');

// Fungsi helper sederhana untuk membaca file .env tanpa dependency eksternal
if (!function_exists('loadEnv')) {
    function loadEnv($path = null) {
        if ($path === null) {
            $path = dirname(__DIR__, 2) . '/.env';
        }
        if (!file_exists($path)) {
            $examplePath = dirname(__DIR__, 2) . '/.env.example';
            if (file_exists($examplePath)) {
                $path = $examplePath;
            } else {
                return;
            }
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                // Hapus tanda kutip jika ada
                $value = trim($value, '"\'');
                if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
                    putenv(sprintf('%s=%s', $key, $value));
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                }
            }
        }
    }
}

// Muat environment
loadEnv();

// Konstanta Aplikasi
if (!defined('APP_ENV')) {
    define('APP_ENV', getenv('APP_ENV') ?: 'local');
}
if (!defined('WA_NOTIFICATION_LEAD_DAYS')) {
    define('WA_NOTIFICATION_LEAD_DAYS', (int)(getenv('WA_NOTIFICATION_LEAD_DAYS') ?: 1));
}
if (!defined('WA_NOTIFICATION_TIME')) {
    define('WA_NOTIFICATION_TIME', getenv('WA_NOTIFICATION_TIME') ?: '09:00');
}
if (!defined('WA_TEST_MODE')) {
    $testMode = getenv('WA_TEST_MODE');
    define('WA_TEST_MODE', ($testMode === 'true' || $testMode === '1'));
}
