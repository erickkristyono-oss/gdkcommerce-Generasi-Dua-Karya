<?php
/** GET — ringkasan untuk dashboard admin. */
require __DIR__ . '/../bootstrap.php';
Http::allow('GET');
Admin::require();

$range = function (string $from): array {
    $r = Db::one(
        "SELECT COUNT(*) AS n, COALESCE(SUM(total),0) AS omzet,
                COALESCE(SUM(price - COALESCE(supplier_price, price)),0) AS laba
         FROM orders WHERE status = 'success' AND kind <> 'deposit' AND completed_at >= ?", [$from]);
    return ['count' => (int)$r['n'], 'omzet' => (int)$r['omzet'], 'laba' => (int)$r['laba']];
};
$today = date('Y-m-d 00:00:00');

// Seri harian 14 hari (hari tanpa transaksi tetap muncul dengan nilai 0)
$days = 14;
$start = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
$rows = Db::all(
    "SELECT DATE(completed_at) AS d, COUNT(*) AS n, SUM(total) AS omzet, SUM(price - COALESCE(supplier_price, price)) AS laba
     FROM orders WHERE status = 'success' AND kind <> 'deposit' AND completed_at >= ? GROUP BY DATE(completed_at)", [$start . ' 00:00:00']);
$byDay = [];
foreach ($rows as $r) $byDay[$r['d']] = $r;
$series = [];
for ($i = 0; $i < $days; $i++) {
    $d = date('Y-m-d', strtotime($start . " +{$i} days"));
    $series[] = ['date' => $d, 'count' => (int)($byDay[$d]['n'] ?? 0), 'omzet' => (int)($byDay[$d]['omzet'] ?? 0), 'laba' => (int)($byDay[$d]['laba'] ?? 0)];
}

$statusCounts = [];
foreach (Db::all("SELECT status, COUNT(*) AS n FROM orders WHERE status <> 'inquiry' AND kind <> 'deposit' AND created_at >= (NOW() - INTERVAL 30 DAY) GROUP BY status") as $r) {
    $statusCounts[$r['status']] = (int)$r['n'];
}

$refund = Db::one("SELECT COUNT(*) AS n, COALESCE(SUM(total),0) AS amount FROM orders WHERE status = 'failed' AND refund_status = 'pending'");
$alerts = [
    'refund_pending'  => (int)$refund['n'],
    'refund_amount'   => (int)$refund['amount'],
    'processing'      => (int)Db::value("SELECT COUNT(*) FROM orders WHERE status = 'processing'"),
    'stuck'           => (int)Db::value("SELECT COUNT(*) FROM orders WHERE kind = 'ppob' AND status = 'processing' AND paid_at < (NOW() - INTERVAL 30 MINUTE)"),
    'unpaid'          => (int)Db::value("SELECT COUNT(*) FROM orders WHERE status = 'unpaid' AND pay_expired_at > NOW()"),
    'unavailable'     => (int)Db::value('SELECT COUNT(*) FROM products WHERE is_active = 1 AND supplier_ok = 0'),
    'digital_waiting' => (int)Db::value("SELECT COUNT(*) FROM orders WHERE kind = 'digital' AND status = 'processing'"),
    'wallet_total'    => (int)Db::value('SELECT COALESCE(SUM(balance),0) FROM users'),
    'auto_refunded7'  => (int)Db::value("SELECT COUNT(*) FROM orders WHERE refund_method = 'saldo' AND refunded_at >= (NOW() - INTERVAL 7 DAY)"),
    'agents_pending'  => (int)Db::value("SELECT COUNT(*) FROM users WHERE agent_status = 'pending' AND role = 'customer'"),
];
$agents = Db::one(
    "SELECT COUNT(*) AS n, COALESCE(SUM(o.total),0) AS omzet FROM orders o
     WHERE o.is_agent = 1 AND o.kind <> 'deposit' AND o.status = 'success' AND o.completed_at >= (NOW() - INTERVAL 30 DAY)");

$top = Db::all(
    "SELECT brand_name AS brand, COUNT(*) AS n, SUM(total) AS omzet, SUM(price - COALESCE(supplier_price, price)) AS laba
     FROM orders WHERE status = 'success' AND kind <> 'deposit' AND completed_at >= (NOW() - INTERVAL 30 DAY)
     GROUP BY brand_name ORDER BY omzet DESC LIMIT 6");

$balance = null; $balanceError = null;
try { $balance = Supplier\Factory::make()->balance(); }
catch (Throwable $e) { $balanceError = $e instanceof HttpError ? $e->getMessage() : 'Gagal mengambil saldo supplier.'; error_log('[GDK] balance: ' . $e->getMessage()); }

Http::ok([
    'ranges' => [
        'today' => $range($today),
        'd7'    => $range(date('Y-m-d 00:00:00', strtotime('-6 days'))),
        'd30'   => $range(date('Y-m-d 00:00:00', strtotime('-29 days'))),
    ],
    'series'   => $series,
    'status'   => $statusCounts,
    'alerts'   => $alerts,
    'top'      => array_map(fn($r) => ['brand' => $r['brand'], 'count' => (int)$r['n'], 'omzet' => (int)$r['omzet'], 'laba' => (int)$r['laba']], $top),
    'users'    => [
        'total' => (int)Db::value('SELECT COUNT(*) FROM users'),
        'new7'  => (int)Db::value('SELECT COUNT(*) FROM users WHERE created_at >= (NOW() - INTERVAL 7 DAY)'),
        'agents' => (int)Db::value("SELECT COUNT(*) FROM users WHERE role = 'agent'"),
    ],
    'agent_sales' => ['count' => (int)$agents['n'], 'omzet' => (int)$agents['omzet']],
    'supplier' => ['driver' => config('supplier.driver'), 'testing' => (bool)config('supplier.digiflazz.testing', false), 'balance' => $balance, 'error' => $balanceError],
]);
