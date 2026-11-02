<?php
/** POST {code} — kode authenticator 6 digit atau kode cadangan (XXXX-XXXX). */
require __DIR__ . '/../bootstrap.php';
Http::allow('POST');
Auth::verifyCsrf();
TwoFactor::verifyLogin(Http::str('code', 20));
Auth::reload();
Http::ok(['user' => Auth::publicUser(Auth::user()), 'csrf' => Auth::csrfToken()]);
