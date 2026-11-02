<?php
/**
 * Program Agen
 * GET                                   — status agen, statistik & batas deposit
 * POST {action: apply, store, city, note?, agree}   — ajukan diri menjadi agen
 * POST {action: deposit, amount, payment}           — isi saldo (khusus agen) → pesanan deposit
 */
require __DIR__ . '/bootstrap.php';
$method = Http::allow('GET', 'POST');
$user = Auth::require();

if ($method === 'POST') {
    Auth::verifyCsrf();
    $action = Http::str('action', 20);
    if ($action === 'apply') {
        Agent::apply($user, Http::body());
        Auth::reload();
        Http::ok(['message' => 'Pengajuan agen terkirim. Admin akan meninjau dalam 1×24 jam.', 'agent' => Agent::summary(Auth::user()), 'user' => Auth::publicUser(Auth::user())]);
    }
    if ($action === 'deposit') {
        Http::ok(['order' => Agent::createDeposit($user, Http::body())]);
    }
    throw new HttpError('Aksi tidak dikenal.', 400);
}

Http::ok(['agent' => Agent::summary($user)]);
