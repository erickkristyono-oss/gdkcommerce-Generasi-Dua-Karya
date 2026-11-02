<?php
declare(strict_types=1);

/**
 * Program Agen.
 *  - Pengguna mengajukan diri → admin menyetujui → role = 'agent'.
 *  - Agen melihat & membayar harga agen (lebih murah dari harga umum).
 *  - Agen mengisi Saldo GDK lewat deposit (melalui payment gateway) lalu bertransaksi pakai saldo.
 */
final class Agent
{
    public static function enabled(): bool
    {
        return (bool)config('agent.enabled', true);
    }

    public static function is(?array $user): bool
    {
        return self::enabled() && $user && ($user['role'] ?? '') === 'agent';
    }

    /**
     * Aturan harga agen otomatis (dipakai bila products.price_agent = 0):
     * harga agen = modal + {share}% dari laba harga umum, dibulatkan ke atas kelipatan {round}.
     * Contoh share 50: modal 10.000, harga umum 11.000 → harga agen 10.500.
     */
    public static function rule(): array
    {
        $share = (float)config('agent.margin_share', 50);
        return ['share' => max(0, min(100, $share)), 'round' => max(1, (int)config('agent.round', 50))];
    }

    /**
     * Harga agen efektif untuk satu produk prabayar.
     * - price_agent manual dipakai bila ≥ modal; selain itu dihitung otomatis (lihat rule()).
     * - Tidak pernah di bawah modal dan tidak pernah lebih mahal dari harga jual umum.
     */
    public static function price(array $p): int
    {
        $sell  = (int)$p['price_sell'];
        $modal = (int)$p['price_modal'];
        $set   = (int)($p['price_agent'] ?? 0);
        if ($set > 0 && $set >= $modal) {
            $price = $set;
        } elseif ($modal > 0 && $sell > $modal) {
            $r = self::rule();
            $price = (int)(ceil(($modal + ($sell - $modal) * $r['share'] / 100) / $r['round']) * $r['round']);
        } else {
            $price = $sell;
        }
        return $sell > 0 ? min($price, $sell) : $price;
    }

    /** Harga yang dibayar pengguna ini untuk produk prabayar. */
    public static function priceFor(?array $user, array $p): int
    {
        return self::is($user) ? self::price($p) : (int)$p['price_sell'];
    }

    /** Biaya admin pascabayar untuk pengguna ini. */
    public static function adminFeeFor(?array $user, array $brand): int
    {
        if (self::is($user) && $brand['admin_fee_agent'] !== null) return (int)$brand['admin_fee_agent'];
        return (int)$brand['admin_fee'];
    }

    public static function depositLimits(): array
    {
        return [
            'min' => max(1000, (int)config('agent.deposit_min', 10000)),
            'max' => max(1000, (int)config('agent.deposit_max', 10000000)),
        ];
    }

    /* =========================================================
       PENGAJUAN
       ========================================================= */
    public static function apply(array $user, array $in): void
    {
        if (!self::enabled()) throw new HttpError('Program agen sedang ditutup.', 403);
        if ($user['role'] === 'admin') throw new HttpError('Akun admin tidak perlu mendaftar sebagai agen.', 409);
        if ($user['role'] === 'agent') throw new HttpError('Akun Anda sudah terdaftar sebagai agen.', 409);
        if (($user['agent_status'] ?? 'none') === 'pending') throw new HttpError('Pengajuan Anda sedang ditinjau admin.', 409);

        $store = trim(mb_substr((string)($in['store'] ?? ''), 0, 100));
        $city  = trim(mb_substr((string)($in['city'] ?? ''), 0, 100));
        $note  = trim(mb_substr((string)($in['note'] ?? ''), 0, 500));
        if (mb_strlen($store) < 3) throw new HttpError('Isi nama toko / usaha Anda (minimal 3 karakter).', 422, ['field' => 'store']);
        if (mb_strlen($city) < 3) throw new HttpError('Isi kota / kabupaten domisili usaha.', 422, ['field' => 'city']);
        if (!Http::input('agree')) throw new HttpError('Anda harus menyetujui ketentuan agen.', 422, ['field' => 'agree']);

        RateLimit::check('agent_apply', (string)$user['id'], 5, 86400, 'Terlalu banyak pengajuan. Coba lagi besok.');
        RateLimit::hit('agent_apply', (string)$user['id']);

        Db::run(
            "UPDATE users SET agent_status = 'pending', agent_store = ?, agent_city = ?, agent_note = ?, agent_admin_note = NULL, agent_applied_at = NOW()
             WHERE id = ? AND role = 'customer'",
            [$store, $city, $note ?: null, $user['id']]
        );
    }

