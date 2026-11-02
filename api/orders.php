<?php
/**
 * GET                 — daftar pesanan milik pengguna
 * GET ?invoice=XXX    — detail pesanan (sekaligus memperbarui status)
 * POST                — buat pesanan
 *     prabayar  : {sku, customer_no, payment, customer_name?}
 *     pascabayar: {inquiry_id, payment}
 *     digital   : {digital: slug, payment, buyer_note?}
 */
require __DIR__ . '/bootstrap.php';
$method = Http::allow('GET', 'POST');
$user = Auth::require();

if ($method === 'POST') {
    Auth::verifyCsrf();
    $body = Http::body();
    $order = !empty($body['digital']) ? DigitalService::createOrder($user, $body) : OrderService::create($user, $body);
    Http::ok(['order' => $order]);
}

$invoice = isset($_GET['invoice']) ? substr((string)$_GET['invoice'], 0, 32) : '';
if ($invoice !== '') {
    $row = Db::one('SELECT * FROM orders WHERE invoice = ? AND user_id = ?', [$invoice, $user['id']]);
    if (!$row || $row['status'] === 'inquiry') throw new HttpError('Pesanan tidak ditemukan.', 404);
    Http::ok(['order' => OrderService::present(OrderService::refresh($row))]);
}

// Perbarui pesanan yang masih menunggu (maks 5) agar daftar selalu terkini
$rows = Db::all("SELECT * FROM orders WHERE user_id = ? AND status <> 'inquiry' ORDER BY id DESC LIMIT 100", [$user['id']]);
$refreshed = 0;
foreach ($rows as $i => $r) {
    if (in_array($r['status'], ['unpaid', 'processing'], true) && $refreshed < 5) {
        $rows[$i] = OrderService::refresh($r);
        $refreshed++;
    }
}
$stats = Db::one("SELECT COUNT(*) AS n, COALESCE(SUM(total),0) AS spent FROM orders WHERE user_id = ? AND status = 'success'", [$user['id']]);
Http::ok([
    'orders' => array_map([OrderService::class, 'present'], $rows),
    'stats'  => ['success' => (int)$stats['n'], 'spent' => (int)$stats['spent']],
]);
