<?php
declare(strict_types=1);

use Payment\Factory as PaymentFactory;
use Supplier\Factory as SupplierFactory;

/**
 * Logika inti pesanan:
 *   buat pesanan → buat tagihan pembayaran → (callback PAID) → kirim ke supplier → sukses/gagal
 */
final class OrderService
{
    /* =========================================================
       KATALOG
       ========================================================= */
    public static function catalog(): array
    {
        $cats = Db::all('SELECT id, name, description AS `desc`, icon FROM categories ORDER BY sort_order');
        $brands = Db::all('SELECT * FROM brands WHERE is_active = 1 ORDER BY sort_order, name');
        $items = Db::all("SELECT brand_id, sku, name, price_sell FROM products WHERE is_active = 1 AND supplier_ok = 1 AND (price_sell > 0 OR brand_id IN (SELECT id FROM brands WHERE type = 'postpaid')) ORDER BY sort_order, price_sell");
        $byBrand = [];
        foreach ($items as $it) $byBrand[$it['brand_id']][] = ['sku' => $it['sku'], 'name' => $it['name'], 'price' => (int)$it['price_sell']];

        $products = [];
        foreach ($brands as $b) {
            $list = $byBrand[$b['id']] ?? [];
            if (!$list) continue; // sembunyikan brand tanpa produk aktif
            $p = [
                'id'    => $b['slug'],
                'name'  => $b['name'],
                'cat'   => $b['category_id'],
                'type'  => $b['type'],
                'color' => $b['color'],
                'mark'  => $b['mark'],
                'hot'   => (bool)$b['is_hot'],
                'checkName' => (bool)$b['check_name'],
                'input' => [
                    'label'   => $b['input_label'],
                    'hint'    => $b['input_hint'],
                    'pattern' => $b['input_pattern'],
                    'error'   => $b['input_error'],
                ],
            ];
            if ($b['type'] === 'postpaid') {
                $p['admin'] = (int)$b['admin_fee'];
            } else {
                $p['items'] = $list;
            }
            $products[] = $p;
        }
        array_unshift($cats, ['id' => 'all', 'name' => 'Semua']);
        return ['categories' => $cats, 'products' => $products, 'payments' => PaymentFactory::methods()];
    }

    /* =========================================================
       VALIDASI TUJUAN
       ========================================================= */
    public static function normalizeTarget(array $brand, string $raw): string
    {
        $raw = trim($raw);
        $pattern = '/' . str_replace('/', '\/', $brand['input_pattern']) . '/u';
        if ($raw === '' || mb_strlen($raw) > 64 || @preg_match($pattern, $raw) !== 1) {
            throw new HttpError($brand['input_error'], 422, ['field' => 'customer_no']);
        }
        return $brand['normalize'] === 'digits' ? preg_replace('/\D+/', '', $raw) : $raw;
    }

    private static function brandBySlug(string $slug): array
    {
        $b = Db::one('SELECT * FROM brands WHERE slug = ? AND is_active = 1', [$slug]);
        if (!$b) throw new HttpError('Produk tidak ditemukan.', 404);
        return $b;
    }

