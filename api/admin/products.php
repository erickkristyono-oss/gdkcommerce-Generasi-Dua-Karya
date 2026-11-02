<?php
/**
 * GET  ?brand=&q=&state=active|inactive|unavailable|all&page=
 * POST {action: update_product, sku, price_sell?, price_agent? (0 = otomatis), price_locked?, is_active?}
 * POST {action: update_brand, slug, is_active?, is_hot?, admin_fee?, admin_fee_agent? ("" = sama), df_category?, df_brand?}
 * POST {action: bulk_markup, brand (slug|"all"), flat, percent, round, target: sell|agent}
 */
require __DIR__ . '/../bootstrap.php';
$method = Http::allow('GET', 'POST');
Admin::require();

$bool = fn($v) => $v === true || $v === 1 || $v === '1' || $v === 'true';

if ($method === 'POST') {
    $action = Http::str('action', 30);
    $b = Http::body();

    if ($action === 'update_product') {
        $p = Db::one('SELECT p.*, b.type FROM products p JOIN brands b ON b.id = p.brand_id WHERE p.sku = ?', [Http::str('sku', 60)]);
        if (!$p) throw new HttpError('Produk tidak ditemukan.', 404);
        $set = []; $val = [];
        if (array_key_exists('price_sell', $b)) {
            $price = filter_var($b['price_sell'], FILTER_VALIDATE_INT);
            if ($price === false || $price < 0 || $price > 100000000) throw new HttpError('Harga jual tidak valid.', 422, ['field' => 'price_sell']);
            if ($p['type'] === 'prepaid' && (int)$p['price_modal'] > 0 && $price < (int)$p['price_modal']) {
                throw new HttpError('Harga jual (Rp ' . number_format($price, 0, ',', '.') . ') di bawah harga modal (Rp ' . number_format((int)$p['price_modal'], 0, ',', '.') . ').', 422, ['field' => 'price_sell']);
            }
            $set[] = 'price_sell = ?'; $val[] = $price;
        }
        if (array_key_exists('price_agent', $b)) {
            $pa = filter_var($b['price_agent'] === '' || $b['price_agent'] === null ? 0 : $b['price_agent'], FILTER_VALIDATE_INT);
            $sell = array_key_exists('price_sell', $b) ? (int)$b['price_sell'] : (int)$p['price_sell'];
            if ($pa === false || $pa < 0 || $pa > 100000000) throw new HttpError('Harga agen tidak valid.', 422, ['field' => 'price_agent']);
            if ($pa > 0 && $p['type'] === 'prepaid' && (int)$p['price_modal'] > 0 && $pa < (int)$p['price_modal']) {
                throw new HttpError('Harga agen (Rp ' . number_format($pa, 0, ',', '.') . ') di bawah harga modal (Rp ' . number_format((int)$p['price_modal'], 0, ',', '.') . ').', 422, ['field' => 'price_agent']);
            }
            if ($pa > 0 && $pa > $sell) throw new HttpError('Harga agen tidak boleh lebih mahal dari harga jual umum.', 422, ['field' => 'price_agent']);
            $set[] = 'price_agent = ?'; $val[] = $pa;
        }
        if (array_key_exists('price_locked', $b)) { $set[] = 'price_locked = ?'; $val[] = $bool($b['price_locked']) ? 1 : 0; }
        if (array_key_exists('is_active', $b))    { $set[] = 'is_active = ?';    $val[] = $bool($b['is_active']) ? 1 : 0; }
        if (!$set) throw new HttpError('Tidak ada perubahan.', 400);
        $val[] = $p['id'];
        Db::run('UPDATE products SET ' . implode(', ', $set) . ' WHERE id = ?', $val);
        Admin::audit('product.update', null, ['sku' => $p['sku'], 'before' => ['price_sell' => (int)$p['price_sell'], 'price_agent' => (int)$p['price_agent'], 'price_locked' => (int)$p['price_locked'], 'is_active' => (int)$p['is_active']], 'changes' => array_intersect_key($b, array_flip(['price_sell', 'price_agent', 'price_locked', 'is_active']))]);
        Http::ok(['message' => 'Produk disimpan.']);
    }

    if ($action === 'update_brand') {
        $br = Db::one('SELECT * FROM brands WHERE slug = ?', [Http::str('slug', 60)]);
        if (!$br) throw new HttpError('Brand tidak ditemukan.', 404);
        $set = []; $val = [];
        foreach (['is_active', 'is_hot'] as $k) if (array_key_exists($k, $b)) { $set[] = "$k = ?"; $val[] = $bool($b[$k]) ? 1 : 0; }
        if (array_key_exists('admin_fee', $b)) {
            $fee = filter_var($b['admin_fee'], FILTER_VALIDATE_INT);
            if ($fee === false || $fee < 0 || $fee > 100000) throw new HttpError('Biaya admin tidak valid.', 422, ['field' => 'admin_fee']);
            $set[] = 'admin_fee = ?'; $val[] = $fee;
        }
        if (array_key_exists('admin_fee_agent', $b)) {
            $raw = $b['admin_fee_agent'];
            if ($raw === '' || $raw === null) { $set[] = 'admin_fee_agent = NULL'; }
            else {
                $fa = filter_var($raw, FILTER_VALIDATE_INT);
                if ($fa === false || $fa < 0 || $fa > 100000) throw new HttpError('Biaya admin agen tidak valid.', 422, ['field' => 'admin_fee_agent']);
                $set[] = 'admin_fee_agent = ?'; $val[] = $fa;
            }
        }
        foreach (['df_category', 'df_brand'] as $k) if (array_key_exists($k, $b)) { $v = trim((string)$b[$k]); $set[] = "$k = ?"; $val[] = $v === '' ? null : mb_substr($v, 0, 60); }
        if (!$set) throw new HttpError('Tidak ada perubahan.', 400);
        $val[] = $br['id'];
        Db::run('UPDATE brands SET ' . implode(', ', $set) . ' WHERE id = ?', $val);
        Admin::audit('brand.update', null, ['slug' => $br['slug'], 'changes' => array_intersect_key($b, array_flip(['is_active', 'is_hot', 'admin_fee', 'admin_fee_agent', 'df_category', 'df_brand']))]);
        Http::ok(['message' => 'Brand disimpan.']);
    }

    if ($action === 'bulk_markup') {
        $flat = filter_var($b['flat'] ?? 0, FILTER_VALIDATE_INT);
        $pct = filter_var($b['percent'] ?? 0, FILTER_VALIDATE_FLOAT);
        $round = filter_var($b['round'] ?? 100, FILTER_VALIDATE_INT);
        if ($flat === false || $flat < 0 || $flat > 1000000 || $pct === false || $pct < 0 || $pct > 100 || $round === false || $round < 1 || $round > 10000) {
            throw new HttpError('Nilai markup tidak valid.', 422);
        }
        $slug = Http::str('brand', 60);
        $target = Http::str('target', 10) === 'agent' ? 'agent' : 'sell';
        $sql = "SELECT p.id, p.price_modal, p.price_sell FROM products p JOIN brands b ON b.id = p.brand_id WHERE b.type = 'prepaid' AND p.price_locked = 0 AND p.price_modal > 0";
        $par = [];
        if ($slug !== 'all') { $sql .= ' AND b.slug = ?'; $par[] = $slug; }
        $rows = Db::all($sql, $par);
        $m = ['flat' => $flat, 'percent' => $pct, 'round' => $round];
        Db::tx(function () use ($rows, $m, $target) {
            $st = Db::pdo()->prepare($target === 'agent' ? 'UPDATE products SET price_agent = ? WHERE id = ?' : 'UPDATE products SET price_sell = ? WHERE id = ?');
            foreach ($rows as $r) {
                $v = ProductSync::sellPrice((int)$r['price_modal'], $m);
                if ($target === 'agent') $v = min($v, (int)$r['price_sell']); // harga agen tidak melebihi harga umum
                $st->execute([$v, $r['id']]);
            }
        });
        Admin::audit('product.bulk_markup', null, ['brand' => $slug, 'target' => $target, 'markup' => $m, 'affected' => count($rows)]);
        Http::ok(['message' => count($rows) . ' produk diperbarui' . ($target === 'agent' ? ' (harga agen)' : '') . ' — produk dengan harga terkunci dilewati.']);
    }
    throw new HttpError('Aksi tidak dikenal.', 400);
}

