<?php
/**
 * GET                — daftar pesanan (filter: status, q, from, to, page)
 * GET ?invoice=XXX   — detail + log API
 * POST {invoice, action, sn?, note?}
 *   action: recheck | retry | mark_success | mark_failed | refund_balance | refund_done | note | deliver (produk digital: content)
 */
require __DIR__ . '/../bootstrap.php';
$method = Http::allow('GET', 'POST');
$admin = Admin::require();

$detail = function (array $o): array {
    $user = Db::one('SELECT id, name, email, phone FROM users WHERE id = ?', [$o['user_id']]);
    $logs = Db::all('SELECT channel, action, request, response, http_code, created_at FROM api_logs WHERE order_id = ? ORDER BY id DESC LIMIT 40', [$o['id']]);
    $cut = fn($v) => $v === null ? null : (mb_strlen($v) > 1500 ? mb_substr($v, 0, 1500) . '…' : $v);
    return OrderService::present($o) + [
        'user'  => $user,
        'admin' => [
            'sku'              => $o['sku'],
            'supplier'         => $o['supplier'],
            'supplier_price'   => $o['supplier_price'] !== null ? (int)$o['supplier_price'] : null,
            'margin'           => $o['supplier_price'] !== null ? (int)$o['price'] - (int)$o['supplier_price'] : null,
            'supplier_status'  => $o['supplier_status'],
            'supplier_rc'      => $o['supplier_rc'],
            'supplier_message' => $o['supplier_message'],
            'sn'               => $o['sn'],
            'pay_reference'    => $o['pay_reference'],
            'pay_code'         => $o['pay_code'],
            'admin_note'       => $o['admin_note'],
            'supplier_ref'     => $o['supplier_ref'],
            'retry_count'      => (int)$o['retry_count'],
            'max_retry'        => (int)config('wallet.max_retry', 3),
            'refund_method'    => $o['refund_method'],
            'user_balance'     => (int)Db::value('SELECT balance FROM users WHERE id = ?', [$o['user_id']]),
            'kind'             => $o['kind'],
            'buyer_note'       => $o['buyer_note'],
            'delivery'         => $o['kind'] === 'digital' ? DigitalService::deliveryFor($o) : null,
            'download_count'   => (int)$o['download_count'],
            'refunded_at'      => $o['refunded_at'] ? strtotime($o['refunded_at']) * 1000 : null,
            'last_checked_at'  => $o['last_checked_at'] ? strtotime($o['last_checked_at']) * 1000 : null,
            'bill_info'        => $o['bill_info'] ? json_decode($o['bill_info'], true) : null,
        ],
        'logs' => array_map(fn($l) => [
            'channel' => $l['channel'], 'action' => $l['action'], 'http' => $l['http_code'] !== null ? (int)$l['http_code'] : null,
            'request' => $cut($l['request']), 'response' => $cut($l['response']), 'at' => strtotime($l['created_at']) * 1000,
        ], $logs),
    ];
};

