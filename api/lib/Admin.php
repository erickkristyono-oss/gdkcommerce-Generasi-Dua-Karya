<?php
declare(strict_types=1);

/** Otorisasi & audit untuk panel admin. */
final class Admin
{
    private static ?array $admin = null;

    /** Wajib login sebagai admin. Untuk POST, CSRF juga diverifikasi. */
    public static function require(): array
    {
        $u = Auth::require();
        if (($u['role'] ?? '') !== 'admin') throw new HttpError('Akses ditolak. Halaman ini khusus admin.', 403);
        if (config('security.admin_2fa', true)) {
            if (!$u['totp_enabled']) throw new HttpError('Aktifkan verifikasi dua langkah (2FA) untuk membuka panel admin.', 403, ['code' => 'mfa_setup_required']);
            if (!Auth::mfaVerified()) throw new HttpError('Masukkan kode verifikasi dua langkah untuk melanjutkan.', 403, ['code' => 'mfa_required']);
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') Auth::verifyCsrf();
        return self::$admin = $u;
    }

    /** Catat aksi admin ke api_logs (channel 'admin'). */
    public static function audit(string $action, ?int $orderId, array $data = []): void
    {
        $a = self::$admin;
        Db::log('admin', $action, $orderId, ['by' => $a ? $a['email'] : null, 'ip' => Http::ip()] + $data, null, null);
    }

    public static function page(): array
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per = min(100, max(5, (int)($_GET['per'] ?? 25)));
        return [$page, $per, ($page - 1) * $per];
    }

    public static function date(?string $v): ?string
    {
        return $v && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }
}
