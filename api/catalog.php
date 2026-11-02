<?php
/** GET — kategori, produk (dengan harga), dan metode pembayaran. Agen yang login melihat harga agen. */
require __DIR__ . '/bootstrap.php';
Http::allow('GET');
Http::ok(OrderService::catalog(Auth::user())); // agen yang login mendapat harga agen
