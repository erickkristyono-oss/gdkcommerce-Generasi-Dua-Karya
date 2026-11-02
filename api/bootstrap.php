<?php
/**
 * GDK Commerce — Bootstrap
 * Dimuat oleh setiap endpoint API.
 */
declare(strict_types=1);

define('GDK_ROOT', dirname(__DIR__));
define('GDK_API', __DIR__);

// ---------- Konfigurasi ----------
(function () {
    $candidates = [
        getenv('GDK_CONFIG') ?: null,
        dirname(GDK_ROOT) . '/gdk-config.php', // di luar public_html (disarankan)
        GDK_API . '/config.php',
    ];
    foreach ($candidates as $file) {
        if ($file && is_file($file)) {
            $GLOBALS['GDK_CONFIG'] = require $file;
            return;
        }
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Konfigurasi belum dibuat. Salin api/config.sample.php menjadi api/config.php.']);
    exit;
})();

function config(string $path, $default = null)
{
    $value = $GLOBALS['GDK_CONFIG'];
    foreach (explode('.', $path) as $key) {
        if (!is_array($value) || !array_key_exists($key, $value)) return $default;
        $value = $value[$key];
    }
    return $value;
}

date_default_timezone_set(config('app.timezone', 'Asia/Jakarta'));

$isProd = config('app.env') === 'production';
ini_set('display_errors', $isProd ? '0' : '1');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// ---------- Autoload sederhana ----------
spl_autoload_register(function (string $class) {
    $file = GDK_API . '/lib/' . str_replace('\\', '/', $class) . '.php';
    if (is_file($file)) require $file;
});

// ---------- Error handler: selalu balas JSON ----------
set_exception_handler(function (Throwable $e) {
    if ($e instanceof HttpError) {
        Http::json(['ok' => false, 'error' => $e->getMessage()] + $e->extra, $e->status);
    }
    error_log('[GDK] ' . $e);
    $msg = config('app.env') === 'production'
        ? 'Terjadi kesalahan pada server. Silakan coba lagi.'
        : get_class($e) . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine();
    Http::json(['ok' => false, 'error' => $msg], 500);
});
set_error_handler(function ($no, $str, $file, $line) {
    if (!(error_reporting() & $no)) return false;
    // Peringatan "deprecated" cukup dicatat, jangan menggagalkan transaksi (aman saat upgrade versi PHP)
    if ($no === E_DEPRECATED || $no === E_USER_DEPRECATED) { error_log("[GDK] deprecated: $str @ $file:$line"); return true; }
    throw new ErrorException($str, 0, $no, $file, $line);
});
