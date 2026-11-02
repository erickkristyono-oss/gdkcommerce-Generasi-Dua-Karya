<?php
declare(strict_types=1);

namespace Payment;

/**
 * Kontrak payment gateway. Untuk menambah gateway asli (Midtrans/Xendit/Tripay),
 * buat class baru yang mengimplementasikan interface ini lalu daftarkan di Factory.
 */
interface PaymentInterface
{
    public function name(): string;

    /**
     * Buat tagihan pembayaran.
     * @param array $order  baris tabel orders
     * @param array $method item dari config payment.methods
     * @return array ['reference'=>string,'pay_code'=>?string,'pay_qr'=>?string,'pay_url'=>?string,'expired_at'=>string 'Y-m-d H:i:s']
     */
    public function create(array $order, array $method, array $user): array;

    /**
     * Verifikasi & baca notifikasi (callback/webhook) dari gateway.
     * @return array ['reference'=>string,'invoice'=>?string,'status'=>'PAID'|'EXPIRED'|'FAILED','amount'=>int]
     * @throws \HttpError jika signature tidak valid
     */
    public function parseCallback(string $rawBody, array $headers): array;
}
