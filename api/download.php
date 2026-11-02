<?php
/** GET ?invoice=XXX — unduh file produk digital (hanya pemilik pesanan yang sudah lunas). */
require __DIR__ . '/bootstrap.php';
Http::allow('GET');
$user = Auth::require();
DigitalService::streamDownload($user, substr((string)($_GET['invoice'] ?? ''), 0, 32));
