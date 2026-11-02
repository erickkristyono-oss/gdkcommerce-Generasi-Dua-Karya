<?php
/**
 * GET  — mulai setup: secret + URI otpauth untuk QR
 * POST {code, password} — aktifkan; balasan berisi kode cadangan (hanya ditampilkan sekali)
 */
require __DIR__ . '/../bootstrap.php';
$method = Http::allow('GET', 'POST');
$user = Auth::require();
$row = Db::one('SELECT totp_enabled FROM users WHERE id = ?', [$user['id']]);
if ($row['totp_enabled']) throw new HttpError('2FA sudah aktif.', 409);

if ($method === 'GET') Http::ok(TwoFactor::begin($user));

Auth::verifyCsrf();
$codes = TwoFactor::confirm($user, Http::str('code', 12), (string)Http::input('password', ''));
Auth::reload();
Http::ok(['recovery_codes' => $codes, 'user' => Auth::publicUser(Auth::user()), 'csrf' => Auth::csrfToken()]);
