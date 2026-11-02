<?php
/** Cron boleh dijalankan via CLI (disarankan) atau via URL dengan ?token=cron_token. */
require __DIR__ . '/../bootstrap.php';
if (PHP_SAPI !== 'cli') {
    $token = (string)config('cron_token', '');
    if ($token === '' || !hash_equals($token, (string)($_GET['token'] ?? ''))) {
        http_response_code(403);
        exit('Forbidden');
    }
    header('Content-Type: text/plain; charset=utf-8');
}
set_exception_handler(function (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
    exit(1);
});
function out(string $s): void { echo $s . PHP_EOL; }
