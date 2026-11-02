<?php
/**
 * GET  — status supplier, saldo, sinkron terakhir, log API terbaru
 * POST {action: sync} — sinkron produk sekarang
 */
require __DIR__ . '/../bootstrap.php';
$method = Http::allow('GET', 'POST');
Admin::require();

if ($method === 'POST') {
    if (Http::str('action', 20) !== 'sync') throw new HttpError('Aksi tidak dikenal.', 400);
    RateLimit::check('admin-sync', 'global', 3, 300, 'Sinkronisasi baru saja dijalankan. Tunggu beberapa menit (batas API price list Digiflazz).');
    RateLimit::hit('admin-sync', 'global');
    try {
        $r = ProductSync::run();
    } catch (HttpError $e) {
        throw $e;
    } catch (Throwable $e) {
        error_log('[GDK] sync: ' . $e->getMessage());
        throw new HttpError('Sinkronisasi gagal: ' . $e->getMessage(), 502);
    }
    Admin::audit('supplier.sync', null, ['result' => array_diff_key($r, ['unmapped' => 1])]);
    Http::ok(['report' => $r]);
}

$driver = config('supplier.driver');
$balance = null; $error = null;
try { $balance = Supplier\Factory::make()->balance(); }
catch (Throwable $e) { $error = $e instanceof HttpError ? $e->getMessage() : 'Gagal mengambil saldo: ' . $e->getMessage(); }

$lastSync = Db::one("SELECT response, created_at FROM api_logs WHERE channel = 'sync' ORDER BY id DESC LIMIT 1");
$logs = Db::all(
    "SELECT l.channel, l.action, l.http_code, l.response, l.created_at, o.invoice
     FROM api_logs l LEFT JOIN orders o ON o.id = l.order_id
     WHERE l.channel IN ('digiflazz','mock','webhook','payment') ORDER BY l.id DESC LIMIT 15");

Http::ok([
    'driver'   => $driver,
    'testing'  => (bool)config('supplier.digiflazz.testing', false),
    'payment'  => config('payment.driver'),
    'can_simulate' => OrderService::canSimulatePayment(),
    'balance'  => $balance,
    'error'    => $error,
    'markup'   => config('supplier.markup'),
    'products' => [
        'live'        => (int)Db::value('SELECT COUNT(*) FROM products WHERE is_active = 1 AND supplier_ok = 1'),
        'unavailable' => (int)Db::value('SELECT COUNT(*) FROM products WHERE supplier_ok = 0'),
        'inactive'    => (int)Db::value('SELECT COUNT(*) FROM products WHERE is_active = 0'),
    ],
    'last_sync' => $lastSync ? ['at' => strtotime($lastSync['created_at']) * 1000, 'report' => json_decode($lastSync['response'], true)] : null,
    'logs' => array_map(fn($l) => [
        'channel' => $l['channel'], 'action' => $l['action'], 'http' => $l['http_code'] !== null ? (int)$l['http_code'] : null,
        'invoice' => $l['invoice'], 'response' => $l['response'] !== null ? mb_substr($l['response'], 0, 300) : null,
        'at' => strtotime($l['created_at']) * 1000,
    ], $logs),
]);
