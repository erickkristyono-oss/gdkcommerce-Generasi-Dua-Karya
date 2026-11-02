<?php
declare(strict_types=1);

/**
 * Toko Produk Digital — game, plugin, software, website service, e-book.
 *
 * Cara kirim (per produk):
 *  - download : file privat (storage/digital) atau link eksternal → langsung tersedia setelah bayar
 *  - license  : satu kode diambil otomatis dari stok digital_licenses
 *  - manual   : pesanan menunggu admin mengisi isi pengiriman (link, akun, catatan)
 */
final class DigitalService
{
    public const MAX_DOWNLOADS = 20;

    public static function storageDir(): string
    {
        $dir = (string)config('digital.storage', GDK_ROOT . '/storage/digital');
        if (!is_dir($dir)) @mkdir($dir, 0750, true);
        return rtrim($dir, '/');
    }

    /* ---------------- Katalog publik ---------------- */

    public static function categories(): array
    {
        return Db::all(
            "SELECT c.id, c.name, c.icon, COUNT(p.id) AS total FROM digital_categories c
             LEFT JOIN digital_products p ON p.category_id = c.id AND p.is_active = 1
             GROUP BY c.id ORDER BY c.sort_order");
    }

    /** Stok: null = tak terbatas; angka = sisa kode lisensi. */
    private static function stock(array $p): ?int
    {
        if ($p['delivery_type'] !== 'license') return null;
        return (int)Db::value("SELECT COUNT(*) FROM digital_licenses WHERE product_id = ? AND status = 'available'", [$p['id']]);
    }

    public static function card(array $p): array
    {
        return [
            'slug' => $p['slug'], 'name' => $p['name'], 'category' => $p['category_id'], 'category_name' => $p['category_name'] ?? null,
            'store' => $p['store_name'], 'short' => $p['short_desc'], 'price' => (int)$p['price'],
            'price_strike' => $p['price_strike'] !== null ? (int)$p['price_strike'] : null,
            'image' => $p['image'], 'featured' => (bool)$p['is_featured'], 'delivery' => $p['delivery_type'],
            'stock' => self::stock($p), 'sold' => (int)($p['sold'] ?? 0),
        ];
    }

    public static function list(array $f): array
    {
        $where = ['p.is_active = 1']; $par = [];
        if (!empty($f['category']) && $f['category'] !== 'all') { $where[] = 'p.category_id = ?'; $par[] = (string)$f['category']; }
        if (!empty($f['featured'])) $where[] = 'p.is_featured = 1';
        if (($q = trim((string)($f['q'] ?? ''))) !== '') {
            $where[] = '(p.name LIKE ? OR p.short_desc LIKE ? OR p.store_name LIKE ?)';
            $like = '%' . addcslashes(mb_substr($q, 0, 80), '%_\\') . '%'; array_push($par, $like, $like, $like);
        }
        $order = ['price_asc' => 'p.price ASC', 'price_desc' => 'p.price DESC', 'newest' => 'p.id DESC', 'popular' => 'sold DESC, p.sort_order']
            [$f['sort'] ?? ''] ?? 'p.is_featured DESC, p.sort_order, p.id';
        $limit = min(100, max(1, (int)($f['limit'] ?? 60)));
        $rows = Db::all(
            "SELECT p.*, c.name AS category_name,
                    (SELECT COUNT(*) FROM orders o WHERE o.digital_product_id = p.id AND o.status = 'success') AS sold
             FROM digital_products p JOIN digital_categories c ON c.id = p.category_id
             WHERE " . implode(' AND ', $where) . " ORDER BY {$order} LIMIT {$limit}", $par);
        return array_map([self::class, 'card'], $rows);
    }

    public static function detail(string $slug): array
    {
        $p = Db::one(
            "SELECT p.*, c.name AS category_name,
                    (SELECT COUNT(*) FROM orders o WHERE o.digital_product_id = p.id AND o.status = 'success') AS sold
             FROM digital_products p JOIN digital_categories c ON c.id = p.category_id WHERE p.slug = ? AND p.is_active = 1", [$slug]);
        if (!$p) throw new HttpError('Produk tidak ditemukan.', 404);
        $related = Db::all(
            "SELECT p.*, c.name AS category_name, 0 AS sold FROM digital_products p JOIN digital_categories c ON c.id = p.category_id
             WHERE p.is_active = 1 AND p.category_id = ? AND p.id <> ? ORDER BY p.is_featured DESC, p.sort_order LIMIT 4", [$p['category_id'], $p['id']]);
        return self::card($p) + [
            'description' => $p['description'], 'version' => $p['version'], 'platform' => $p['platform'], 'license_type' => $p['license_type'],
            'buyer_note_label' => $p['buyer_note_label'], 'buyer_note_required' => (bool)$p['buyer_note_required'],
            'related' => array_map([self::class, 'card'], $related),
        ];
    }

    /* ---------------- Pesanan ---------------- */

    public static function createOrder(array $user, array $in): array
    {
        $method = OrderService::preflight($user, (string)($in['payment'] ?? ''));
        $p = Db::one("SELECT p.*, c.name AS category_name FROM digital_products p JOIN digital_categories c ON c.id = p.category_id WHERE p.slug = ? AND p.is_active = 1", [(string)($in['digital'] ?? '')]);
        if (!$p) throw new HttpError('Produk tidak tersedia.', 404);
        if ($p['delivery_type'] === 'license' && self::stock($p) < 1) throw new HttpError('Stok produk ini sedang habis.', 409);

        $note = isset($in['buyer_note']) && is_scalar($in['buyer_note']) ? trim(mb_substr((string)$in['buyer_note'], 0, 500)) : '';
        if ($p['buyer_note_label'] && $p['buyer_note_required'] && mb_strlen($note) < 3) {
            throw new HttpError('Isi ' . $p['buyer_note_label'] . '.', 422, ['field' => 'buyer_note']);
        }
        $invoice = OrderService::newInvoicePublic();
        $id = Db::insert(
            "INSERT INTO orders (invoice, kind, user_id, brand_id, product_id, digital_product_id, sku, product_name, brand_name, customer_no, buyer_note,
                                 type, price, total, supplier_price, status, supplier, supplier_ref)
             VALUES (?, 'digital', ?, NULL, NULL, ?, ?, ?, ?, ?, ?, 'prepaid', ?, ?, 0, 'inquiry', 'digital', ?)",
            [$invoice, $user['id'], $p['id'], 'DG-' . $p['id'], $p['name'], $p['category_name'] . ($p['store_name'] ? ' · ' . $p['store_name'] : ''),
             mb_substr($user['email'], 0, 64), $note ?: null, (int)$p['price'], (int)$p['price'], $invoice]
        );
        return OrderService::charge(OrderService::row($id), $method, $user);
    }

    /** Dipanggil setelah pesanan lunas (status sudah 'processing'). */
    public static function fulfill(int $orderId): void
    {
        $o = OrderService::row($orderId);
        $p = Db::one('SELECT * FROM digital_products WHERE id = ?', [$o['digital_product_id']]);
        if (!$p) { self::waitAdmin($orderId, 'Produk sudah dihapus — kirim manual.'); return; }

        if ($p['delivery_type'] === 'license') {
            $ok = Db::tx(function () use ($orderId, $p) {
                $lic = Db::one("SELECT id, license_key FROM digital_licenses WHERE product_id = ? AND status = 'available' ORDER BY id LIMIT 1 FOR UPDATE", [$p['id']]);
                if (!$lic) return false;
                Db::run("UPDATE digital_licenses SET status = 'sold', order_id = ?, sold_at = NOW() WHERE id = ?", [$orderId, $lic['id']]);
                self::complete($orderId, ['keys' => [Crypto::decrypt($lic['license_key'])], 'note' => $p['delivery_note']]);
                return true;
            });
            if (!$ok) self::waitAdmin($orderId, 'Stok kode lisensi habis — kirim manual atau tambah stok.');
            return;
        }
        if ($p['delivery_type'] === 'download' && ($p['file_path'] || $p['file_url'])) {
            self::complete($orderId, ['download' => true, 'note' => $p['delivery_note']]);
            return;
        }
        self::waitAdmin($orderId, $p['delivery_type'] === 'download' ? 'File belum diunggah — kirim manual.' : null);
    }

    private static function complete(int $orderId, array $delivery): void
    {
        Db::run(
            "UPDATE orders SET status = 'success', supplier_status = 'Sukses', supplier_message = 'Produk terkirim', delivery = ?, completed_at = NOW()
             WHERE id = ? AND status = 'processing'",
            [Crypto::encrypt(json_encode($delivery, JSON_UNESCAPED_UNICODE)), $orderId]);
    }

    private static function waitAdmin(int $orderId, ?string $why): void
    {
        Db::run("UPDATE orders SET supplier_status = 'Pending', supplier_message = ? WHERE id = ? AND status = 'processing'",
            ['Menunggu dikirim admin' . ($why ? ' — ' . $why : ''), $orderId]);
    }

    /** Admin mengirim produk untuk pesanan manual. */
    public static function deliverManual(int $orderId, string $content): void
    {
        $o = OrderService::row($orderId);
        if ($o['kind'] !== 'digital' || $o['status'] !== 'processing') throw new HttpError('Pesanan ini tidak menunggu pengiriman.', 409);
        self::complete($orderId, ['text' => $content, 'note' => null, 'manual' => true]);
    }

    /** Isi pengiriman untuk ditampilkan ke pemilik pesanan / admin. */
    public static function deliveryFor(array $o): ?array
    {
        if (!$o['delivery']) return null;
        try { $d = json_decode(Crypto::decrypt($o['delivery']), true) ?: []; } catch (Throwable $e) { return ['error' => 'Isi pengiriman tidak dapat dibaca.']; }
        $p = Db::one('SELECT file_path, file_name, file_url FROM digital_products WHERE id = ?', [$o['digital_product_id']]);
        $out = ['keys' => $d['keys'] ?? [], 'text' => $d['text'] ?? null, 'note' => $d['note'] ?? null];
        if (!empty($d['download']) && $p) {
            $out['download'] = [
                'url' => $p['file_path'] ? 'api/download.php?invoice=' . rawurlencode($o['invoice']) : null,
                'external' => $p['file_path'] ? null : $p['file_url'],
                'name' => $p['file_name'], 'left' => max(0, self::MAX_DOWNLOADS - (int)$o['download_count']),
            ];
        }
        return $out;
    }

    /* ---------------- Unduhan ---------------- */

    public static function streamDownload(array $user, string $invoice): void
    {
        $o = Db::one("SELECT * FROM orders WHERE invoice = ? AND user_id = ? AND kind = 'digital'", [$invoice, $user['id']]);
        if (!$o || $o['status'] !== 'success') throw new HttpError('File tidak tersedia untuk pesanan ini.', 404);
        $p = Db::one('SELECT * FROM digital_products WHERE id = ?', [$o['digital_product_id']]);
        if (!$p || !$p['file_path']) throw new HttpError('File tidak tersedia.', 404);
        if ((int)$o['download_count'] >= self::MAX_DOWNLOADS) throw new HttpError('Batas unduhan tercapai. Hubungi admin.', 429);
        $path = self::storageDir() . '/' . basename($p['file_path']);
        if (!is_file($path)) throw new HttpError('File tidak ditemukan di server. Hubungi admin.', 404);

        Db::run('UPDATE orders SET download_count = download_count + 1 WHERE id = ?', [$o['id']]);
        $name = preg_replace('/[^A-Za-z0-9._ -]/', '_', $p['file_name'] ?: basename($path));
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($path);
        exit;
    }
}