    private static function newInvoice(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $inv = 'GDK' . date('ymd') . strtoupper(substr(base_convert(bin2hex(random_bytes(5)), 16, 36), 0, 7));
            if (!Db::value('SELECT 1 FROM orders WHERE invoice = ?', [$inv])) return $inv;
        }
        throw new RuntimeException('Gagal membuat nomor invoice.');
    }

    private static function guardUnpaidLimit(int $userId): void
    {
        $n = (int)Db::value("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'unpaid' AND pay_expired_at > NOW()", [$userId]);
        if ($n >= (int)config('limits.max_unpaid_orders', 5)) {
            throw new HttpError('Anda masih memiliki ' . $n . ' pesanan yang belum dibayar. Selesaikan atau tunggu hingga kedaluwarsa.', 429);
        }
    }

    /* =========================================================
       CEK NAMA PLN / CEK TAGIHAN
       ========================================================= */
    public static function checkName(array $user, string $brandSlug, string $rawTarget): array
    {
        $brand = self::brandBySlug($brandSlug);
        if (!$brand['check_name']) throw new HttpError('Produk ini tidak mendukung cek nama.', 400);
        $target = self::normalizeTarget($brand, $rawTarget);
        RateLimit::check('checkname', (string)$user['id'], 30, 600, 'Terlalu banyak pengecekan. Coba lagi beberapa menit lagi.');
        RateLimit::hit('checkname', (string)$user['id']);
        $r = SupplierFactory::make()->checkPlnName($target);
        if (!$r['ok']) throw new HttpError($r['message'], 422, ['field' => 'customer_no']);
        return ['name' => $r['name'], 'segment' => $r['segment']];
    }

    public static function inquiry(array $user, string $brandSlug, string $rawTarget): array
    {
        $brand = self::brandBySlug($brandSlug);
        if ($brand['type'] !== 'postpaid') throw new HttpError('Produk ini bukan tagihan pascabayar.', 400);
        $target = self::normalizeTarget($brand, $rawTarget);
        $product = Db::one('SELECT * FROM products WHERE brand_id = ? AND is_active = 1 AND supplier_ok = 1 ORDER BY sort_order LIMIT 1', [$brand['id']]);
        if (!$product) throw new HttpError('Layanan sedang tidak tersedia.', 503);

        RateLimit::check('inquiry', (string)$user['id'], 20, 600, 'Terlalu banyak cek tagihan. Coba lagi beberapa menit lagi.');
        RateLimit::hit('inquiry', (string)$user['id']);

        $invoice = self::newInvoice();
        $r = SupplierFactory::make()->inquiry($product['sku'], $target, $invoice);
        if (!$r['ok']) throw new HttpError($r['message'] ?: 'Tagihan tidak ditemukan.', 422, ['field' => 'customer_no']);

        // Harga ke pelanggan = total tagihan (termasuk admin resmi) + admin tambahan kita
        $price = (int)$r['bill'] + (int)$brand['admin_fee'];
        Db::insert(
            "INSERT INTO orders (invoice, user_id, brand_id, product_id, sku, product_name, brand_name, customer_no, customer_name, bill_info, type, price, total, supplier_price, status, supplier, supplier_ref)
             VALUES (?,?,?,?,?,?,?,?,?,?, 'postpaid', ?, ?, ?, 'inquiry', ?, ?)",
            [$invoice, $user['id'], $brand['id'], $product['id'], $product['sku'], 'Tagihan' . ($r['period'] ? ' ' . self::period($r['period']) : ''),
             $brand['name'], $target, $r['customer_name'], json_encode($r['detail'] ?? [], JSON_UNESCAPED_UNICODE), $price, $price, $r['amount'], SupplierFactory::make()->name(), $invoice]
        );
        return [
            'inquiry_id'    => $invoice,
            'customer_name' => $r['customer_name'],
            'period'        => $r['period'] ? self::period($r['period']) : null,
            'bill'          => (int)$r['bill'],
            'admin'         => (int)$brand['admin_fee'],
            'amount'        => $price,
        ];
    }

    private static function period(string $p): string
    {
        if (preg_match('/^(\d{4})(\d{2})$/', $p, $m)) {
            $bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            return $bulan[(int)$m[2]] . ' ' . $m[1];
        }
        return $p;
    }

    /* =========================================================
       BUAT PESANAN
       ========================================================= */
    /** Pemeriksaan sebelum membuat pesanan apa pun. Mengembalikan metode pembayaran. */
    public static function preflight(array $user, string $methodId): array
    {
        if (config('payment.driver') === 'dummy' && !self::canSimulatePayment()) {
            throw new HttpError('Pembayaran belum tersedia. Payment gateway asli belum dihubungkan.', 503);
        }
        $method = PaymentFactory::method($methodId);
        if (!$method) throw new HttpError('Pilih metode pembayaran.', 422, ['field' => 'payment']);
        self::guardUnpaidLimit((int)$user['id']);
        return $method;
    }

    public static function newInvoicePublic(): string { return self::newInvoice(); }

    public static function create(array $user, array $in): array
    {
        $method = self::preflight($user, (string)($in['payment'] ?? ''));

        if (!empty($in['inquiry_id'])) {
            // ---- Pascabayar: lanjutkan dari hasil inquiry ----
            $order = Db::one("SELECT * FROM orders WHERE invoice = ? AND user_id = ? AND status = 'inquiry'", [(string)$in['inquiry_id'], $user['id']]);
            if (!$order) throw new HttpError('Data tagihan tidak ditemukan. Silakan cek tagihan ulang.', 404);
            if (strtotime($order['created_at']) < time() - 3600) throw new HttpError('Data tagihan kedaluwarsa. Silakan cek tagihan ulang.', 410);
        } else {
            // ---- Prabayar ----
            $product = Db::one(
                'SELECT p.*, b.slug, b.name AS brand_name, b.type AS brand_type FROM products p JOIN brands b ON b.id = p.brand_id
                 WHERE p.sku = ? AND p.is_active = 1 AND p.supplier_ok = 1 AND b.is_active = 1',
                [(string)($in['sku'] ?? '')]
            );
            if (!$product || $product['brand_type'] !== 'prepaid' || (int)$product['price_sell'] <= 0) {
                throw new HttpError('Produk tidak tersedia atau sedang gangguan.', 422, ['field' => 'sku']);
            }
            $brand = Db::one('SELECT * FROM brands WHERE id = ?', [$product['brand_id']]);
            $target = self::normalizeTarget($brand, (string)($in['customer_no'] ?? ''));
            $invoice = self::newInvoice();
            Db::insert(
                "INSERT INTO orders (invoice, user_id, brand_id, product_id, sku, product_name, brand_name, customer_no, customer_name, type, price, total, supplier_price, status, supplier, supplier_ref)
                 VALUES (?,?,?,?,?,?,?,?,?, 'prepaid', ?, ?, ?, 'inquiry', ?, ?)",
                [$invoice, $user['id'], $brand['id'], $product['id'], $product['sku'], $product['name'], $product['brand_name'], $target,
                 isset($in['customer_name']) && is_string($in['customer_name']) ? mb_substr($in['customer_name'], 0, 120) : null,
                 (int)$product['price_sell'], (int)$product['price_sell'], (int)$product['price_modal'], SupplierFactory::make()->name(), $invoice]
            );
            $order = Db::one('SELECT * FROM orders WHERE invoice = ?', [$invoice]);
        }

        return self::charge($order, $method, $user);
    }

    /** Lampirkan pembayaran ke pesanan berstatus 'inquiry' (dipakai PPOB & produk digital). */
    public static function charge(array $order, array $method, array $user): array
    {
        // ---- Bayar pakai Saldo GDK: potong saldo & langsung proses ----
        if (($method['kind'] ?? '') === 'balance') {
            $total = (int)$order['price'];
            Db::tx(function () use ($order, $user, $method, $total) {
                Wallet::post((int)$user['id'], -$total, 'payment', 'pay:' . $order['invoice'], (int)$order['id'], 'Pembayaran ' . $order['invoice'] . ' — ' . $order['brand_name']);
                Db::run(
                    "UPDATE orders SET status = 'unpaid', fee = 0, total = ?, pay_provider = 'balance', pay_method = ?, pay_method_name = ?,
                            pay_reference = ?, pay_expired_at = NOW() WHERE id = ? AND status = 'inquiry'",
                    [$total, $method['id'], $method['name'], 'pay:' . $order['invoice'], $order['id']]
                );
            });
            self::markPaidAndProcess(self::row((int)$order['id']));
            return self::find((int)$order['id']);
        }

        // ---- Buat tagihan pembayaran ----
        $fee = PaymentFactory::fee($method, (int)$order['price']);
        $order['fee'] = $fee;
        $order['total'] = (int)$order['price'] + $fee;
        $gateway = PaymentFactory::make();
        $pay = $gateway->create($order, $method, $user);
        Db::log('payment', 'create', (int)$order['id'], ['method' => $method['id'], 'total' => $order['total']], $pay);

        Db::run(
            "UPDATE orders SET status = 'unpaid', fee = ?, total = ?, pay_provider = ?, pay_method = ?, pay_method_name = ?, pay_reference = ?,
                    pay_code = ?, pay_qr = ?, pay_url = ?, pay_expired_at = ? WHERE id = ?",
            [$fee, $order['total'], $gateway->name(), $method['id'], $method['name'], $pay['reference'], $pay['pay_code'], $pay['pay_qr'], $pay['pay_url'], $pay['expired_at'], $order['id']]
        );
        return self::find((int)$order['id']);
    }

    /* =========================================================
       CALLBACK PEMBAYARAN
       ========================================================= */
    public static function handlePaymentCallback(string $raw, array $headers): array
    {
        $gateway = PaymentFactory::make();
        $cb = $gateway->parseCallback($raw, $headers);
        $order = Db::one('SELECT * FROM orders WHERE pay_reference = ? AND pay_provider = ?', [$cb['reference'], $gateway->name()]);
        Db::log('payment', 'callback', $order ? (int)$order['id'] : null, $raw, $cb);
        if (!$order) throw new HttpError('Pesanan tidak ditemukan.', 404);

        if ($cb['status'] === 'PAID') {
            if ($cb['amount'] !== (int)$order['total']) {
                Db::log('payment', 'amount-mismatch', (int)$order['id'], $cb, ['expected' => (int)$order['total']]);
                throw new HttpError('Nominal pembayaran tidak sesuai.', 400);
            }
            self::markPaidAndProcess($order);
        } elseif ($cb['status'] === 'EXPIRED' || $cb['status'] === 'FAILED') {
            Db::run("UPDATE orders SET status = 'expired' WHERE id = ? AND status = 'unpaid'", [$order['id']]);
        }
        return self::find((int)$order['id']);
    }

    /** Tandai lunas (sekali saja — aman dari callback ganda), lalu kirim ke supplier. */
    public static function markPaidAndProcess(array $order): void
    {
        $st = Db::run(
            "UPDATE orders SET status = 'processing', paid_at = NOW(), supplier_status = 'Pending', last_checked_at = NOW()
             WHERE id = ? AND status IN ('unpaid','expired')",
            [$order['id']]
        );
        if ($st->rowCount() !== 1) return; // sudah diproses sebelumnya
        if (($order['kind'] ?? 'ppob') === 'digital') { DigitalService::fulfill((int)$order['id']); return; }

        $ref = $order['supplier_ref'] ?: $order['invoice'];
        $supplier = SupplierFactory::make();
        try {
            $r = $order['type'] === 'postpaid'
                ? $supplier->payBill($order['sku'], $order['customer_no'], $ref)
                : $supplier->topup($order['sku'], $order['customer_no'], $ref);
        } catch (Throwable $e) {
            error_log('[GDK] supplier error ' . $ref . ': ' . $e->getMessage());
            $r = ['status' => 'Pending', 'rc' => null, 'message' => 'Menunggu respons supplier', 'sn' => null, 'price' => null];
        }
        self::applySupplierResult((int)$order['id'], $r);
    }

    /** Simpan hasil supplier. Hanya mengubah pesanan yang masih 'processing'. */
    public static function applySupplierResult(int $orderId, array $r): void
    {
        $status = $r['status'];
        if ($status === 'Sukses') {
            Db::run(
                "UPDATE orders SET status = 'success', supplier_status = 'Sukses', supplier_rc = ?, supplier_message = ?, sn = ?,
                        supplier_price = COALESCE(?, supplier_price), completed_at = NOW(), last_checked_at = NOW()
                 WHERE id = ? AND status = 'processing'",
                [$r['rc'], mb_substr((string)$r['message'], 0, 255), $r['sn'], $r['price'], $orderId]
            );
        } elseif ($status === 'Gagal') {
            $st = Db::run(
                "UPDATE orders SET status = 'failed', supplier_status = 'Gagal', supplier_rc = ?, supplier_message = ?, refund_status = 'pending',
                        completed_at = NOW(), last_checked_at = NOW()
                 WHERE id = ? AND status = 'processing'",
                [$r['rc'], mb_substr((string)$r['message'], 0, 255), $orderId]
            );
            if ($st->rowCount() === 1) self::autoRefund($orderId);
        } else {
            Db::run(
                "UPDATE orders SET supplier_status = 'Pending', supplier_rc = COALESCE(?, supplier_rc), supplier_message = ?, last_checked_at = NOW()
                 WHERE id = ? AND status = 'processing'",
                [$r['rc'], mb_substr((string)$r['message'], 0, 255), $orderId]
            );
        }
    }

    /* =========================================================
       REFUND KE SALDO & KIRIM ULANG
       ========================================================= */

    /** Refund otomatis bila diaktifkan di config (wallet.auto_refund). */
    public static function autoRefund(int $orderId): void
    {
        if (!Wallet::enabled() || !config('wallet.auto_refund', true)) return;
        try {
            self::refundToBalance($orderId, 'system', 'Refund otomatis');
        } catch (Throwable $e) {
            // Jangan gagalkan alur utama; pesanan tetap di antrean refund untuk ditangani admin.
            error_log('[GDK] auto refund ' . $orderId . ': ' . $e->getMessage());
        }
    }

    /** Kembalikan total pembayaran pesanan gagal ke Saldo GDK pelanggan. Aman dipanggil berulang. */
    public static function refundToBalance(int $orderId, string $by, string $note = 'Refund'): bool
    {
        return (bool)Db::tx(function () use ($orderId, $by, $note) {
            $o = Db::one('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$orderId]);
            if (!$o || $o['status'] !== 'failed' || $o['refund_status'] !== 'pending') return false;
            $ref = 'refund:' . ($o['supplier_ref'] ?: $o['invoice']);
            Wallet::post((int)$o['user_id'], (int)$o['total'], 'refund', $ref, $orderId, $note . ' ' . $o['invoice'] . ' — ' . $o['brand_name'], $by);
            Db::run("UPDATE orders SET refund_status = 'done', refund_method = 'saldo', refunded_at = NOW() WHERE id = ?", [$orderId]);
            return true;
        });
    }

    /**
     * Kirim ulang pesanan gagal ke supplier dengan ref_id baru.
     * Bila dana sudah direfund ke saldo, saldo ditarik kembali lebih dulu (ditolak bila sudah terpakai).
     */
    public static function retry(int $orderId, string $by): array
    {
        $max = (int)config('wallet.max_retry', 3);
        $prep = Db::tx(function () use ($orderId, $by, $max) {
            $o = Db::one('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$orderId]);
            if (!$o || $o['status'] !== 'failed') throw new HttpError('Hanya pesanan berstatus Gagal yang bisa dikirim ulang.', 409);
            if (($o['kind'] ?? 'ppob') !== 'ppob') throw new HttpError('Kirim ulang hanya untuk produk PPOB.', 409);
            if ((int)$o['retry_count'] >= $max) throw new HttpError("Batas kirim ulang ({$max}x) tercapai.", 409);
            if ($o['refund_status'] === 'done' && $o['refund_method'] !== 'saldo') {
                throw new HttpError('Dana pesanan ini sudah dikembalikan secara manual, jadi tidak bisa dikirim ulang.', 409);
            }
            $product = Db::one('SELECT p.is_active, p.supplier_ok, b.is_active AS brand_active FROM products p JOIN brands b ON b.id = p.brand_id WHERE p.sku = ?', [$o['sku']]);
            if ($product && !$product['supplier_ok']) throw new HttpError('Produk sedang gangguan di supplier. Coba lagi setelah tersedia.', 409);

            $oldRef = $o['supplier_ref'] ?: $o['invoice'];
            if ($o['refund_status'] === 'done' && $o['refund_method'] === 'saldo') {
                try {
                    Wallet::post((int)$o['user_id'], -(int)$o['total'], 'refund_reversal', 'reversal:' . $oldRef, $orderId,
                        'Pembatalan refund — pesanan ' . $o['invoice'] . ' dikirim ulang', $by);
                } catch (HttpError $e) {
                    if ($e->status === 422) throw new HttpError('Saldo pelanggan sudah terpakai (refund tidak bisa ditarik kembali). Pesanan tidak dikirim ulang.', 409);
                    throw $e;
                }
            }
            $newRef = $o['invoice'] . 'R' . ((int)$o['retry_count'] + 1);
            Db::run(
                "UPDATE orders SET status = 'processing', supplier_ref = ?, retry_count = retry_count + 1, refund_status = 'none', refund_method = NULL,
                        refunded_at = NULL, sn = NULL, supplier_status = 'Pending', supplier_rc = NULL, supplier_message = 'Dikirim ulang oleh admin',
                        completed_at = NULL, last_checked_at = NOW() WHERE id = ?",
                [$newRef, $orderId]
            );
            return ['ref' => $newRef, 'order' => $o];
        });

        $o = $prep['order']; $ref = $prep['ref'];
        $supplier = SupplierFactory::make();
        try {
            if ($o['type'] === 'postpaid') {
                // Pascabayar wajib inquiry ulang dengan ref_id baru sebelum membayar
                $inq = $supplier->inquiry($o['sku'], $o['customer_no'], $ref);
                if (!$inq['ok']) {
                    $r = ['status' => 'Gagal', 'rc' => null, 'message' => 'Inquiry ulang gagal: ' . $inq['message'], 'sn' => null, 'price' => null];
                } elseif ((int)$inq['amount'] > (int)$o['supplier_price'] + 5000) {
                    $r = ['status' => 'Gagal', 'rc' => null, 'message' => 'Jumlah tagihan berubah (Rp ' . number_format((int)$inq['amount'], 0, ',', '.') . '). Pelanggan perlu membuat pesanan baru.', 'sn' => null, 'price' => null];
                } else {
                    $r = $supplier->payBill($o['sku'], $o['customer_no'], $ref);
                }
            } else {
                $r = $supplier->topup($o['sku'], $o['customer_no'], $ref);
            }
        } catch (Throwable $e) {
            error_log('[GDK] retry ' . $ref . ': ' . $e->getMessage());
            $r = ['status' => 'Pending', 'rc' => null, 'message' => 'Menunggu respons supplier', 'sn' => null, 'price' => null];
        }
        self::applySupplierResult($orderId, $r);
        return self::row($orderId);
    }

    /* =========================================================
       PEMBARUAN STATUS (dipanggil saat polling & cron)
       ========================================================= */
    public static function refresh(array $order, int $throttleSeconds = 8): array
    {
        if ($order['status'] === 'unpaid' && $order['pay_expired_at'] && strtotime($order['pay_expired_at']) < time()) {
            Db::run("UPDATE orders SET status = 'expired' WHERE id = ? AND status = 'unpaid'", [$order['id']]);
            return self::row((int)$order['id']);
        }
        if ($order['status'] === 'processing' && ($order['kind'] ?? 'ppob') === 'ppob') {
            $last = $order['last_checked_at'] ? strtotime($order['last_checked_at']) : 0;
            if (time() - $last >= $throttleSeconds) {
                Db::run('UPDATE orders SET last_checked_at = NOW() WHERE id = ?', [$order['id']]);
                try {
                    $r = SupplierFactory::make()->checkStatus($order['type'], $order['sku'], $order['customer_no'], $order['supplier_ref'] ?: $order['invoice']);
                    self::applySupplierResult((int)$order['id'], $r);
                } catch (Throwable $e) {
                    error_log('[GDK] status check ' . $order['invoice'] . ': ' . $e->getMessage());
                }
                return self::row((int)$order['id']);
            }
        }
        return $order;
    }

    /**
     * Simulasi pembayaran hanya boleh jika gateway = dummy DAN supplier tidak memakai saldo asli.
     * (Mencegah produk asli terkirim tanpa pembayaran sungguhan.)
     */
    public static function canSimulatePayment(): bool
    {
        if (config('payment.driver') !== 'dummy') return false;
        $driver = config('supplier.driver');
        if ($driver === 'mock') return true;
        if ($driver === 'digiflazz') return (bool)config('supplier.digiflazz.testing', false);
        return false;
    }

    /* =========================================================
       BACA PESANAN
       ========================================================= */
    public static function row(int $id): array
    {
        return Db::one('SELECT * FROM orders WHERE id = ?', [$id]);
    }

    public static function find(int $id): array
    {
        return self::present(self::row($id));
    }

    public static function present(array $o): array
    {
        $method = $o['pay_method'] ? PaymentFactory::method($o['pay_method']) : null;
        $labels = [
            'inquiry' => 'Draft', 'unpaid' => 'Menunggu Pembayaran', 'processing' => 'Diproses',
            'success' => 'Berhasil', 'failed' => 'Gagal', 'expired' => 'Kedaluwarsa',
        ];
        $ts = fn($v) => $v ? strtotime($v) * 1000 : null;
        return [
            'invoice'       => $o['invoice'],
            'brand'         => $o['brand_name'],
            'item'          => $o['product_name'],
            'type'          => $o['type'],
            'customer_no'   => $o['customer_no'],
            'customer_name' => $o['customer_name'],
            'price'         => (int)$o['price'],
            'fee'           => (int)$o['fee'],
            'total'         => (int)$o['total'],
            'status'        => $o['status'],
            'status_label'  => $labels[$o['status']] ?? $o['status'],
            'sn'            => $o['status'] === 'success' ? $o['sn'] : null,
            'message'       => in_array($o['status'], ['failed', 'processing'], true) ? $o['supplier_message'] : null,
            'refund_status' => $o['refund_status'],
            'refund_method' => $o['refund_method'] ?? null,
            'kind'          => $o['kind'] ?? 'ppob',
            'buyer_note'    => $o['buyer_note'] ?? null,
            'delivery'      => ($o['kind'] ?? 'ppob') === 'digital' && $o['status'] === 'success' ? DigitalService::deliveryFor($o) : null,
            'retry_count'   => (int)($o['retry_count'] ?? 0),
            'payment' => [
                'provider'   => $o['pay_provider'],
                'method'     => $o['pay_method'],
                'name'       => $o['pay_method_name'],
                'kind'       => $method['kind'] ?? null,
                'code'       => $o['status'] === 'unpaid' ? $o['pay_code'] : null,
                'qr'         => $o['status'] === 'unpaid' ? $o['pay_qr'] : null,
                'url'        => $o['status'] === 'unpaid' ? $o['pay_url'] : null,
                'expired_at' => $ts($o['pay_expired_at']),
                'simulate'   => $o['pay_provider'] === 'dummy' && $o['status'] === 'unpaid' && self::canSimulatePayment(),
            ],
            'created_at'   => $ts($o['created_at']),
            'paid_at'      => $ts($o['paid_at']),
            'completed_at' => $ts($o['completed_at']),
        ];
    }
}
