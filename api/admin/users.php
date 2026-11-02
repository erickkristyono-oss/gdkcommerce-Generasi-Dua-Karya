<?php
/**
 * GET  ?q=&page=
 * GET  ?ledger=email          — mutasi Saldo GDK pengguna
 * POST {email, action: toggle_active | make_admin | make_customer | reset_2fa}
 * POST {email, action: adjust_balance, amount (+/-), note}
 */
require __DIR__ . '/../bootstrap.php';
$method = Http::allow('GET', 'POST');
$admin = Admin::require();

if ($method === 'POST') {
    $u = Db::one('SELECT * FROM users WHERE email = ?', [mb_strtolower(Http::str('email', 190))]);
    if (!$u) throw new HttpError('Pengguna tidak ditemukan.', 404);
    if ((int)$u['id'] === (int)$admin['id']) throw new HttpError('Anda tidak dapat mengubah akun Anda sendiri.', 409);
    $action = Http::str('action', 20);
    switch ($action) {
        case 'adjust_balance':
            $amount = filter_var(Http::input('amount'), FILTER_VALIDATE_INT);
            $note = Http::str('note', 200);
            if ($amount === false || $amount === 0 || abs($amount) > 50000000) throw new HttpError('Nominal tidak valid (contoh: 50000 atau -50000).', 422, ['field' => 'amount']);
            if (mb_strlen($note) < 5) throw new HttpError('Tuliskan alasan penyesuaian (mis. "Pencairan ke BCA 1234").', 422, ['field' => 'note']);
            $r = Wallet::post((int)$u['id'], $amount, 'adjust', 'adjust:' . bin2hex(random_bytes(8)), null, $note, $admin['email']);
            Admin::audit('user.adjust_balance', null, ['email' => $u['email'], 'amount' => $amount, 'note' => $note, 'balance_after' => $r['balance_after']]);
            Http::ok(['message' => 'Saldo diperbarui. Saldo sekarang Rp ' . number_format($r['balance_after'], 0, ',', '.') . '.']);
        case 'reset_2fa':
            if (!$u['totp_enabled']) throw new HttpError('2FA pengguna ini belum aktif.', 409);
            TwoFactor::reset((int)$u['id'], $admin['email']);
            $msg = '2FA direset. Pengguna perlu mengaktifkan ulang.';
            break;
        case 'toggle_active':
            Db::run('UPDATE users SET is_active = 1 - is_active WHERE id = ?', [$u['id']]);
            $msg = $u['is_active'] ? 'Akun dinonaktifkan. Pengguna otomatis keluar.' : 'Akun diaktifkan kembali.';
            break;
        case 'make_admin':
            Db::run("UPDATE users SET role = 'admin' WHERE id = ?", [$u['id']]); $msg = 'Pengguna dijadikan admin.'; break;
        case 'make_customer':
            Db::run("UPDATE users SET role = 'customer' WHERE id = ?", [$u['id']]); $msg = 'Akses admin dicabut.'; break;
        default:
            throw new HttpError('Aksi tidak dikenal.', 400);
    }
    Admin::audit('user.' . $action, null, ['email' => $u['email']]);
    Http::ok(['message' => $msg]);
}

if (!empty($_GET['ledger'])) {
    $u = Db::one('SELECT id, name, email, balance FROM users WHERE email = ?', [mb_strtolower(substr((string)$_GET['ledger'], 0, 190))]);
    if (!$u) throw new HttpError('Pengguna tidak ditemukan.', 404);
    Http::ok(['user' => ['name' => $u['name'], 'email' => $u['email'], 'balance' => (int)$u['balance']], 'history' => Wallet::history((int)$u['id'], 100)]);
}

[$page, $per, $offset] = Admin::page();
$where = '1=1'; $p = [];
$q = trim(substr((string)($_GET['q'] ?? ''), 0, 100));
if ($q !== '') { $where = '(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)'; $like = '%' . addcslashes($q, '%_\\') . '%'; $p = [$like, $like, $like]; }
$total = (int)Db::value("SELECT COUNT(*) FROM users u WHERE {$where}", $p);
$rows = Db::all(
    "SELECT u.id, u.name, u.email, u.phone, u.role, u.is_active, u.balance, u.totp_enabled, u.created_at, u.last_login_at,
            (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id AND o.status = 'success') AS orders_ok,
            (SELECT COALESCE(SUM(total),0) FROM orders o WHERE o.user_id = u.id AND o.status = 'success') AS spent
     FROM users u WHERE {$where} ORDER BY u.id DESC LIMIT {$per} OFFSET {$offset}", $p);
Http::ok([
    'users' => array_map(fn($r) => [
        'name' => $r['name'], 'email' => $r['email'], 'phone' => $r['phone'], 'role' => $r['role'], 'is_active' => (bool)$r['is_active'],
        'balance' => (int)$r['balance'], 'twofa' => (bool)$r['totp_enabled'],
        'is_self' => (int)$r['id'] === (int)$admin['id'],
        'created_at' => strtotime($r['created_at']) * 1000, 'last_login_at' => $r['last_login_at'] ? strtotime($r['last_login_at']) * 1000 : null,
        'orders' => (int)$r['orders_ok'], 'spent' => (int)$r['spent'],
    ], $rows),
    'total' => $total, 'page' => $page, 'per' => $per,
]);
