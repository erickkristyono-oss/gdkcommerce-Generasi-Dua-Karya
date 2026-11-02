<?php
declare(strict_types=1);

/** Sesi login berbasis cookie PHP (HttpOnly, SameSite=Lax) + CSRF token. */
final class Auth
{
    private static bool $started = false;
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function start(): void
    {
        if (self::$started) return;
        self::$started = true;
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        session_name(config('session.name', 'gdk_sid'));
        session_set_cookie_params([
            'lifetime' => (int)config('session.lifetime', 604800),
            'path'     => '/',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', (string)config('session.lifetime', 604800));
        session_start();
        if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    public static function csrfToken(): string
    {
        self::start();
        return $_SESSION['csrf'];
    }

    /** Wajib untuk setiap request yang mengubah data (POST). */
    public static function verifyCsrf(): void
    {
        self::start();
        Http::requireSameOrigin();
        $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($sent) || !hash_equals($_SESSION['csrf'], $sent)) {
            throw new HttpError('Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.', 419);
        }
    }

    public static function user(): ?array
    {
        self::start();
        if (self::$loaded) return self::$user;
        self::$loaded = true;
        $id = $_SESSION['uid'] ?? null;
        if (!$id) return null;
        $u = Db::one('SELECT id, name, email, phone, balance, role, agent_status, agent_store, agent_city, agent_admin_note, agent_applied_at, agent_approved_at,
                    is_active, totp_enabled, recovery_codes, created_at FROM users WHERE id = ?', [$id]);
        if (!$u || !$u['is_active']) {
            unset($_SESSION['uid']);
            return null;
        }
        return self::$user = $u;
    }

    public static function require(): array
    {
        $u = self::user();
        if (!$u) throw new HttpError('Silakan masuk terlebih dahulu.', 401);
        return $u;
    }

    /** @param bool $mfa true bila login sudah melewati verifikasi dua langkah */
    public static function login(int $userId, bool $mfa = false): void
    {
        self::start();
        session_regenerate_id(true);
        unset($_SESSION['mfa_pending']);
        $_SESSION['uid'] = $userId;
        $_SESSION['mfa'] = $mfa;
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        self::$loaded = false;
        Db::run('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$userId]);
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
        }
        session_destroy();
        self::$user = null;
        self::$loaded = true;
    }

    /** Paksa baca ulang data pengguna dari database (setelah data akun berubah). */
    public static function reload(): void
    {
        self::$loaded = false;
        self::$user = null;
    }

    /** Sesi ini sudah lolos verifikasi dua langkah? */
    public static function mfaVerified(): bool
    {
        self::start();
        return !empty($_SESSION['mfa']);
    }

    public static function publicUser(?array $u): ?array
    {
        if (!$u) return null;
        return [
            'name'       => $u['name'],
            'email'      => $u['email'],
            'phone'      => $u['phone'],
            'role'       => $u['role'],
            'agent'      => ($u['role'] ?? '') === 'agent' ? 'approved' : ($u['agent_status'] ?? 'none'),
            'store'      => $u['agent_store'] ?? null,
            'balance'    => (int)($u['balance'] ?? 0),
            'twofa'      => (bool)($u['totp_enabled'] ?? false),
            'mfa'        => self::mfaVerified(),
            'recovery_left' => isset($u['recovery_codes']) ? TwoFactor::recoveryLeft($u) : 0,
            'created_at' => strtotime($u['created_at']) * 1000,
        ];
    }
}
