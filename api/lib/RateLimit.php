<?php
declare(strict_types=1);

final class RateLimit
{
    /** Lempar error 429 jika jumlah hit dalam jendela waktu sudah melewati batas. */
    public static function check(string $bucket, string $ident, int $max, int $windowSeconds, string $message): void
    {
        $count = (int)Db::value(
            'SELECT COUNT(*) FROM rate_limits WHERE bucket = ? AND ident = ? AND created_at > (NOW() - INTERVAL ? SECOND)',
            [$bucket, $ident, $windowSeconds]
        );
        if ($count >= $max) throw new HttpError($message, 429);
    }

    public static function hit(string $bucket, string $ident): void
    {
        Db::run('INSERT INTO rate_limits (bucket, ident) VALUES (?, ?)', [$bucket, $ident]);
        // Bersihkan data lama sesekali
        if (random_int(1, 50) === 1) Db::run('DELETE FROM rate_limits WHERE created_at < (NOW() - INTERVAL 1 DAY)');
    }

    public static function clear(string $bucket, string $ident): void
    {
        Db::run('DELETE FROM rate_limits WHERE bucket = ? AND ident = ?', [$bucket, $ident]);
    }
}
