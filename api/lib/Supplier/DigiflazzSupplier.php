<?php
declare(strict_types=1);

namespace Supplier;

use Db;
use HttpError;

/**
 * Integrasi Digiflazz Buyer API
 * Dokumentasi: https://developer.digiflazz.com/api/buyer/
 *
 * Signature:
 *   cek saldo   : md5(username + apiKey + "depo")
 *   price list  : md5(username + apiKey + "pricelist")
 *   transaksi   : md5(username + apiKey + ref_id)
 */
final class DigiflazzSupplier implements SupplierInterface
{
    private string $username;
    private string $apiKey;
    private bool $testing;
    private string $baseUrl;
    private int $timeout;

    public function __construct(array $cfg)
    {
        $this->username = (string)($cfg['username'] ?? '');
        $this->apiKey   = (string)($cfg['api_key'] ?? '');
        $this->testing  = (bool)($cfg['testing'] ?? false);
        $this->baseUrl  = rtrim((string)($cfg['base_url'] ?? 'https://api.digiflazz.com/v1'), '/');
        $this->timeout  = (int)($cfg['timeout'] ?? 40);
        if ($this->username === '' || $this->apiKey === '' || str_contains($this->username, 'USERNAME_')) {
            throw new HttpError('Kredensial Digiflazz belum diatur di config.', 500);
        }
    }

    public function name(): string
    {
        return 'digiflazz';
    }

    /* ---------------- Prabayar ---------------- */

    public function topup(string $sku, string $customerNo, string $refId): array
    {
        return $this->transaction('topup', [
            'buyer_sku_code' => $sku,
            'customer_no'    => $customerNo,
            'ref_id'         => $refId,
        ], $refId);
    }

    public function checkStatus(string $type, string $sku, string $customerNo, string $refId): array
    {
        $payload = ['buyer_sku_code' => $sku, 'customer_no' => $customerNo, 'ref_id' => $refId];
        if ($type === 'postpaid') $payload['commands'] = 'status-pasca';
        // Prabayar: mengirim ulang transaksi dengan ref_id yang sama mengembalikan status terakhir (idempoten).
        return $this->transaction('status', $payload, $refId);
    }

    /* ---------------- Pascabayar ---------------- */

    public function inquiry(string $sku, string $customerNo, string $refId): array
    {
        $res = $this->call('/transaction', [
            'commands'       => 'inq-pasca',
            'username'       => $this->username,
            'buyer_sku_code' => $sku,
            'customer_no'    => $customerNo,
            'ref_id'         => $refId,
            'sign'           => $this->sign($refId),
        ] + $this->testingFlag(), 'inquiry', null);

        $d = $res['data'] ?? [];
        $status = $d['status'] ?? 'Gagal';
        if ($res['_network_error'] ?? false) {
            return ['ok' => false, 'message' => 'Supplier tidak merespons. Coba lagi beberapa saat.'];
        }
        if ($status !== 'Sukses') {
            return ['ok' => false, 'message' => $d['message'] ?? 'Tagihan tidak ditemukan.'];
        }
        $period = null;
        if (!empty($d['desc']['detail'][0]['periode'])) $period = (string)$d['desc']['detail'][0]['periode'];
        return [
            'ok'            => true,
            'message'       => $d['message'] ?? 'OK',
            'customer_name' => $d['customer_name'] ?? null,
            // 'selling_price' = harga yang harus dibayar ke Digiflazz (tagihan + admin - komisi)
            'amount'        => (int)($d['selling_price'] ?? $d['price'] ?? 0),
            'admin'         => (int)($d['admin'] ?? 0),
            'bill'          => (int)($d['price'] ?? 0),
            'period'        => $period,
            'detail'        => is_array($d['desc'] ?? null) ? $d['desc'] : [],
        ];
    }

    public function payBill(string $sku, string $customerNo, string $refId): array
    {
        return $this->transaction('pay', [
            'commands'       => 'pay-pasca',
            'buyer_sku_code' => $sku,
            'customer_no'    => $customerNo,
            'ref_id'         => $refId,
        ], $refId);
    }

