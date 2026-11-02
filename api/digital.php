<?php
/**
 * GET                      — kategori + daftar produk digital (?category=&q=&sort=price_asc|price_desc|newest|popular&featured=1)
 * GET ?slug=xxx            — detail produk
 */
require __DIR__ . '/bootstrap.php';
Http::allow('GET');
if (!empty($_GET['slug'])) Http::ok(['product' => DigitalService::detail(substr((string)$_GET['slug'], 0, 120))]);
Http::ok(['categories' => DigitalService::categories(), 'products' => DigitalService::list($_GET)]);
