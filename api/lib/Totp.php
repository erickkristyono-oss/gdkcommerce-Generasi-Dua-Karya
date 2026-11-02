<?php
declare(strict_types=1);

/** TOTP (RFC 6238) — kompatibel Google Authenticator, Authy, Microsoft Authenticator. */
final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    public const PERIOD = 30;

    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    public static function base32Encode(string $data): string
    {
        $bits = '';
        foreach (str_split($data) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        $out = '';
        foreach (str_split($bits, 5) as $chunk) $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        return $out;
    }

    public static function base32Decode(string $b32): string
    {
        $b32 = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $b32));
        $bits = '';
        foreach (str_split($b32) as $c) $bits .= str_pad(decbin(strpos(self::ALPHABET, $c)), 5, '0', STR_PAD_LEFT);
        $out = '';
        foreach (str_split($bits, 8) as $byte) if (strlen($byte) === 8) $out .= chr(bindec($byte));
        return $out;
    }

    public static function code(string $secret, int $step): string
    {
        $bin = pack('N*', 0) . pack('N*', $step);
        $h = hash_hmac('sha1', $bin, self::base32Decode($secret), true);
        $o = ord($h[19]) & 0xf;
        $num = ((ord($h[$o]) & 0x7f) << 24) | ((ord($h[$o + 1]) & 0xff) << 16) | ((ord($h[$o + 2]) & 0xff) << 8) | (ord($h[$o + 3]) & 0xff);
        return str_pad((string)($num % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Verifikasi kode (toleransi ±1 periode untuk selisih jam).
     * @return int|null time-step yang cocok, atau null. Step <= $lastStep ditolak (anti replay).
     */
    public static function verify(string $secret, string $code, ?int $lastStep = null, int $window = 1): ?int
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== 6) return null;
        $now = intdiv(time(), self::PERIOD);
        for ($i = -$window; $i <= $window; $i++) {
            $step = $now + $i;
            if ($lastStep !== null && $step <= $lastStep) continue;
            if (hash_equals(self::code($secret, $step), $code)) return $step;
        }
        return null;
    }

    public static function uri(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account)
            . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=' . self::PERIOD;
    }
}
