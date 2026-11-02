<?php
/**
 * Panel Admin — Produk Digital
 * GET                       — daftar semua produk (?q=&category=)
 * GET ?id=N                 — detail lengkap untuk form edit
 * GET ?licenses=N           — daftar kode lisensi produk (disamarkan)
 * POST multipart action=save         — tambah/ubah produk (+ upload image / file)
 * POST JSON {action: add_licenses, id, keys}       — tambah stok kode (satu per baris)
 * POST JSON {action: delete_license, id, license_id}
 * POST JSON {action: toggle, id, field: is_active|is_featured}
 * POST JSON {action: delete, id}    — hapus (hanya bila belum pernah dipesan; selain itu nonaktifkan)
 */
require __DIR__ . '/../bootstrap.php';
$method = Http::allow('GET', 'POST');
$admin = Admin::require();

const IMG_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
const FILE_EXT  = ['zip', 'rar', '7z', 'pdf', 'epub', 'apk', 'exe', 'msi', 'dmg', 'iso', 'txt', 'tar', 'gz'];

function dp_row(int $id): array {
    $p = Db::one('SELECT * FROM digital_products WHERE id = ?', [$id]);
    if (!$p) throw new HttpError('Produk tidak ditemukan.', 404);
    return $p;
}
function dp_slug(string $text): string {
    $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text), '-'));
    return substr($s ?: 'produk', 0, 100);
}
function upload_error(int $code): string {
    return [UPLOAD_ERR_INI_SIZE => 'File melebihi batas upload server (upload_max_filesize). Gunakan link eksternal untuk file besar.',
            UPLOAD_ERR_FORM_SIZE => 'File terlalu besar.', UPLOAD_ERR_PARTIAL => 'Upload terputus, coba lagi.'][$code] ?? 'Upload gagal (kode ' . $code . ').';
}

