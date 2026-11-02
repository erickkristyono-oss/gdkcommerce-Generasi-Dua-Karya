<?php
declare(strict_types=1);

/**
 * Saldo GDK — dompet akun pelanggan.
 * Setiap perubahan saldo dicatat di wallet_ledger dengan kunci `ref` yang unik,
 * sehingga operasi yang sama (mis. refund pesanan X) tidak mungkin terjadi dua kali.
 */
final class Wallet
{
    public static function enabled(): bool
    {
        return (bool)config('wallet.enabled', true);
    }

    public static function balance(int $userId): int
    {
        return (int)Db::value('SELECT balance FROM users WHERE id = ?', [$userId]);
    }

    /**
     * Tambah (+) / kurangi (−) saldo secara atomik.
     * @return array|null baris ledger, atau null bila `ref` sudah pernah diproses (idempoten)
     * @throws HttpError 422 bila saldo tidak cukup
     */
    public static function post(int $userId, int $amount, string $type, string $ref, ?int $orderId, string $note, string $by = 'system'): ?array
    {
        if ($amount === 0) throw new HttpError('Nominal tidak boleh nol.', 422);
        return Db::tx(function () use ($userId, $amount, $type, $ref, $orderId, $note, $by) {
            if (Db::value('SELECT 1 FROM wallet_ledger WHERE ref = ?', [$ref])) return null;
            $bal = Db::value('SELECT balance FROM users WHERE id = ? FOR UPDATE', [$userId]);
            if ($bal === null) throw new HttpError('Pengguna tidak ditemukan.', 404);
            $new = (int)$bal + $amount;
            if ($new < 0) {
                throw new HttpError('Saldo tidak cukup. Saldo saat ini Rp ' . number_format((int)$bal, 0, ',', '.') . '.', 422, ['field' => 'balance', 'balance' => (int)$bal]);
            }
            Db::run('UPDATE users SET balance = ? WHERE id = ?', [$new, $userId]);
            $id = Db::insert(
                'INSERT INTO wallet_ledger (user_id, type, amount, balance_after, ref, order_id, note, created_by) VALUES (?,?,?,?,?,?,?,?)',
                [$userId, $type, $amount, $new, $ref, $orderId, mb_substr($note, 0, 255), mb_substr($by, 0, 190)]
            );
            return ['id' => $id, 'amount' => $amount, 'balance_after' => $new];
        });
    }

    public static function history(int $userId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $rows = Db::all(
            "SELECT l.type, l.amount, l.balance_after, l.note, l.created_by, l.created_at, o.invoice
             FROM wallet_ledger l LEFT JOIN orders o ON o.id = l.order_id
             WHERE l.user_id = ? ORDER BY l.id DESC LIMIT {$limit}", [$userId]);
        $labels = ['refund' => 'Refund', 'payment' => 'Pembayaran', 'refund_reversal' => 'Pembatalan refund', 'adjust' => 'Penyesuaian', 'deposit' => 'Deposit'];
        return array_map(fn($r) => [
            'type' => $r['type'], 'label' => $labels[$r['type']] ?? $r['type'], 'amount' => (int)$r['amount'],
            'balance_after' => (int)$r['balance_after'], 'note' => $r['note'], 'invoice' => $r['invoice'],
            'by' => $r['created_by'], 'at' => strtotime($r['created_at']) * 1000,
        ], $rows);
    }
}
