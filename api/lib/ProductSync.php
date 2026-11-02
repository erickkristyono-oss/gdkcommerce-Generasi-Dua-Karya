<?php
declare(strict_types=1);

/**
 * Sinkronisasi produk & harga dari supplier (Digiflazz).
 * Dipakai oleh cron/sync-products.php dan panel admin.
 */
final class ProductSync
{
    /** Harga jual = modal + markup, dibulatkan ke atas. */
    public static function sellPrice(int $modal, ?array $markup = null): int
    {
        $m = $markup ?? config('supplier.markup', ['flat' => 0, 'percent' => 0, 'round' => 100]);
        $p = $modal + (int)($m['flat'] ?? 0) + $modal * (float)($m['percent'] ?? 0) / 100;
        $r = max(1, (int)($m['round'] ?? 1));
        return (int)(ceil($p / $r) * $r);
    }

    /**
     * @return array ['skipped'=>bool,'message'=>string,'balance'=>?int,'prepaid'=>int,'postpaid'=>int,'deactivated'=>int,'unmapped'=>array]
     */
    public static function run(): array
    {
        $supplier = Supplier\Factory::make();
        if ($supplier->name() === 'mock') {
            return ['skipped' => true, 'message' => 'Supplier = mock. Harga memakai data di database; tidak ada yang disinkronkan.',
                    'balance' => null, 'prepaid' => 0, 'postpaid' => 0, 'deactivated' => 0, 'unmapped' => []];
        }
        @set_time_limit(180);
        $balance = $supplier->balance();
        $runStart = Db::value('SELECT NOW()');

        $brands = Db::all('SELECT id, slug, type, df_category, df_brand FROM brands WHERE df_brand IS NOT NULL');
        $norm = fn($s) => mb_strtolower(trim((string)$s));
        $findBrand = function (array $item, string $type) use ($brands, $norm) {
            foreach ($brands as $b) {
                if ($b['type'] !== $type) continue;
                if ($norm($b['df_brand']) !== $norm($item['brand'] ?? '')) continue;
                if ($b['df_category'] !== null && $b['df_category'] !== '' && $norm($b['df_category']) !== $norm($item['category'] ?? '')) continue;
                return $b;
            }
            return null;
        };

        // Ambil kedua price list dulu; bila salah satu gagal, jangan ubah apa pun.
        $lists = ['prepaid' => $supplier->priceList('prepaid'), 'postpaid' => $supplier->priceList('postpaid')];

        $upsert = Db::pdo()->prepare(
            'INSERT INTO products (brand_id, sku, name, price_modal, price_sell, is_active, supplier_ok, sort_order, synced_at)
             VALUES (:brand, :sku, :name, :modal, :sell, 1, :ok, :sort, NOW())
             ON DUPLICATE KEY UPDATE brand_id = VALUES(brand_id), name = VALUES(name), price_modal = VALUES(price_modal),
               price_sell = IF(price_locked = 1, price_sell, VALUES(price_sell)), supplier_ok = VALUES(supplier_ok),
               sort_order = VALUES(sort_order), synced_at = NOW()'
        );

        $unmapped = [];
        $count = ['prepaid' => 0, 'postpaid' => 0];
        Db::tx(function () use ($lists, $findBrand, $upsert, &$unmapped, &$count, $runStart, &$off) {
            foreach ($lists as $type => $list) {
                usort($list, fn($a, $b) => ((int)($a['price'] ?? 0)) <=> ((int)($b['price'] ?? 0)));
                foreach ($list as $i => $item) {
                    $b = $findBrand($item, $type);
                    if (!$b) {
                        $key = ($item['category'] ?? '-') . ' / ' . ($item['brand'] ?? '-');
                        $unmapped[$type][$key] = ($unmapped[$type][$key] ?? 0) + 1;
                        continue;
                    }
                    $modal = (int)($item['price'] ?? 0);
                    $upsert->execute([
                        ':brand' => $b['id'],
                        ':sku'   => (string)$item['buyer_sku_code'],
                        ':name'  => mb_substr((string)$item['product_name'], 0, 150),
                        ':modal' => $modal,
                        ':sell'  => $type === 'postpaid' ? 0 : self::sellPrice($modal),
                        ':ok'    => (!empty($item['buyer_product_status']) && !empty($item['seller_product_status'])) ? 1 : 0,
                        ':sort'  => $i,
                    ]);
                    $count[$type]++;
                }
            }
            // Produk yang hilang dari price list → tandai tidak tersedia di supplier.
            // Kolom is_active murni dikendalikan admin dan tidak pernah diubah oleh sinkronisasi.
            $off = Db::run('UPDATE products SET supplier_ok = 0 WHERE supplier_ok = 1 AND (synced_at IS NULL OR synced_at < ?)', [$runStart])->rowCount();
        });

        foreach ($unmapped as &$rows) ksort($rows);
        $report = ['skipped' => false, 'message' => 'Sinkronisasi selesai.', 'balance' => $balance,
                   'prepaid' => $count['prepaid'], 'postpaid' => $count['postpaid'], 'deactivated' => (int)$off, 'unmapped' => $unmapped];
        Db::log('sync', 'products', null, null, $report);
        return $report;
    }
}
