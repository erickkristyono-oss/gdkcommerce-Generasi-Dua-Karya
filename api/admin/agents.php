<?php
/**
 * Program Agen — panel admin
 * GET  ?status=pending|approved|rejected|all&q=&page=
 * POST {email, action: approve | reject | revoke, note?}
 */
require __DIR__ . '/../bootstrap.php';
$method = Http::allow('GET', 'POST');
$admin = Admin::require();

if ($method === 'POST') {
    $u = Db::one('SELECT * FROM users WHERE email = ?', [mb_strtolower(Http::str('email', 190))]);
    if (!$u) throw new HttpError('Pengguna tidak ditemukan.', 404);
    if ($u['role'] === 'admin') throw new HttpError('Akun admin tidak bisa dijadikan agen.', 409);
    $action = Http::str('action', 20);
    $note = Http::str('note', 255);
    switch ($action) {
        case 'approve':
            if ($u['role'] === 'agent') throw new HttpError('Pengguna ini sudah menjadi agen.', 409);
            Db::run("UPDATE users SET role = 'agent', agent_status = 'approved', agent_admin_note = NULL, agent_approved_at = NOW(),
                     agent_applied_at = COALESCE(agent_applied_at, NOW()) WHERE id = ? AND role = 'customer'", [$u['id']]);
            $msg = 'Agen disetujui. Harga agen langsung berlaku saat pengguna memuat ulang halaman.';
            break;
        case 'reject':
            if ($u['agent_status'] !== 'pending') throw new HttpError('Tidak ada pengajuan yang menunggu dari pengguna ini.', 409);
            if (mb_strlen($note) < 5) throw new HttpError('Tuliskan alasan penolakan (akan terlihat oleh pemohon).', 422, ['field' => 'note']);
            Db::run("UPDATE users SET agent_status = 'rejected', agent_admin_note = ? WHERE id = ?", [$note, $u['id']]);
            $msg = 'Pengajuan ditolak.';
            break;
        case 'revoke':
            if ($u['role'] !== 'agent') throw new HttpError('Pengguna ini bukan agen.', 409);
            if (mb_strlen($note) < 5) throw new HttpError('Tuliskan alasan pencabutan (akan terlihat oleh pengguna).', 422, ['field' => 'note']);
            Db::run("UPDATE users SET role = 'customer', agent_status = 'rejected', agent_admin_note = ? WHERE id = ? AND role = 'agent'", [$note, $u['id']]);
            $msg = 'Status agen dicabut. Saldo pengguna tetap ada dan bisa dipakai dengan harga umum.';
            break;
        default:
            throw new HttpError('Aksi tidak dikenal.', 400);
    }
    Admin::audit('agent.' . $action, null, ['email' => $u['email'], 'note' => $note ?: null]);
    Http::ok(['message' => $msg]);
}

[$page, $per, $offset] = Admin::page();
$status = (string)($_GET['status'] ?? 'pending');
$where = ["u.role <> 'admin'"]; $p = [];
if ($status === 'approved') $where[] = "u.role = 'agent'";
elseif ($status === 'pending') $where[] = "u.agent_status = 'pending'";
elseif ($status === 'rejected') $where[] = "u.agent_status = 'rejected' AND u.role <> 'agent'";
else $where[] = "(u.role = 'agent' OR u.agent_status <> 'none')";
$q = trim(substr((string)($_GET['q'] ?? ''), 0, 100));
if ($q !== '') {
    $where[] = '(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.agent_store LIKE ? OR u.agent_city LIKE ?)';
    $like = '%' . addcslashes($q, '%_\\') . '%';
    array_push($p, $like, $like, $like, $like, $like);
}
$w = implode(' AND ', $where);
$since = date('Y-m-d 00:00:00', strtotime('-29 days'));
$total = (int)Db::value("SELECT COUNT(*) FROM users u WHERE {$w}", $p);
$rows = Db::all(
    "SELECT u.id, u.name, u.email, u.phone, u.role, u.is_active, u.balance, u.agent_status, u.agent_store, u.agent_city, u.agent_note,
            u.agent_admin_note, u.agent_applied_at, u.agent_approved_at, u.created_at,
            (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id AND o.kind <> 'deposit' AND o.status = 'success' AND o.completed_at >= ?) AS trx30,
            (SELECT COALESCE(SUM(o.total),0) FROM orders o WHERE o.user_id = u.id AND o.kind <> 'deposit' AND o.status = 'success' AND o.completed_at >= ?) AS omzet30,
            (SELECT COALESCE(SUM(o.price - COALESCE(o.supplier_price, o.price)),0) FROM orders o WHERE o.user_id = u.id AND o.kind <> 'deposit' AND o.status = 'success' AND o.completed_at >= ?) AS laba30,
            (SELECT COALESCE(SUM(o.price),0) FROM orders o WHERE o.user_id = u.id AND o.kind = 'deposit' AND o.status = 'success') AS deposit_total
     FROM users u WHERE {$w}
     ORDER BY (u.agent_status = 'pending') DESC, u.agent_applied_at DESC, u.id DESC LIMIT {$per} OFFSET {$offset}",
    array_merge([$since, $since, $since], $p));

$counts = Db::one(
    "SELECT SUM(agent_status = 'pending' AND role <> 'agent') AS pending, SUM(role = 'agent') AS approved,
            SUM(agent_status = 'rejected' AND role <> 'agent') AS rejected, COALESCE(SUM(IF(role = 'agent', balance, 0)),0) AS balance
     FROM users WHERE role <> 'admin'");
$ts = fn($v) => $v ? strtotime($v) * 1000 : null;

Http::ok([
    'agents' => array_map(fn($r) => [
        'name' => $r['name'], 'email' => $r['email'], 'phone' => $r['phone'], 'is_active' => (bool)$r['is_active'],
        'status' => $r['role'] === 'agent' ? 'approved' : $r['agent_status'],
        'store' => $r['agent_store'], 'city' => $r['agent_city'], 'note' => $r['agent_note'], 'admin_note' => $r['agent_admin_note'],
        'applied_at' => $ts($r['agent_applied_at']), 'approved_at' => $ts($r['agent_approved_at']), 'created_at' => $ts($r['created_at']),
        'balance' => (int)$r['balance'], 'trx30' => (int)$r['trx30'], 'omzet30' => (int)$r['omzet30'], 'laba30' => (int)$r['laba30'],
        'deposit_total' => (int)$r['deposit_total'],
    ], $rows),
    'counts' => ['pending' => (int)$counts['pending'], 'approved' => (int)$counts['approved'], 'rejected' => (int)$counts['rejected'], 'balance' => (int)$counts['balance']],
    'rule' => Agent::rule(),
    'enabled' => Agent::enabled(),
    'total' => $total, 'page' => $page, 'per' => $per,
]);
