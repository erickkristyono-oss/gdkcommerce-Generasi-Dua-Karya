<?php
/** GET — Saldo GDK & mutasi milik pengguna yang login. */
require __DIR__ . '/bootstrap.php';
Http::allow('GET');
$user = Auth::require();
Http::ok(['balance' => Wallet::balance((int)$user['id']), 'history' => Wallet::history((int)$user['id'], 50)]);
