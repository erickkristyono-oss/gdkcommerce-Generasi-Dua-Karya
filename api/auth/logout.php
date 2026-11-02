<?php
/** POST — keluar */
require __DIR__ . '/../bootstrap.php';
Http::allow('POST');
Auth::verifyCsrf();
Auth::logout();
Http::ok();
