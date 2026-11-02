<?php
/**
 * POST {invoice} — HANYA untuk payment gateway DUMMY.
 * Mensimulasikan pelanggan membayar: membuat callback ber-signature
 * lalu memprosesnya lewat jalur yang sama dengan callback asli.
 */
require __DIR__ . '/../bootstrap.php';
Http::allow('POST');
Auth::verifyCsrf();
$user = Auth::require();

$gateway = Payment\Factory::make();
if (!$gateway instanceof Payment\DummyPayment) throw new HttpError('Simulasi hanya tersedia untuk payment dummy.', 403);
if (!OrderService::canSimulatePayment()) {
    throw new HttpError('Simulasi dinonaktifkan: supplier memakai saldo asli (Digiflazz produksi). Hubungkan payment gateway asli.', 403);
}

$row = Db::one("SELECT * FROM orders WHERE invoice = ? AND user_id = ? AND pay_provider = 'dummy'", [Http::str('invoice', 32), $user['id']]);
if (!$row) throw new HttpError('Pesanan tidak ditemukan.', 404);
if ($row['status'] !== 'unpaid') throw new HttpError('Pesanan ini tidak menunggu pembayaran.', 409);
if (strtotime((string)$row['pay_expired_at']) < time()) {
    Db::run("UPDATE orders SET status = 'expired' WHERE id = ? AND status = 'unpaid'", [$row['id']]);
    throw new HttpError('Batas waktu pembayaran sudah lewat.', 410);
}

$raw = json_encode(['reference' => $row['pay_reference'], 'invoice' => $row['invoice'], 'status' => 'PAID', 'amount' => (int)$row['total'], 'paid_at' => date('c')]);
$order = OrderService::handlePaymentCallback($raw, ['x-callback-signature' => $gateway->sign($raw)]);
Http::ok(['order' => $order]);
