<?php
declare(strict_types=1);

/** Enkripsi simetris AES-256-GCM dengan kunci dari config app.key. */
final class Crypto
{
    private static function key(): string
    {
        $k = (string)config('app.key', '');
        if ($k === '') {
            if (config('app.env') === 'production') {
                throw new HttpError('app.key belum diisi di konfigurasi. Isi dulu sebelum mengaktifkan 2FA (lihat config.sample.php).', 500);
            }
            $k = 'dev-insecure-key-' . (string)config('db.name', '');
        }
        return hash('sha256', $k, true);
    }

    public static function encrypt(string $plain): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $ct = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($ct === false) throw new RuntimeException('Enkripsi gagal.');
        return 'v1:' . base64_encode($iv . $tag . $ct);
    }

    public static function decrypt(string $enc): string
    {
        if (!str_starts_with($enc, 'v1:')) throw new RuntimeException('Format data terenkripsi tidak dikenal.');
        $raw = base64_decode(substr($enc, 3), true);
        if ($raw === false || strlen($raw) < 29) throw new RuntimeException('Data terenkripsi rusak.');
        $pt = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        if ($pt === false) throw new RuntimeException('Gagal membaca data terenkripsi (app.key berubah?).');
        return $pt;
    }
}
