<?php
declare(strict_types=1);

namespace Payment;

use HttpError;

/**
 * Payment gateway DUMMY.
 * - Membuat nomor VA / string QRIS palsu.
 * - Pembayaran disimulasikan dari halaman pembayaran (tombol "Simulasikan Pembayaran"),
 *   yang mengirim callback ber-signature HMAC — alurnya sama seperti gateway asli,
 *   sehingga logika setelah pembayaran (kirim ke supplier) ikut teruji.
 */
final class DummyPayment implements PaymentInterface
{
    private string $secret;

    public function __construct(string $secret)
    {
        $this->secret = $secret;
    }

    public function name(): string
    {
        return 'dummy';
    }

    public function create(array $order, array $method, array $user): array
    {
        $minutes = (int)config('payment.expiry_minutes', 60);
        $ref = 'DMY-' . strtoupper(bin2hex(random_bytes(6)));
        $code = null;
        $qr = null;
        $url = null;

        switch ($method['kind'] ?? '') {
            case 'va':
                $code = ($method['prefix'] ?? '8808') . str_pad((string)random_int(0, 99999999999), 11, '0', STR_PAD_LEFT);
                break;
            case 'qris':
                // Bukan QRIS sungguhan — hanya untuk tampilan.
                $qr = '00020101021226590013ID.CO.DUMMY.WWW0118DUMMY' . $order['invoice'] . '5204481253033605404' . $order['total'] . '5802ID5913GDK COMMERCE6007JAKARTA6304ABCD';
                break;
            case 'ewallet':
                $url = null; // gateway asli akan memberi deeplink/checkout URL
                break;
        }

        return [
            'reference'  => $ref,
            'pay_code'   => $code,
            'pay_qr'     => $qr,
            'pay_url'    => $url,
            'expired_at' => date('Y-m-d H:i:s', time() + $minutes * 60),
        ];
    }

    public function parseCallback(string $rawBody, array $headers): array
    {
        $sig = $headers['x-callback-signature'] ?? '';
        if (!is_string($sig) || !hash_equals($this->sign($rawBody), $sig)) {
            throw new HttpError('Signature callback tidak valid.', 403);
        }
        $d = json_decode($rawBody, true);
        if (!is_array($d) || empty($d['reference'])) throw new HttpError('Payload callback tidak valid.', 400);
        $status = strtoupper((string)($d['status'] ?? ''));
        if (!in_array($status, ['PAID', 'EXPIRED', 'FAILED'], true)) throw new HttpError('Status callback tidak dikenal.', 400);
        return [
            'reference' => (string)$d['reference'],
            'invoice'   => isset($d['invoice']) ? (string)$d['invoice'] : null,
            'status'    => $status,
            'amount'    => (int)($d['amount'] ?? 0),
        ];
    }

    /** Dipakai oleh simulator untuk membuat callback yang sah. */
    public function sign(string $rawBody): string
    {
        return hash_hmac('sha256', $rawBody, $this->secret);
    }
}
