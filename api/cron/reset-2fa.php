<?php
/**
 * Reset 2FA pengguna (mis. HP admin hilang & kode cadangan habis) — hanya via CLI/SSH:
 *   php api/cron/reset-2fa.php email@domain.com
 * Alternatif phpMyAdmin:
 *   UPDATE users SET totp_secret=NULL, totp_enabled=0, totp_last_step=NULL, recovery_codes=NULL WHERE email='email@domain.com';
 */
require __DIR__ . '/_guard.php';
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
$email = strtolower(trim($argv[1] ?? ''));
$u = $email ? Db::one('SELECT id, name FROM users WHERE email = ?', [$email]) : null;
if (!$u) { out('Pemakaian: php api/cron/reset-2fa.php email@domain.com (pengguna harus ada)'); exit(1); }
TwoFactor::reset((int)$u['id'], 'cli');
out("2FA untuk {$u['name']} <{$email}> direset. Pengguna perlu mengaktifkannya ulang saat masuk.");
