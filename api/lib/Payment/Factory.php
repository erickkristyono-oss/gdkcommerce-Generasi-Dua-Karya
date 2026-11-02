<?php
declare(strict_types=1);

namespace Payment;

final class Factory
{
    private static ?PaymentInterface $instance = null;

    public static function make(): PaymentInterface
    {
        if (self::$instance) return self::$instance;
        $driver = config('payment.driver', 'dummy');
        switch ($driver) {
            case 'dummy':
                $secret = (string)config('payment.callback_secret', '');
                if ($secret === '' || str_starts_with($secret, 'GANTI_')) {
                    if (config('app.env') === 'production') throw new \RuntimeException('payment.callback_secret belum diatur.');
                    $secret = 'dev-secret';
                }
                return self::$instance = new DummyPayment($secret);
            // case 'midtrans': return self::$instance = new MidtransPayment(config('payment.midtrans'));
            // case 'tripay':   return self::$instance = new TripayPayment(config('payment.tripay'));
            default:
                throw new \RuntimeException("Payment driver tidak dikenal: {$driver}");
        }
    }

    /** Metode internal: bayar pakai Saldo GDK (tanpa gateway, tanpa biaya). */
    public static function balanceMethod(): ?array
    {
        return \Wallet::enabled() ? ['id' => 'saldo', 'name' => 'Saldo GDK', 'kind' => 'balance', 'color' => '#0084ff', 'fee' => 0] : null;
    }

    private static function all(): array
    {
        $list = config('payment.methods', []);
        if ($b = self::balanceMethod()) array_unshift($list, $b);
        return $list;
    }

    /** Daftar metode pembayaran publik. */
    public static function methods(): array
    {
        return array_map(fn($m) => [
            'id'    => $m['id'],
            'name'  => $m['name'],
            'kind'  => $m['kind'],
            'color' => $m['color'] ?? '#111827',
            'fee'   => $m['fee'] ?? 0,
        ], self::all());
    }

    public static function method(string $id): ?array
    {
        foreach (self::all() as $m) {
            if ($m['id'] === $id) return $m;
        }
        return null;
    }

    public static function fee(array $method, int $amount): int
    {
        $f = $method['fee'] ?? 0;
        return $f < 1 ? (int)ceil($amount * $f) : (int)$f;
    }
}
