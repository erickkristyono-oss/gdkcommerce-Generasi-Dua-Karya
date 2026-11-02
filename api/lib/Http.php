<?php
declare(strict_types=1);

final class Http
{
    private static ?array $body = null;

    /** Kirim respons JSON lalu hentikan eksekusi. */
    public static function json(array $data, int $status = 200): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            header('X-Content-Type-Options: nosniff');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function ok(array $data = []): void
    {
        self::json(['ok' => true] + $data);
    }

    /** Batasi method HTTP yang diizinkan. */
    public static function allow(string ...$methods): string
    {
        $m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($m, $methods, true)) {
            header('Allow: ' . implode(', ', $methods));
            throw new HttpError('Method tidak diizinkan.', 405);
        }
        return $m;
    }

    /** Body JSON (atau form) sebagai array. */
    public static function body(): array
    {
        if (self::$body !== null) return self::$body;
        $raw = file_get_contents('php://input') ?: '';
        $data = [];
        if ($raw !== '') {
            $data = json_decode($raw, true);
            if (!is_array($data)) $data = $_POST ?: [];
        }
        return self::$body = $data;
    }

    public static function rawBody(): string
    {
        return file_get_contents('php://input') ?: '';
    }

    public static function input(string $key, $default = null)
    {
        $b = self::body();
        return array_key_exists($key, $b) ? $b[$key] : $default;
    }

    public static function str(string $key, int $max = 255): string
    {
        $v = self::input($key, '');
        if (!is_scalar($v)) return '';
        return mb_substr(trim((string)$v), 0, $max);
    }

    public static function ip(): string
    {
        // Hostinger berada di belakang proxy; gunakan header jika tersedia.
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $h) {
            if (!empty($_SERVER[$h]) && filter_var($_SERVER[$h], FILTER_VALIDATE_IP)) return $_SERVER[$h];
        }
        return '0.0.0.0';
    }

    /** Pastikan request berasal dari situs sendiri (lapisan tambahan selain CSRF token). */
    public static function requireSameOrigin(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin === '') return; // beberapa browser tidak mengirim Origin pada same-origin
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $oHost = parse_url($origin, PHP_URL_HOST) . (parse_url($origin, PHP_URL_PORT) ? ':' . parse_url($origin, PHP_URL_PORT) : '');
        if (strcasecmp($oHost, $host) !== 0) throw new HttpError('Origin tidak valid.', 403);
    }
}
