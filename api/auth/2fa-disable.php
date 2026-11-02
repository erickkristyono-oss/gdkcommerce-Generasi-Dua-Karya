<?php
/** POST {password, code} — nonaktifkan 2FA. Admin yang menonaktifkan 2FA tidak bisa membuka panel admin. */
require __DIR__ . '/../bootstrap.php';
Http::allow('POST');
Auth::verifyCsrf();
$user = Auth::require();
TwoFactor::disable($user, (string)Http::input('password', ''), Http::str('code', 20));
Auth::reload();
Http::ok(['user' => Auth::publicUser(Auth::user()), 'csrf' => Auth::csrfToken()]);
