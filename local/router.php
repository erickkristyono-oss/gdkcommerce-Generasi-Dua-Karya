<?php
/**
 * Router untuk server lokal `php -S` — meniru aturan .htaccess di Hostinger
 * (php -S tidak membaca .htaccess). Hanya dipakai oleh local/start.sh.
 */
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$blocked = '#^/(storage|database|local|api/lib|api/cron/_guard\.php|api/config\.php|api/bootstrap\.php)(/|$)|/\.|\.(sql|md|sh|log)$|^/assets/uploads/.*\.(php|phtml|phar|html?|svg)$#i';
if (preg_match($blocked, $path)) {
    http_response_code(403);
    header('Content-Type: text/plain');
    exit('Forbidden');
}
return false; // biarkan php -S melayani file seperti biasa
