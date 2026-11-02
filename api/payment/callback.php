<?php
/**
 * POST — Notifikasi pembayaran dari payment gateway.
 * Daftarkan URL ini di dashboard gateway: https://domainanda.com/api/payment/callback.php
 */
require __DIR__ . '/../bootstrap.php';
Http::allow('POST');
$headers = array_change_key_case(function_exists('getallheaders') ? getallheaders() : [], CASE_LOWER);
foreach ($_SERVER as $k => $v) {
    if (str_starts_with($k, 'HTTP_')) $headers[strtolower(str_replace('_', '-', substr($k, 5)))] = $v;
}
$order = OrderService::handlePaymentCallback(Http::rawBody(), $headers);
Http::ok(['invoice' => $order['invoice'], 'status' => $order['status']]);