/* ---------------- GET ---------------- */
$brands = Db::all(
    "SELECT b.slug, b.name, b.category_id, b.type, b.is_active, b.is_hot, b.admin_fee, b.admin_fee_agent, b.df_category, b.df_brand,
            COUNT(p.id) AS total, SUM(p.is_active = 1 AND p.supplier_ok = 1) AS live
     FROM brands b LEFT JOIN products p ON p.brand_id = b.id
     GROUP BY b.id ORDER BY b.sort_order, b.name");

[$page, $per, $offset] = Admin::page();
$where = ['1=1']; $p = [];
if (($slug = (string)($_GET['brand'] ?? '')) !== '') { $where[] = 'b.slug = ?'; $p[] = $slug; }
$state = (string)($_GET['state'] ?? 'all');
if ($state === 'active') $where[] = 'p.is_active = 1 AND p.supplier_ok = 1';
elseif ($state === 'inactive') $where[] = 'p.is_active = 0';
elseif ($state === 'unavailable') $where[] = 'p.supplier_ok = 0';
$q = trim(substr((string)($_GET['q'] ?? ''), 0, 100));
if ($q !== '') { $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR b.name LIKE ?)'; $like = '%' . addcslashes($q, '%_\\') . '%'; array_push($p, $like, $like, $like); }
$w = implode(' AND ', $where);
$total = (int)Db::value("SELECT COUNT(*) FROM products p JOIN brands b ON b.id = p.brand_id WHERE {$w}", $p);
$rows = Db::all(
    "SELECT p.sku, p.name, p.price_modal, p.price_sell, p.price_agent, p.price_locked, p.is_active, p.supplier_ok, p.synced_at, b.slug AS brand, b.name AS brand_name, b.type
     FROM products p JOIN brands b ON b.id = p.brand_id WHERE {$w}
     ORDER BY b.sort_order, p.sort_order, p.price_sell LIMIT {$per} OFFSET {$offset}", $p);

Http::ok([
    'brands' => array_map(fn($r) => [
        'slug' => $r['slug'], 'name' => $r['name'], 'category' => $r['category_id'], 'type' => $r['type'],
        'is_active' => (bool)$r['is_active'], 'is_hot' => (bool)$r['is_hot'], 'admin_fee' => (int)$r['admin_fee'],
        'admin_fee_agent' => $r['admin_fee_agent'] === null ? null : (int)$r['admin_fee_agent'],
        'df_category' => $r['df_category'], 'df_brand' => $r['df_brand'], 'total' => (int)$r['total'], 'live' => (int)$r['live'],
    ], $brands),
    'products' => array_map(fn($r) => [
        'sku' => $r['sku'], 'name' => $r['name'], 'brand' => $r['brand'], 'brand_name' => $r['brand_name'], 'type' => $r['type'],
        'price_modal' => (int)$r['price_modal'], 'price_sell' => (int)$r['price_sell'], 'price_locked' => (bool)$r['price_locked'],
        'price_agent' => (int)$r['price_agent'], 'agent_price' => Agent::price($r),
        'is_active' => (bool)$r['is_active'], 'supplier_ok' => (bool)$r['supplier_ok'],
        'synced_at' => $r['synced_at'] ? strtotime($r['synced_at']) * 1000 : null,
    ], $rows),
    'total' => $total, 'page' => $page, 'per' => $per,
    'markup' => config('supplier.markup'),
    'agent_rule' => Agent::rule(),
]);
