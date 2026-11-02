<?php
/** POST {brand, customer_no} — cek nama pelanggan PLN prabayar. */
require __DIR__ . '/bootstrap.php';
Http::allow('POST');
Auth::verifyCsrf();
$user = Auth::require();
Http::ok(OrderService::checkName($user, Http::str('brand', 60), Http::str('customer_no', 64)));
