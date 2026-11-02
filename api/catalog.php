<?php
/** GET — kategori, produk (dengan harga), dan metode pembayaran. */
require __DIR__ . '/bootstrap.php';
Http::allow('GET');
Http::ok(OrderService::catalog());
