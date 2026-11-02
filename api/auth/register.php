<?php
/** POST {name, email, phone, password, password2, agree} */
require __DIR__ . '/../bootstrap.php';
Http::allow('POST');
Auth::verifyCsrf();

$ip = Http::ip();
RateLimit::check('register', $ip, (int)config('limits.register_per_hour', 5), 3600, 'Terlalu banyak pendaftaran dari jaringan Anda. Coba lagi nanti.');

$name  = Http::str('name', 100);
$email = mb_strtolower(Http::str('email', 190));
$phone = preg_replace('/\D+/', '', Http::str('phone', 20));
$pass  = (string)Http::input('password', '');
$pass2 = (string)Http::input('password2', '');

$err = fn(string $m, string $f) => throw new HttpError($m, 422, ['field' => $f]);
if (mb_strlen($name) < 3) $err('Nama minimal 3 karakter.', 'name');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err('Email tidak valid.', 'email');
if (!preg_match('/^08[0-9]{8,12}$/', $phone)) $err('Format nomor HP: 08xxxxxxxxxx.', 'phone');
if (strlen($pass) < 8 || strlen($pass) > 72 || !preg_match('/[A-Za-z]/', $pass) || !preg_match('/[0-9]/', $pass)) $err('Kata sandi minimal 8 karakter, kombinasi huruf & angka.', 'password');
if (!hash_equals($pass, $pass2)) $err('Konfirmasi kata sandi tidak sama.', 'password2');
if (!Http::input('agree')) $err('Anda harus menyetujui Syarat & Ketentuan.', 'agree');

RateLimit::hit('register', $ip);
if (Db::value('SELECT 1 FROM users WHERE email = ?', [$email])) $err('Email sudah terdaftar. Silakan masuk.', 'email');

try {
    $id = Db::insert('INSERT INTO users (name, email, phone, password_hash) VALUES (?,?,?,?)', [$name, $email, $phone, password_hash($pass, PASSWORD_DEFAULT)]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') $err('Email sudah terdaftar. Silakan masuk.', 'email');
    throw $e;
}
Auth::login($id);
Http::ok(['user' => Auth::publicUser(Auth::user()), 'csrf' => Auth::csrfToken()]);
