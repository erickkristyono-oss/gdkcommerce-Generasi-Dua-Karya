<?php
declare(strict_types=1);

namespace Supplier;

/**
 * Kontrak supplier PPOB.
 *
 * Semua method transaksi mengembalikan array "hasil":
 *   [
 *     'status'  => 'Sukses' | 'Pending' | 'Gagal',
 *     'rc'      => string|null,   // response code supplier
 *     'message' => string,
 *     'sn'      => string|null,   // serial number / token / kode voucher
 *     'price'   => int|null,      // harga yang dipotong dari saldo supplier
 *   ]
 *
 * PENTING: kegagalan jaringan / timeout HARUS dikembalikan sebagai 'Pending'
 * (bukan 'Gagal'), karena transaksi mungkin sudah diproses di sisi supplier.
 * Status akhirnya akan diketahui lewat checkStatus() / webhook.
 */
interface SupplierInterface
{
    public function name(): string;

    /** Transaksi prabayar (pulsa, data, token PLN, game, e-wallet). refId = invoice (unik). */
    public function topup(string $sku, string $customerNo, string $refId): array;

    /** Cek status transaksi yang masih Pending. */
    public function checkStatus(string $type, string $sku, string $customerNo, string $refId): array;

    /**
     * Cek tagihan pascabayar.
     * @return array ['ok'=>bool,'message'=>string,'customer_name'=>?string,'amount'=>int (total ke supplier, termasuk admin),
     *                'admin'=>int,'period'=>?string,'detail'=>array]
     */
    public function inquiry(string $sku, string $customerNo, string $refId): array;

    /** Bayar tagihan pascabayar (refId harus sama dengan saat inquiry). */
    public function payBill(string $sku, string $customerNo, string $refId): array;

    /** Cek nama pelanggan PLN prabayar. @return array ['ok'=>bool,'name'=>?string,'segment'=>?string,'message'=>string] */
    public function checkPlnName(string $customerNo): array;

    /** Daftar harga. $type: 'prepaid' | 'postpaid'. Format item mengikuti Digiflazz. */
    public function priceList(string $type): array;

    /** Saldo deposit di supplier. */
    public function balance(): int;
}
