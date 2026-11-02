<?php
/** POST {brand, customer_no} — cek tagihan pascabayar. */
require __DIR__ . '/bootstrap.php';
Http::allow('POST');
Auth::verifyCsrf();
$user = Auth::require();
Http::ok(OrderService::inquiry($user, Http::str('brand', 60), Http::str('customer_no', 64)));