    public function checkPlnName(string $customerNo): array
    {
        $res = $this->call('/transaction', [
            'commands'    => 'pln-subscribe',
            'customer_no' => $customerNo,
        ], 'pln-subscribe', null);
        $d = $res['data'] ?? [];
        if (!empty($d['name'])) {
            return ['ok' => true, 'name' => $d['name'], 'segment' => trim(($d['segment_power'] ?? '')), 'message' => 'OK'];
        }
        return ['ok' => false, 'name' => null, 'segment' => null, 'message' => $d['message'] ?? 'Nomor meter tidak ditemukan.'];
    }

    /* ---------------- Informasi ---------------- */

    public function priceList(string $type): array
    {
        $res = $this->call('/price-list', [
            'cmd'      => $type === 'postpaid' ? 'pasca' : 'prepaid',
            'username' => $this->username,
            'sign'     => md5($this->username . $this->apiKey . 'pricelist'),
        ], 'pricelist-' . $type, null, false);
        $data = $res['data'] ?? null;
        if (!is_array($data) || isset($data['rc'])) {
            throw new \RuntimeException('Gagal mengambil price list: ' . ($data['message'] ?? json_encode($res)));
        }
        return $data;
    }

    public function balance(): int
    {
        $res = $this->call('/cek-saldo', [
            'cmd'      => 'deposit',
            'username' => $this->username,
            'sign'     => md5($this->username . $this->apiKey . 'depo'),
        ], 'balance', null);
        if (!isset($res['data']['deposit'])) {
            throw new \RuntimeException('Gagal cek saldo: ' . ($res['data']['message'] ?? json_encode($res)));
        }
        return (int)$res['data']['deposit'];
    }

    /* ---------------- Internal ---------------- */

    private function transaction(string $action, array $payload, string $refId): array
    {
        $payload = ['username' => $this->username] + $payload + ['sign' => $this->sign($refId)] + $this->testingFlag();
        $res = $this->call('/transaction', $payload, $action, $refId);

        if ($res['_network_error'] ?? false) {
            return ['status' => 'Pending', 'rc' => null, 'message' => 'Menunggu respons supplier', 'sn' => null, 'price' => null];
        }
        $d = $res['data'] ?? [];
        $status = $d['status'] ?? null;
        if (!in_array($status, ['Sukses', 'Pending', 'Gagal'], true)) {
            // Respons tidak dikenali → anggap Pending agar dicek ulang, jangan langsung gagal.
            $status = 'Pending';
        }
        return [
            'status'  => $status,
            'rc'      => isset($d['rc']) ? (string)$d['rc'] : null,
            'message' => (string)($d['message'] ?? ''),
            'sn'      => isset($d['sn']) && $d['sn'] !== '' ? (string)$d['sn'] : null,
            'price'   => isset($d['price']) ? (int)$d['price'] : null,
        ];
    }

    private function sign(string $refId): string
    {
        return md5($this->username . $this->apiKey . $refId);
    }

    private function testingFlag(): array
    {
        return $this->testing ? ['testing' => true] : [];
    }

    private function call(string $path, array $payload, string $action, ?string $refId, bool $logResponse = true): array
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        unset($ch);

        $orderId = $refId ? (int)(Db::value('SELECT id FROM orders WHERE supplier_ref = ? OR invoice = ? LIMIT 1', [$refId, $refId]) ?? 0) ?: null : null;
        $safeReq = $payload;
        unset($safeReq['sign']);

        if ($body === false || $body === '') {
            Db::log('digiflazz', $action, $orderId, $safeReq, 'NETWORK ERROR: ' . $err, $code ?: null);
            return ['_network_error' => true];
        }
        Db::log('digiflazz', $action, $orderId, $safeReq, $logResponse ? $body : '[' . strlen($body) . ' bytes]', $code);
        $json = json_decode($body, true);
        if (!is_array($json)) return ['_network_error' => true];
        // Digiflazz kadang mengembalikan HTTP 400 dengan body status Gagal yang valid — tetap diproses.
        return $json;
    }
}
