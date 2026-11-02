<?php
/** GET — status login + CSRF token (dipanggil saat halaman dimuat). */
require __DIR__ . '/../bootstrap.php';
Http::allow('GET');
Auth::start();
Http::ok(['user' => Auth::publicUser(Auth::user()), 'csrf' => Auth::csrfToken()]);
