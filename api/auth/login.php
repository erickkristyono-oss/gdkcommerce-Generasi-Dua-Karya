<?php
/** POST {email, password} */
require __DIR__ . '/../bootstrap.php';
Http::allow('POST');
Auth::verifyCsrf();

$email = mb_strtolower(Http::str('email', 190));
$pass  = (string)Http::input('password', '');
$ip    = Http::ip();
$max   = (int)config('limits.login_attempts', 5);
$msg   = 'Terlalu banyak percobaan masuk. Coba lagi dalam 15 menit.';

RateLimit::check('login-ip', $ip, $max * 4, 900, $msg);
RateLimit::check('login-email', $email, $max, 900, $msg);

$u = $email !== '' ? Db::one('SELECT id, password_hash, is_active FROM users WHERE email = ?', [$email]) : null;
// Selalu jalankan password_verify agar waktu respons tidak membocorkan email terdaftar
$hash = $u['password_hash'] ?? password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
$valid = password_verify($pass, $hash) && $u && $u['is_active'];

if (!$valid) {
    RateLimit::hit('login-ip', $ip);
    RateLimit::hit('login-email', $email);
    throw new HttpError('Email atau kata sandi salah.', 401);
}
RateLimit::clear('login-email', $email);
if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
    Db::run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $u['id']]);
}
// Akun dengan 2FA: password benar, tapi sesi belum login sampai kode diverifikasi
if ((int)Db::value('SELECT totp_enabled FROM users WHERE id = ?', [$u['id']]) === 1) {
    TwoFactor::startPending((int)$u['id']);
    Http::ok(['twofa_required' => true, 'csrf' => Auth::csrfToken()]);
}
Auth::login((int)$u['id']);
Http::ok(['user' => Auth::publicUser(Auth::user()), 'csrf' => Auth::csrfToken()]);
