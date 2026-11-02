<?php
declare(strict_types=1);

/** Alur verifikasi dua langkah: setup, verifikasi login, kode cadangan, nonaktifkan. */
final class TwoFactor
{
    private const PENDING_TTL = 300; // detik untuk memasukkan kode setelah password benar

    /** Mulai setup: buat secret sementara di sesi. */
    public static function begin(array $user): array
    {
        Auth::start();
        $secret = Totp::generateSecret();
        $_SESSION['totp_setup'] = ['secret' => $secret, 'at' => time()];
        return [
            'secret' => trim(chunk_split($secret, 4, ' ')),
            'uri'    => Totp::uri($secret, $user['email'], (string)config('app.name', 'GDK Commerce')),
        ];
    }

    /** Konfirmasi setup dengan password + kode pertama. Mengembalikan kode cadangan (sekali tampil). */
    public static function confirm(array $user, string $code, string $password): array
    {
        Auth::start();
        $pending = $_SESSION['totp_setup'] ?? null;
        if (!$pending || time() - $pending['at'] > 900) throw new HttpError('Sesi setup kedaluwarsa. Mulai ulang.', 410);
        self::checkPassword((int)$user['id'], $password);
        $step = Totp::verify($pending['secret'], $code);
        if ($step === null) throw new HttpError('Kode salah. Pastikan jam di HP sudah otomatis lalu coba kode terbaru.', 422, ['field' => 'code']);

        $codes = self::newRecoveryCodes();
        Db::run('UPDATE users SET totp_secret = ?, totp_enabled = 1, totp_last_step = ?, recovery_codes = ? WHERE id = ?',
            [Crypto::encrypt($pending['secret']), $step, json_encode(array_map([self::class, 'hashCode'], $codes)), $user['id']]);
        unset($_SESSION['totp_setup']);
        $_SESSION['mfa'] = true;
        Db::log('security', '2fa.enable', null, ['user' => $user['email'], 'ip' => Http::ip()], null);
        return $codes;
    }

    public static function disable(array $user, string $password, string $code): void
    {
        self::checkPassword((int)$user['id'], $password);
        $row = Db::one('SELECT * FROM users WHERE id = ?', [$user['id']]);
        if (!$row['totp_enabled']) throw new HttpError('2FA belum aktif.', 409);
        if (!self::verifyCode($row, $code)) throw new HttpError('Kode 2FA salah.', 422, ['field' => 'code']);
        Db::run('UPDATE users SET totp_secret = NULL, totp_enabled = 0, totp_last_step = NULL, recovery_codes = NULL WHERE id = ?', [$user['id']]);
        $_SESSION['mfa'] = false;
        Db::log('security', '2fa.disable', null, ['user' => $user['email'], 'ip' => Http::ip()], null);
    }

    /** Reset oleh admin lain / CLI (HP hilang). */
    public static function reset(int $userId, string $by): void
    {
        Db::run('UPDATE users SET totp_secret = NULL, totp_enabled = 0, totp_last_step = NULL, recovery_codes = NULL WHERE id = ?', [$userId]);
        Db::log('security', '2fa.reset', null, ['user_id' => $userId, 'by' => $by], null);
    }

    /** Simpan status "menunggu kode 2FA" setelah password benar. */
    public static function startPending(int $userId): void
    {
        Auth::start();
        session_regenerate_id(true);
        unset($_SESSION['uid']);
        $_SESSION['mfa_pending'] = ['uid' => $userId, 'at' => time()];
    }

    /**
     * Verifikasi kode untuk (a) login yang menunggu 2FA, atau (b) pengguna yang sudah login tapi sesinya belum terverifikasi.
     */
    public static function verifyLogin(string $code): array
    {
        Auth::start();
        $pending = $_SESSION['mfa_pending'] ?? null;
        if ($pending && time() - $pending['at'] <= self::PENDING_TTL) {
            $uid = (int)$pending['uid'];
        } elseif (!empty($_SESSION['uid'])) {
            $uid = (int)$_SESSION['uid'];
        } else {
            unset($_SESSION['mfa_pending']);
            throw new HttpError('Sesi verifikasi kedaluwarsa. Silakan masuk ulang.', 401, ['code' => 'mfa_expired']);
        }
        RateLimit::check('2fa', (string)$uid, 5, 900, 'Terlalu banyak kode salah. Coba lagi dalam 15 menit.');
        $row = Db::one('SELECT * FROM users WHERE id = ? AND is_active = 1', [$uid]);
        if (!$row || !$row['totp_enabled']) throw new HttpError('2FA tidak aktif untuk akun ini.', 409);
        if (!self::verifyCode($row, $code)) {
            RateLimit::hit('2fa', (string)$uid);
            throw new HttpError('Kode verifikasi salah.', 422, ['field' => 'code']);
        }
        RateLimit::clear('2fa', (string)$uid);
        unset($_SESSION['mfa_pending']);
        if (empty($_SESSION['uid'])) Auth::login($uid, true);
        else { session_regenerate_id(true); $_SESSION['mfa'] = true; }
        return $row;
    }

    /** Cek kode TOTP atau kode cadangan (kode cadangan sekali pakai). */
    private static function verifyCode(array $row, string $code): bool
    {
        $code = trim($code);
        if (preg_match('/^\d{6}$/', preg_replace('/\s/', '', $code))) {
            $step = Totp::verify(Crypto::decrypt($row['totp_secret']), $code, $row['totp_last_step'] !== null ? (int)$row['totp_last_step'] : null);
            if ($step === null) return false;
            Db::run('UPDATE users SET totp_last_step = ? WHERE id = ?', [$step, $row['id']]);
            return true;
        }
        $hashes = json_decode((string)$row['recovery_codes'], true) ?: [];
        $h = self::hashCode($code);
        $idx = array_search($h, $hashes, true);
        if ($idx === false) return false;
        array_splice($hashes, (int)$idx, 1);
        Db::run('UPDATE users SET recovery_codes = ? WHERE id = ?', [json_encode($hashes), $row['id']]);
        Db::log('security', '2fa.recovery_used', null, ['user' => $row['email'], 'left' => count($hashes)], null);
        return true;
    }

    public static function recoveryLeft(array $row): int
    {
        return count(json_decode((string)($row['recovery_codes'] ?? ''), true) ?: []);
    }

    private static function newRecoveryCodes(): array
    {
        $out = [];
        for ($i = 0; $i < 8; $i++) {
            $r = strtoupper(bin2hex(random_bytes(4)));
            $out[] = substr($r, 0, 4) . '-' . substr($r, 4, 4);
        }
        return $out;
    }

    private static function hashCode(string $code): string
    {
        return hash('sha256', strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code)));
    }

    private static function checkPassword(int $userId, string $password): void
    {
        RateLimit::check('reauth', (string)$userId, 5, 900, 'Terlalu banyak percobaan. Coba lagi dalam 15 menit.');
        $hash = (string)Db::value('SELECT password_hash FROM users WHERE id = ?', [$userId]);
        if (!password_verify($password, $hash)) {
            RateLimit::hit('reauth', (string)$userId);
            throw new HttpError('Kata sandi salah.', 422, ['field' => 'password']);
        }
    }
}