    /* =========================================================
       DEPOSIT SALDO (memakai payment gateway — dummy untuk sekarang)
       ========================================================= */
    public static function createDeposit(array $user, array $in): array
    {
        if (!self::is($user)) throw new HttpError('Deposit saldo khusus untuk agen.', 403);
        if (!Wallet::enabled()) throw new HttpError('Saldo GDK sedang dinonaktifkan.', 503);

        $amount = filter_var($in['amount'] ?? null, FILTER_VALIDATE_INT);
        $lim = self::depositLimits();
        if ($amount === false || $amount < $lim['min'] || $amount > $lim['max']) {
            throw new HttpError('Nominal deposit antara Rp ' . number_format($lim['min'], 0, ',', '.') . ' dan Rp ' . number_format($lim['max'], 0, ',', '.') . '.', 422, ['field' => 'amount']);
        }
        if ($amount % 1000 !== 0) throw new HttpError('Nominal deposit harus kelipatan Rp 1.000.', 422, ['field' => 'amount']);

        $method = OrderService::preflight($user, (string)($in['payment'] ?? ''));
        if (($method['kind'] ?? '') === 'balance') throw new HttpError('Deposit tidak bisa dibayar dengan Saldo GDK. Pilih metode pembayaran lain.', 422, ['field' => 'payment']);

        $invoice = OrderService::newInvoicePublic();
        $id = Db::insert(
            "INSERT INTO orders (invoice, kind, user_id, is_agent, sku, product_name, brand_name, customer_no, type, price, total, status, supplier, supplier_ref)
             VALUES (?, 'deposit', ?, 1, 'DEPOSIT', ?, 'Deposit Saldo', ?, 'prepaid', ?, ?, 'inquiry', 'deposit', ?)",
            [$invoice, $user['id'], 'Deposit Rp ' . number_format($amount, 0, ',', '.'), mb_substr($user['email'], 0, 64), $amount, $amount, $invoice]
        );
        return OrderService::charge(OrderService::row($id), $method, $user);
    }

    /** Dipanggil setelah deposit lunas (status sudah 'processing'): tambahkan saldo, tandai berhasil. */
    public static function completeDeposit(int $orderId): void
    {
        Db::tx(function () use ($orderId) {
            $o = Db::one('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$orderId]);
            if (!$o || $o['kind'] !== 'deposit' || $o['status'] !== 'processing') return;
            Wallet::post((int)$o['user_id'], (int)$o['price'], 'deposit', 'deposit:' . $o['invoice'], $orderId, 'Deposit saldo ' . $o['invoice'] . ' via ' . ($o['pay_method_name'] ?: '-'));
            Db::run("UPDATE orders SET status = 'success', supplier_status = 'Sukses', supplier_message = 'Saldo ditambahkan', completed_at = NOW(), last_checked_at = NOW()
                     WHERE id = ? AND status = 'processing'", [$orderId]);
        });
    }

    /* =========================================================
       DATA UNTUK HALAMAN AKUN
       ========================================================= */
    public static function summary(array $user): array
    {
        $out = [
            'enabled' => self::enabled(),
            'status'  => $user['role'] === 'agent' ? 'approved' : ($user['agent_status'] ?? 'none'),
            'store'   => $user['agent_store'] ?? null,
            'city'    => $user['agent_city'] ?? null,
            'admin_note'  => in_array($user['agent_status'] ?? '', ['rejected'], true) ? ($user['agent_admin_note'] ?? null) : null,
            'applied_at'  => !empty($user['agent_applied_at']) ? strtotime($user['agent_applied_at']) * 1000 : null,
            'approved_at' => !empty($user['agent_approved_at']) ? strtotime($user['agent_approved_at']) * 1000 : null,
        ];
        if (!self::is($user)) return $out;

        $range = function (string $from) use ($user): array {
            $r = Db::one("SELECT COUNT(*) AS n, COALESCE(SUM(total),0) AS t FROM orders
                          WHERE user_id = ? AND kind <> 'deposit' AND status = 'success' AND completed_at >= ?", [$user['id'], $from]);
            return ['count' => (int)$r['n'], 'total' => (int)$r['t']];
        };
        $out['stats'] = [
            'today' => $range(date('Y-m-d 00:00:00')),
            'month' => $range(date('Y-m-01 00:00:00')),
            'deposit_month' => (int)Db::value("SELECT COALESCE(SUM(price),0) FROM orders WHERE user_id = ? AND kind = 'deposit' AND status = 'success' AND completed_at >= ?", [$user['id'], date('Y-m-01 00:00:00')]),
        ];
        $out['deposit'] = self::depositLimits() + ['presets' => [50000, 100000, 250000, 500000, 1000000, 2000000]];
        return $out;
    }
}