if ($method === 'POST') {
    $isMultipart = str_starts_with((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'multipart/form-data');
    if ($isMultipart && empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        throw new HttpError('Ukuran upload melebihi batas server (post_max_size). Perkecil file atau gunakan link eksternal.', 413);
    }
    $action = $isMultipart ? (string)($_POST['action'] ?? '') : Http::str('action', 30);

    /* ---------- Simpan produk ---------- */
    if ($action === 'save') {
        $in = fn(string $k, int $max = 255) => mb_substr(trim((string)($_POST[$k] ?? '')), 0, $max);
        $id = (int)($_POST['id'] ?? 0);
        $old = $id ? dp_row($id) : null;

        $name = $in('name', 150);
        if (mb_strlen($name) < 3) throw new HttpError('Nama produk minimal 3 karakter.', 422, ['field' => 'name']);
        $cat = $in('category_id', 30);
        if (!Db::value('SELECT 1 FROM digital_categories WHERE id = ?', [$cat])) throw new HttpError('Pilih kategori.', 422, ['field' => 'category_id']);
        $price = filter_var($_POST['price'] ?? '', FILTER_VALIDATE_INT);
        if ($price === false || $price < 1000 || $price > 1000000000) throw new HttpError('Harga minimal Rp 1.000.', 422, ['field' => 'price']);
        $strike = trim((string)($_POST['price_strike'] ?? '')) === '' ? null : filter_var($_POST['price_strike'], FILTER_VALIDATE_INT);
        if ($strike === false || ($strike !== null && $strike <= $price)) throw new HttpError('Harga coret harus lebih besar dari harga jual (atau kosongkan).', 422, ['field' => 'price_strike']);
        $delivery = $in('delivery_type', 10);
        if (!in_array($delivery, ['download', 'license', 'manual'], true)) throw new HttpError('Pilih cara kirim.', 422, ['field' => 'delivery_type']);
        $fileUrl = $in('file_url', 500);
        if ($fileUrl !== '' && !preg_match('#^https://#i', $fileUrl)) throw new HttpError('Link file harus diawali https://', 422, ['field' => 'file_url']);

        // Slug: pakai input; bila kosong saat edit, pertahankan slug lama agar link produk tidak rusak
        $slug = $in('slug', 120) !== '' ? dp_slug($in('slug', 120)) : ($old['slug'] ?? dp_slug($name));
        $base = $slug; $n = 2;
        while (Db::value('SELECT 1 FROM digital_products WHERE slug = ? AND id <> ?', [$slug, $id])) $slug = $base . '-' . $n++;

        $image = $old['image'] ?? null; $filePath = $old['file_path'] ?? null; $fileName = $old['file_name'] ?? null;
        $toDelete = [];

        // Gambar
        if (!empty($_POST['remove_image']) && $image) { $toDelete[] = GDK_ROOT . '/' . $image; $image = null; }
        if (!empty($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $f = $_FILES['image'];
            if ($f['error'] !== UPLOAD_ERR_OK) throw new HttpError(upload_error($f['error']), 422, ['field' => 'image']);
            if ($f['size'] > 3 * 1024 * 1024) throw new HttpError('Gambar maksimal 3 MB.', 422, ['field' => 'image']);
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
            if (!isset(IMG_TYPES[$mime]) || !@getimagesize($f['tmp_name'])) throw new HttpError('Gambar harus JPG, PNG, atau WEBP.', 422, ['field' => 'image']);
            $dir = GDK_ROOT . '/assets/uploads/digital';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $rel = 'assets/uploads/digital/' . $slug . '-' . bin2hex(random_bytes(4)) . '.' . IMG_TYPES[$mime];
            if (!move_uploaded_file($f['tmp_name'], GDK_ROOT . '/' . $rel)) throw new HttpError('Gagal menyimpan gambar.', 500);
            if ($image) $toDelete[] = GDK_ROOT . '/' . $image;
            $image = $rel;
        }
        // File produk (privat)
        if (!empty($_POST['remove_file']) && $filePath) { $toDelete[] = DigitalService::storageDir() . '/' . $filePath; $filePath = null; $fileName = null; }
        if (!empty($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $f = $_FILES['file'];
            if ($f['error'] !== UPLOAD_ERR_OK) throw new HttpError(upload_error($f['error']), 422, ['field' => 'file']);
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, FILE_EXT, true)) throw new HttpError('Jenis file tidak diizinkan. Gunakan: ' . implode(', ', FILE_EXT) . '.', 422, ['field' => 'file']);
            $stored = bin2hex(random_bytes(12)) . '.' . $ext;
            if (!move_uploaded_file($f['tmp_name'], DigitalService::storageDir() . '/' . $stored)) throw new HttpError('Gagal menyimpan file.', 500);
            if ($filePath) $toDelete[] = DigitalService::storageDir() . '/' . $filePath;
            $filePath = $stored;
            $fileName = mb_substr(preg_replace('/[^\w.\- ]/u', '_', basename($f['name'])), 0, 150);
        }
        if ($delivery === 'download' && !$filePath && $fileUrl === '') {
            throw new HttpError('Cara kirim "Unduh file" butuh file yang diunggah atau link https.', 422, ['field' => 'file']);
        }

        $data = [
            'slug' => $slug, 'name' => $name, 'category_id' => $cat, 'store_name' => $in('store_name', 100) ?: null,
            'short_desc' => $in('short_desc', 300) ?: null, 'description' => mb_substr(trim((string)($_POST['description'] ?? '')), 0, 20000) ?: null,
            'price' => $price, 'price_strike' => $strike, 'image' => $image,
            'version' => $in('version', 40) ?: null, 'platform' => $in('platform', 100) ?: null, 'license_type' => $in('license_type', 100) ?: null,
            'delivery_type' => $delivery, 'file_path' => $filePath, 'file_name' => $fileName, 'file_url' => $fileUrl ?: null,
            'delivery_note' => mb_substr(trim((string)($_POST['delivery_note'] ?? '')), 0, 5000) ?: null,
            'buyer_note_label' => $in('buyer_note_label', 100) ?: null, 'buyer_note_required' => !empty($_POST['buyer_note_required']) ? 1 : 0,
            'is_active' => !empty($_POST['is_active']) ? 1 : 0, 'is_featured' => !empty($_POST['is_featured']) ? 1 : 0,
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
        ];
        $cols = array_keys($data);
        if ($old) {
            Db::run('UPDATE digital_products SET ' . implode(', ', array_map(fn($c) => "$c = ?", $cols)) . ' WHERE id = ?', [...array_values($data), $id]);
        } else {
            $id = Db::insert('INSERT INTO digital_products (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')', array_values($data));
        }
        foreach ($toDelete as $f) if (is_file($f)) @unlink($f);
        Admin::audit($old ? 'digital.update' : 'digital.create', null, ['id' => $id, 'slug' => $slug, 'price' => $price, 'delivery' => $delivery]);
        Http::ok(['message' => $old ? 'Produk disimpan.' : 'Produk ditambahkan.', 'id' => $id, 'slug' => $slug]);
    }

    $id = (int)Http::input('id', 0);
    $p = dp_row($id);

    if ($action === 'add_licenses') {
        $lines = preg_split('/\r\n|\r|\n/', (string)Http::input('keys', ''));
        $keys = array_values(array_unique(array_filter(array_map(fn($l) => mb_substr(trim($l), 0, 500), $lines), fn($l) => $l !== '')));
        if (!$keys) throw new HttpError('Masukkan minimal satu kode (satu per baris).', 422, ['field' => 'keys']);
        if (count($keys) > 1000) throw new HttpError('Maksimal 1.000 kode sekali tambah.', 422, ['field' => 'keys']);
        $added = 0;
        $st = Db::pdo()->prepare('INSERT IGNORE INTO digital_licenses (product_id, license_key, key_hash) VALUES (?, ?, ?)');
        Db::tx(function () use ($keys, $st, $p, &$added) {
            foreach ($keys as $k) { $st->execute([$p['id'], Crypto::encrypt($k), hash('sha256', $k)]); $added += $st->rowCount(); }
        });
        Admin::audit('digital.add_licenses', null, ['id' => $p['id'], 'added' => $added, 'submitted' => count($keys)]);
        $dup = count($keys) - $added;
        Http::ok(['message' => "{$added} kode ditambahkan" . ($dup ? ", {$dup} dilewati karena sudah ada." : '.')]);
    }
    if ($action === 'delete_license') {
        $n = Db::run("DELETE FROM digital_licenses WHERE id = ? AND product_id = ? AND status = 'available'", [(int)Http::input('license_id', 0), $p['id']])->rowCount();
        if (!$n) throw new HttpError('Kode tidak ditemukan atau sudah terjual.', 409);
        Admin::audit('digital.delete_license', null, ['id' => $p['id']]);
        Http::ok(['message' => 'Kode dihapus.']);
    }
    if ($action === 'toggle') {
        $field = Http::str('field', 20);
        if (!in_array($field, ['is_active', 'is_featured'], true)) throw new HttpError('Field tidak valid.', 400);
        Db::run("UPDATE digital_products SET {$field} = 1 - {$field} WHERE id = ?", [$p['id']]);
        Admin::audit('digital.toggle', null, ['id' => $p['id'], 'field' => $field]);
        Http::ok(['message' => 'Disimpan.']);
    }
    if ($action === 'delete') {
        if (Db::value('SELECT 1 FROM orders WHERE digital_product_id = ? LIMIT 1', [$p['id']])) {
            Db::run('UPDATE digital_products SET is_active = 0 WHERE id = ?', [$p['id']]);
            Admin::audit('digital.deactivate', null, ['id' => $p['id']]);
            Http::ok(['message' => 'Produk sudah pernah dipesan, jadi dinonaktifkan (tidak dihapus) agar riwayat tetap utuh.']);
        }
        Db::run('DELETE FROM digital_products WHERE id = ?', [$p['id']]);
        if ($p['image'] && is_file(GDK_ROOT . '/' . $p['image'])) @unlink(GDK_ROOT . '/' . $p['image']);
        if ($p['file_path'] && is_file(DigitalService::storageDir() . '/' . $p['file_path'])) @unlink(DigitalService::storageDir() . '/' . $p['file_path']);
        Admin::audit('digital.delete', null, ['id' => $p['id'], 'slug' => $p['slug']]);
        Http::ok(['message' => 'Produk dihapus.']);
    }
    throw new HttpError('Aksi tidak dikenal.', 400);
}

/* ---------------- GET ---------------- */
if (!empty($_GET['licenses'])) {
    $p = dp_row((int)$_GET['licenses']);
    $rows = Db::all("SELECT l.id, l.license_key, l.status, l.created_at, l.sold_at, o.invoice FROM digital_licenses l LEFT JOIN orders o ON o.id = l.order_id
                     WHERE l.product_id = ? ORDER BY l.status = 'sold', l.id DESC LIMIT 500", [$p['id']]);
    $mask = function (string $enc): string {
        try { $k = Crypto::decrypt($enc); } catch (Throwable $e) { return '(tidak terbaca)'; }
        return mb_strlen($k) <= 8 ? str_repeat('•', mb_strlen($k)) : mb_substr($k, 0, 4) . str_repeat('•', min(12, mb_strlen($k) - 8)) . mb_substr($k, -4);
    };
    Http::ok(['product' => ['id' => (int)$p['id'], 'name' => $p['name'], 'delivery_type' => $p['delivery_type']],
        'licenses' => array_map(fn($r) => ['id' => (int)$r['id'], 'key' => $mask($r['license_key']), 'status' => $r['status'], 'invoice' => $r['invoice'],
            'at' => strtotime($r['created_at']) * 1000, 'sold_at' => $r['sold_at'] ? strtotime($r['sold_at']) * 1000 : null], $rows)]);
}
if (!empty($_GET['id'])) {
    $p = dp_row((int)$_GET['id']);
    unset($p['file_path']);
    $p['has_file'] = (bool)Db::value('SELECT file_path IS NOT NULL FROM digital_products WHERE id = ?', [$_GET['id']]);
    Http::ok(['product' => $p]);
}
$where = ['1=1']; $par = [];
if (($c = (string)($_GET['category'] ?? '')) !== '') { $where[] = 'p.category_id = ?'; $par[] = $c; }
if (($q = trim(substr((string)($_GET['q'] ?? ''), 0, 80))) !== '') { $where[] = '(p.name LIKE ? OR p.store_name LIKE ?)'; $like = '%' . addcslashes($q, '%_\\') . '%'; array_push($par, $like, $like); }
$rows = Db::all(
    "SELECT p.id, p.slug, p.name, p.category_id, c.name AS category_name, p.store_name, p.price, p.price_strike, p.image, p.delivery_type,
            p.file_path IS NOT NULL AS has_file, p.file_url IS NOT NULL AS has_url, p.is_active, p.is_featured,
            (SELECT COUNT(*) FROM digital_licenses l WHERE l.product_id = p.id AND l.status = 'available') AS stock,
            (SELECT COUNT(*) FROM orders o WHERE o.digital_product_id = p.id AND o.status = 'success') AS sold,
            (SELECT COUNT(*) FROM orders o WHERE o.digital_product_id = p.id AND o.status = 'processing') AS waiting
     FROM digital_products p JOIN digital_categories c ON c.id = p.category_id
     WHERE " . implode(' AND ', $where) . " ORDER BY p.sort_order, p.id", $par);
Http::ok([
    'categories' => Db::all('SELECT id, name FROM digital_categories ORDER BY sort_order'),
    'products' => array_map(fn($r) => [
        'id' => (int)$r['id'], 'slug' => $r['slug'], 'name' => $r['name'], 'category' => $r['category_name'], 'store' => $r['store_name'],
        'price' => (int)$r['price'], 'price_strike' => $r['price_strike'] !== null ? (int)$r['price_strike'] : null, 'image' => $r['image'],
        'delivery' => $r['delivery_type'], 'has_file' => (bool)$r['has_file'], 'has_url' => (bool)$r['has_url'],
        'is_active' => (bool)$r['is_active'], 'is_featured' => (bool)$r['is_featured'],
        'stock' => (int)$r['stock'], 'sold' => (int)$r['sold'], 'waiting' => (int)$r['waiting'],
    ], $rows),
    'upload_limit' => ini_get('upload_max_filesize'),
]);
