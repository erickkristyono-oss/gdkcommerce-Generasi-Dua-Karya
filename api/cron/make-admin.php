<?php
/**
 * Jadikan pengguna sebagai admin (hanya via CLI / SSH):
 *   php api/cron/make-admin.php email@domain.com
 * Alternatif tanpa SSH — jalankan di phpMyAdmin:
 *   UPDATE users SET role = 'admin' WHERE email = 'email@domain.com';
 */
require __DIR__ . '/_guard.php';
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }

$email = strtolower(trim($argv[1] ?? ''));
if ($email === '') { out('Pemakaian: php api/cron/make-admin.php email@domain.com'); exit(1); }
$u = Db::one('SELECT id, name, role FROM users WHERE email = ?', [$email]);
if (!$u) { out("Pengguna {$email} tidak ditemukan. Daftar dulu lewat website."); exit(1); }
Db::run("UPDATE users SET role = 'admin' WHERE id = ?", [$u['id']]);
Db::log('admin', 'make-admin', null, ['by' => 'cli', 'email' => $email], null);
out("{$u['name']} <{$email}> sekarang admin.");
