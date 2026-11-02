<?php
declare(strict_types=1);

namespace Supplier;

use Db;

/**
 * Supplier SIMULASI — tanpa transaksi nyata. Dipakai saat supplier.driver = 'mock'.
 *
 * Skenario uji (berdasarkan nomor tujuan):
 *   - berakhiran 0000 → transaksi Gagal (kecuali saat dikirim ulang admin: ref berakhiran R1/R2/..)
 *   - berakhiran 9999 → Pending dulu, lalu Sukses saat dicek ulang
 *   - selain itu      → Sukses langsung
 *   - Pascabayar berakhiran 0000 → tagihan tidak ditemukan
 */
final class MockSupplier implements SupplierInterface
{
    public function name(): string
    {
        return 'mock';
    }

    public function topup(string $sku, string $customerNo, string $refId): array
    {
        $digits = preg_replace('/\D/', '', $customerNo);
        $isRetry = (bool)preg_match('/R\d+$/', $refId);
        if (str_ends_with($digits, '0000') && !$isRetry) {
            $r = $this->result('Gagal', '02', 'Transaksi gagal (simulasi): nomor tujuan tidak valid');
        } elseif (str_ends_with($digits, '9999')) {
            $r = $this->result('Pending', '03', 'Transaksi sedang diproses (simulasi)');
        } else {
            $r = $this->result('Sukses', '00', 'Transaksi sukses (simulasi)', $this->sn($sku, $refId));
        }
        $this->log('topup', $refId, compact('sku', 'customerNo', 'refId'), $r);
        return $r;
    }

    public function checkStatus(string $type, string $sku, string $customerNo, string $refId): array
    {
        $digits = preg_replace('/\D/', '', $customerNo);
        $r = str_ends_with($digits, '0000') && !preg_match('/R\d+$/', $refId)
            ? $this->result('Gagal', '02', 'Transaksi gagal (simulasi)')
            : $this->result('Sukses', '00', 'Transaksi sukses (simulasi)', $this->sn($sku, $refId));
        $this->log('status', $refId, compact('type', 'sku', 'customerNo', 'refId'), $r);
        return $r;
    }

    public function inquiry(string $sku, string $customerNo, string $refId): array
    {
        if (str_ends_with($customerNo, '0000') && !preg_match('/R\d+$/', $refId)) {
            return ['ok' => false, 'message' => 'Tagihan tidak ditemukan atau sudah lunas (simulasi).'];
        }
        $h = crc32($sku . $customerNo);
        $bill = 50000 + ($h % 450000);
        $bill = (int)(round($bill / 100) * 100);
        $admin = 2500;
        $names = ['Budi Santoso', 'Siti Rahma', 'Andi Wijaya', 'Dewi Lestari', 'Rizky Pratama', 'Putri Ananda'];
        $r = [
            'ok'            => true,
            'message'       => 'Inquiry sukses (simulasi)',
            'customer_name' => $names[$h % count($names)],
            'amount'        => $bill + $admin - 500, // anggap komisi Rp 500
            'admin'         => $admin,
            'bill'          => $bill + $admin,
            'period'        => (new \DateTime())->format('Ym'),
            'detail'        => ['lembar_tagihan' => 1],
        ];
        $this->log('inquiry', $refId, compact('sku', 'customerNo', 'refId'), $r);
        return $r;
    }

    public function payBill(string $sku, string $customerNo, string $refId): array
    {
        $r = $this->result('Sukses', '00', 'Pembayaran tagihan sukses (simulasi)', 'REF' . strtoupper(substr(md5($refId), 0, 16)));
        $this->log('pay', $refId, compact('sku', 'customerNo', 'refId'), $r);
        return $r;
    }

    public function checkPlnName(string $customerNo): array
    {
        if (str_ends_with($customerNo, '0000')) return ['ok' => false, 'name' => null, 'segment' => null, 'message' => 'Nomor meter tidak ditemukan (simulasi).'];
        $names = ['BUDI SANTOSO', 'SITI RAHMA', 'ANDI WIJAYA', 'DEWI LESTARI'];
        return ['ok' => true, 'name' => $names[crc32($customerNo) % 4], 'segment' => 'R1 /000001300', 'message' => 'OK'];
    }

    public function priceList(string $type): array
    {
        // Mock memakai harga dari database (seed), jadi sinkronisasi tidak diperlukan.
        return [];
    }

    public function balance(): int
    {
        return 10000000;
    }

    private function result(string $status, string $rc, string $message, ?string $sn = null): array
    {
        return ['status' => $status, 'rc' => $rc, 'message' => $message, 'sn' => $sn, 'price' => null];
    }

    private function sn(string $sku, string $refId): string
    {
        $h = strtoupper(substr(hash('sha256', $refId), 0, 20));
        if (str_contains($sku, 'tokenpln')) {
            $d = substr(preg_replace('/\D/', '', (string)hexdec(substr($h, 0, 12)) . (string)crc32($refId)), 0, 20);
            return implode('-', str_split(str_pad($d, 20, '7'), 4)) . '/BUDI SANTOSO/R1/1300VA/32.5';
        }
        return $h;
    }

    private function log(string $action, string $refId, array $req, array $res): void
    {
        $orderId = (int)(Db::value('SELECT id FROM orders WHERE supplier_ref = ? OR invoice = ? LIMIT 1', [$refId, $refId]) ?? 0) ?: null;
        Db::log('mock', $action, $orderId, $req, $res, 200);
    }
}
