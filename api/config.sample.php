<?php
/**
 * GDK Commerce — Konfigurasi
 * -----------------------------------------------------------
 * 1. Salin file ini menjadi `config.php` (folder yang sama), ATAU — lebih aman —
 *    simpan sebagai `gdk-config.php` DI LUAR public_html
 *    (mis. /home/u123456789/domains/domainanda.com/gdk-config.php).
 *    Backend otomatis mencari file di luar public_html terlebih dahulu.
 * 2. Isi kredensial database dari hPanel → Databases → MySQL Databases.
 * 3. JANGAN pernah membagikan file ini.
 */
return [
    'app' => [
        'env'       => 'development',            // 'development' | 'production'
        'url'       => 'https://domainanda.com', // tanpa garis miring di akhir
        'timezone'  => 'Asia/Jakarta',
        'name'      => 'GDK Commerce',
        // Kunci enkripsi (untuk secret 2FA). WAJIB diisi di produksi, 64 karakter acak.
        // Buat dengan: php -r "echo bin2hex(random_bytes(32));"
        // JANGAN diganti setelah ada pengguna yang mengaktifkan 2FA (secret lama tidak bisa dibaca).
        'key'       => '',
    ],

    'security' => [
        'admin_2fa' => true, // wajibkan verifikasi dua langkah untuk semua admin
    ],

    'wallet' => [
        'enabled'        => true, // Saldo GDK bisa dipakai sebagai metode pembayaran
        'auto_refund'    => true, // pesanan gagal → dana otomatis kembali ke Saldo GDK
        'max_retry'      => 3,    // batas kirim ulang pesanan gagal oleh admin
    ],

    'db' => [
        'host'     => 'localhost',
        'port'     => 3306,
        'name'     => 'u123456789_gdk',
        'user'     => 'u123456789_gdk',
        'pass'     => 'GANTI_PASSWORD_DATABASE',
        'charset'  => 'utf8mb4',
    ],

    'session' => [
        'name'     => 'gdk_sid',
        'lifetime' => 60 * 60 * 24 * 7, // 7 hari
    ],

    /* -------------------------------------------------------
     * Supplier PPOB
     *  - 'mock'      : simulasi lokal, tanpa akun supplier (untuk uji coba)
     *  - 'digiflazz' : transaksi asli via Digiflazz (https://digiflazz.com)
     * ----------------------------------------------------- */
    'supplier' => [
        'driver' => 'mock',

        'digiflazz' => [
            'username'       => 'USERNAME_DIGIFLAZZ',
            'api_key'        => 'API_KEY_PRODUCTION_ATAU_DEVELOPMENT',
            'webhook_secret' => 'SECRET_WEBHOOK_DARI_DASHBOARD_DIGIFLAZZ',
            // true = mode testing Digiflazz (pakai API key Development & SKU uji coba)
            'testing'        => true,
            'base_url'       => 'https://api.digiflazz.com/v1',
            'timeout'        => 40,
        ],

        // Margin harga jual otomatis saat sync price list (prabayar)
        'markup' => [
            'flat'    => 1000, // tambahan Rp per transaksi
            'percent' => 0,    // tambahan persen dari harga modal (mis. 2 = 2%)
            'round'   => 100,  // pembulatan ke atas ke kelipatan ini
        ],
        // Margin tagihan pascabayar diatur per brand: kolom brands.admin_fee
    ],

    /* -------------------------------------------------------
     * Payment Gateway
     *  - 'dummy' : simulasi (tombol "Simulasikan Pembayaran" di halaman bayar)
     *  Nanti bisa ditambah: 'midtrans', 'xendit', 'tripay'
     * ----------------------------------------------------- */
    'payment' => [
        'driver'          => 'dummy',
        'expiry_minutes'  => 60,
        'callback_secret' => 'GANTI_DENGAN_STRING_ACAK_PANJANG',
        // Metode pembayaran yang ditampilkan. fee: angka >= 1 = Rupiah, < 1 = persen (0.007 = 0,7%)
        'methods' => [
            ['id' => 'qris',       'name' => 'QRIS',       'kind' => 'qris', 'color' => '#111827', 'fee' => 0.007],
            ['id' => 'va-bca',     'name' => 'VA BCA',     'kind' => 'va',   'color' => '#0060af', 'fee' => 4000, 'prefix' => '39358'],
            ['id' => 'va-bri',     'name' => 'VA BRI',     'kind' => 'va',   'color' => '#00529c', 'fee' => 4000, 'prefix' => '26215'],
            ['id' => 'va-mandiri', 'name' => 'VA Mandiri', 'kind' => 'va',   'color' => '#003d79', 'fee' => 4000, 'prefix' => '88908'],
            ['id' => 'dana',       'name' => 'DANA',       'kind' => 'ewallet', 'color' => '#118eea', 'fee' => 0.015],
            ['id' => 'ovo',        'name' => 'OVO',        'kind' => 'ewallet', 'color' => '#4c2a86', 'fee' => 0.015],
        ],
    ],

    'digital' => [
        // Folder file produk digital (privat). Disarankan DI LUAR public_html, mis. '/home/u123456789/gdk-files'.
        // Default: folder storage/digital di dalam website (dilindungi .htaccess).
        // 'storage' => '/home/u123456789/gdk-files',
    ],

    'limits' => [
        'max_unpaid_orders' => 5,   // per pengguna
        'login_attempts'    => 5,   // per 15 menit
        'register_per_hour' => 5,   // per IP
    ],

    // Token rahasia untuk menjalankan cron lewat URL (jika hosting tidak mendukung cron CLI).
    // Kosongkan untuk menonaktifkan akses cron lewat web.
    'cron_token' => '',
];
