<?php
/**
 * Webhook Digiflazz — update status transaksi otomatis.
 * Atur di dashboard Digiflazz → Atur Koneksi → API → Webhook:
 *   URL    : https://domainanda.com/api/webhook/digiflazz.php
 *   Secret : sama dengan supplier.digiflazz.webhook_secret di config
 */
require __DIR__ . '/../bootstrap.php';
Http::allow('POST');

$raw = Http::rawBody();
$secret = (string)config('supplier.digiflazz.webhook_secret', '');
$sig = $_SERVER['HTTP_X_HUB_SIGNATURE'] ?? '';
$expected = 'sha1=' . hash_hmac('sha1', $raw, $secret);
if ($secret === '' || !is_string($sig) || !hash_equals($expected, $sig)) {
    Db::log('webhook', 'digiflazz-bad-signature', null, $raw, null, 403);
    throw new HttpError('Signature tidak valid.', 403);
}

$payload = json_decode($raw, true);
$d = $payload['data'] ?? null;
if (!is_array($d) || empty($d['ref_id'])) throw new HttpError('Payload tidak valid.', 400);

// Hanya ref_id aktif yang diproses; webhook dari percobaan lama (sebelum kirim ulang) diabaikan.
$order = Db::one('SELECT id, status FROM orders WHERE supplier_ref = ?', [(string)$d['ref_id']]);
if (!$order && Db::value('SELECT 1 FROM orders WHERE invoice = ? OR supplier_ref LIKE ?', [preg_replace('/R\d+$/', '', (string)$d['ref_id']), (string)$d['ref_id'] . '%'])) {
    Db::log('webhook', 'digiflazz-stale-ref', null, $raw, null, 200);
    Http::ok(['ignored' => 'stale ref']);
}
Db::log('webhook', 'digiflazz-' . ($_SERVER['HTTP_X_DIGIFLAZZ_EVENT'] ?? 'event'), $order['id'] ?? null, $raw, null, 200);
if (!$order) Http::ok(['ignored' => true]);

if (in_array($d['status'] ?? '', ['Sukses', 'Gagal', 'Pending'], true)) {
    OrderService::applySupplierResult((int)$order['id'], [
        'status'  => $d['status'],
        'rc'      => isset($d['rc']) ? (string)$d['rc'] : null,
        'message' => (string)($d['message'] ?? ''),
        'sn'      => !empty($d['sn']) ? (string)$d['sn'] : null,
        'price'   => isset($d['price']) ? (int)$d['price'] : null,
    ]);
}
Http::ok();
