<?php
/**
 * Perawatan pesanan — jadwalkan tiap 5 menit di hPanel → Cron Jobs:
 *   php /home/uXXXX/domains/domainanda.com/public_html/api/cron/check-orders.php
 * - Pesanan belum dibayar yang lewat batas waktu → kedaluwarsa
 * - Pesanan 'Diproses' dicek ulang ke supplier (cadangan bila webhook tidak masuk)
 * - Draft cek tagihan lama dihapus
 */
require __DIR__ . '/_guard.php';

$expired = Db::run("UPDATE orders SET status = 'expired' WHERE status = 'unpaid' AND pay_expired_at < NOW()")->rowCount();
out("Kedaluwarsa: {$expired}");

$rows = Db::all("SELECT * FROM orders WHERE status = 'processing' AND kind = 'ppob' ORDER BY last_checked_at ASC LIMIT 50");
$done = 0;
foreach ($rows as $r) {
    $after = OrderService::refresh($r, 60);
    if ($after['status'] !== 'processing') $done++;
}
out('Dicek ulang: ' . count($rows) . ", selesai: {$done}");

$stuck = (int)Db::value("SELECT COUNT(*) FROM orders WHERE status = 'processing' AND kind = 'ppob' AND paid_at < (NOW() - INTERVAL 1 DAY)");
$dw = (int)Db::value("SELECT COUNT(*) FROM orders WHERE status = 'processing' AND kind = 'digital'");
if ($dw) out("PERHATIAN: {$dw} pesanan produk digital menunggu dikirim admin.");
if ($stuck) out("PERHATIAN: {$stuck} pesanan diproses > 24 jam. Periksa manual di dashboard Digiflazz.");

$failed = (int)Db::value("SELECT COUNT(*) FROM orders WHERE status = 'failed' AND refund_status = 'pending'");
if ($failed) out("PERHATIAN: {$failed} pesanan gagal menunggu refund manual.");

$del = Db::run("DELETE FROM orders WHERE status = 'inquiry' AND created_at < (NOW() - INTERVAL 1 DAY)")->rowCount();
out("Draft dihapus: {$del}");