/* ---------------- Aksi ---------------- */
if ($method === 'POST') {
    $invoice = Http::str('invoice', 32);
    $action  = Http::str('action', 20);
    $note    = Http::str('note', 255);
    $o = Db::one('SELECT * FROM orders WHERE invoice = ?', [$invoice]);
    if (!$o || $o['status'] === 'inquiry') throw new HttpError('Pesanan tidak ditemukan.', 404);
    $id = (int)$o['id'];
    if ($o['kind'] === 'deposit' && $action !== 'note') {
        throw new HttpError('Pesanan deposit diproses otomatis setelah pembayaran. Untuk koreksi saldo, gunakan Pengguna → Saldo → Penyesuaian.', 409);
    }

    switch ($action) {
        case 'deliver':
            $content = trim(mb_substr((string)Http::input('content', ''), 0, 5000));
            if (mb_strlen($content) < 3) throw new HttpError('Isi produk yang dikirim (link, akun, kode, atau instruksi).', 422, ['field' => 'content']);
            DigitalService::deliverManual($id, $content);
            $msg = 'Produk terkirim. Pembeli bisa melihatnya di halaman pesanan.';
            break;

        case 'recheck':
            if ($o['kind'] === 'digital') throw new HttpError('Pesanan digital tidak dicek ke supplier.', 409);
            if ($o['status'] !== 'processing') throw new HttpError('Hanya pesanan berstatus Diproses yang bisa dicek ulang.', 409);
            OrderService::refresh($o, 0);
            $msg = 'Status diperbarui dari supplier.';
            break;

        case 'retry':
            OrderService::retry($id, $admin['email']);
            $after = OrderService::row($id);
            $msg = [
                'success'    => 'Kirim ulang berhasil. Produk terkirim.',
                'processing' => 'Pesanan dikirim ulang dan sedang diproses supplier.',
                'failed'     => 'Kirim ulang gagal lagi: ' . ($after['supplier_message'] ?? '-') . ($after['refund_status'] === 'done' ? ' Dana kembali ke saldo pelanggan.' : ''),
            ][$after['status']] ?? 'Pesanan dikirim ulang.';
            break;

        case 'mark_success':
            $sn = Http::str('sn', 255);
            if (mb_strlen($sn) < 3) throw new HttpError('Isi SN / bukti transaksi dari dashboard supplier.', 422, ['field' => 'sn']);
            if (!in_array($o['status'], ['processing', 'failed'], true)) throw new HttpError('Status pesanan tidak bisa diubah ke Berhasil.', 409);
            if ($o['status'] === 'failed' && $o['refund_status'] === 'done' && $o['refund_method'] !== 'saldo') {
                throw new HttpError('Dana pesanan ini sudah dikembalikan manual. Tidak bisa ditandai berhasil.', 409);
            }
            Db::tx(function () use ($o, $id, $sn, $note, $admin) {
                // Produk ternyata terkirim, tetapi dana sudah direfund ke saldo → tarik kembali agar tidak dobel
                if ($o['status'] === 'failed' && $o['refund_status'] === 'done' && $o['refund_method'] === 'saldo') {
                    try {
                        Wallet::post((int)$o['user_id'], -(int)$o['total'], 'refund_reversal', 'reversal:' . ($o['supplier_ref'] ?: $o['invoice']), $id,
                            'Pembatalan refund — pesanan ' . $o['invoice'] . ' ternyata berhasil', $admin['email']);
                    } catch (HttpError $e) {
                        if ($e->status === 422) throw new HttpError('Saldo pelanggan sudah terpakai sehingga refund tidak bisa ditarik. Biarkan status Gagal (pelanggan sudah menerima refund).', 409);
                        throw $e;
                    }
                }
                Db::run("UPDATE orders SET status = 'success', supplier_status = 'Sukses', sn = ?, refund_status = 'none', refund_method = NULL, refunded_at = NULL,
                         completed_at = COALESCE(completed_at, NOW()), admin_note = ? WHERE id = ? AND status IN ('processing','failed')", [$sn, $note ?: 'Ditandai berhasil manual', $id]);
            });
            $msg = 'Pesanan ditandai Berhasil.';
            break;

        case 'mark_failed':
            if ($o['status'] !== 'processing') throw new HttpError('Hanya pesanan berstatus Diproses yang bisa ditandai Gagal.', 409);
            if ($note === '') throw new HttpError('Tuliskan alasan (mis. "Gagal di dashboard Digiflazz").', 422, ['field' => 'note']);
            Db::run("UPDATE orders SET status = 'failed', supplier_status = 'Gagal', refund_status = 'pending', completed_at = NOW(), admin_note = ?
                     WHERE id = ? AND status = 'processing'", [$note, $id]);
            OrderService::autoRefund($id);
            $msg = OrderService::row($id)['refund_status'] === 'done'
                ? 'Pesanan ditandai Gagal. Dana otomatis dikembalikan ke Saldo GDK pelanggan.'
                : 'Pesanan ditandai Gagal dan masuk antrean refund.';
            break;

        case 'refund_balance':
            if ($o['status'] !== 'failed' || $o['refund_status'] !== 'pending') throw new HttpError('Pesanan ini tidak menunggu refund.', 409);
            if (!Wallet::enabled()) throw new HttpError('Saldo GDK dinonaktifkan di konfigurasi.', 409);
            OrderService::refundToBalance($id, $admin['email'], 'Refund oleh admin');
            if ($note !== '') Db::run('UPDATE orders SET admin_note = ? WHERE id = ?', [$note, $id]);
            $msg = 'Dana dikembalikan ke Saldo GDK pelanggan.';
            break;

        case 'refund_done':
            if ($o['status'] !== 'failed' || $o['refund_status'] !== 'pending') throw new HttpError('Pesanan ini tidak menunggu refund.', 409);
            if ($note === '') throw new HttpError('Tuliskan keterangan refund (mis. "Transfer BCA 02/11 a.n. Budi").', 422, ['field' => 'note']);
            Db::run("UPDATE orders SET refund_status = 'done', refund_method = 'manual', refunded_at = NOW(), admin_note = ? WHERE id = ? AND refund_status = 'pending'", [$note, $id]);
            $msg = 'Refund ditandai selesai.';
            break;

        case 'note':
            Db::run('UPDATE orders SET admin_note = ? WHERE id = ?', [$note ?: null, $id]);
            $msg = 'Catatan disimpan.';
            break;

        default:
            throw new HttpError('Aksi tidak dikenal.', 400);
    }
    Admin::audit('order.' . $action, $id, ['invoice' => $invoice, 'note' => $note, 'sn' => Http::str('sn', 255) ?: null, 'from_status' => $o['status']]);
    Http::ok(['message' => $msg, 'order' => $detail(OrderService::row($id))]);
}

/* ---------------- Detail ---------------- */
$invoice = substr((string)($_GET['invoice'] ?? ''), 0, 32);
if ($invoice !== '') {
    $o = Db::one('SELECT * FROM orders WHERE invoice = ?', [$invoice]);
    if (!$o || $o['status'] === 'inquiry') throw new HttpError('Pesanan tidak ditemukan.', 404);
    Http::ok(['order' => $detail($o)]);
}

/* ---------------- Daftar ---------------- */
[$page, $per, $offset] = Admin::page();
$where = ["o.status <> 'inquiry'"];
$p = [];
$status = (string)($_GET['status'] ?? '');
if ($status === 'refund') {
    $where[] = "o.status = 'failed' AND o.refund_status = 'pending'";
} elseif ($status === 'refunded') {
    $where[] = "o.refund_status = 'done'";
} elseif ($status === 'digital_waiting') {
    $where[] = "o.kind = 'digital' AND o.status = 'processing'";
} elseif ($status === 'deposit') {
    $where[] = "o.kind = 'deposit'";
} elseif ($status === 'agent') {
    $where[] = "o.is_agent = 1 AND o.kind <> 'deposit'";
} elseif (in_array($status, ['unpaid', 'processing', 'success', 'failed', 'expired'], true)) {
    $where[] = 'o.status = ?'; $p[] = $status;
}
$q = trim(substr((string)($_GET['q'] ?? ''), 0, 100));
if ($q !== '') {
    $where[] = '(o.invoice LIKE ? OR o.customer_no LIKE ? OR u.email LIKE ? OR u.name LIKE ? OR o.brand_name LIKE ?)';
    $like = '%' . addcslashes($q, '%_\\') . '%';
    array_push($p, $like, $like, $like, $like, $like);
}
if ($from = Admin::date($_GET['from'] ?? null)) { $where[] = 'o.created_at >= ?'; $p[] = $from . ' 00:00:00'; }
if ($to = Admin::date($_GET['to'] ?? null))     { $where[] = 'o.created_at < (? + INTERVAL 1 DAY)'; $p[] = $to; }
$w = implode(' AND ', $where);

$total = (int)Db::value("SELECT COUNT(*) FROM orders o JOIN users u ON u.id = o.user_id WHERE {$w}", $p);
$sum = Db::one("SELECT COALESCE(SUM(o.total),0) AS t FROM orders o JOIN users u ON u.id = o.user_id WHERE {$w} AND o.status = 'success' AND o.kind <> 'deposit'", $p);
$rows = Db::all("SELECT o.*, u.name AS user_name, u.email AS user_email FROM orders o JOIN users u ON u.id = o.user_id
                 WHERE {$w} ORDER BY o.id DESC LIMIT {$per} OFFSET {$offset}", $p);

Http::ok([
    'orders' => array_map(fn($o) => OrderService::present($o) + [
        'user' => ['name' => $o['user_name'], 'email' => $o['user_email']],
        'margin' => $o['supplier_price'] !== null && $o['status'] === 'success' ? (int)$o['price'] - (int)$o['supplier_price'] : null,
        'admin_note' => $o['admin_note'],
    ], $rows),
    'total' => $total, 'page' => $page, 'per' => $per, 'success_sum' => (int)$sum['t'],
]);
