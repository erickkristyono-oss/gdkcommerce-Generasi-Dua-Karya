<?php
/**
 * Sinkronisasi produk & harga dari Digiflazz.
 * Jalankan manual setelah setup, lalu jadwalkan tiap 1–6 jam di hPanel → Advanced → Cron Jobs:
 *   php /home/uXXXX/domains/domainanda.com/public_html/api/cron/sync-products.php
 * Bisa juga dijalankan dari Panel Admin → Supplier → "Sinkron Sekarang".
 *
 * - Produk dicocokkan ke brand lewat kolom brands.df_brand (+ df_category bila diisi).
 * - Harga jual = harga modal + markup (config supplier.markup), kecuali products.price_locked = 1.
 * - Produk yang tidak ada lagi di price list ditandai tidak tersedia (supplier_ok = 0).
 *   Status aktif/nonaktif dari admin tidak pernah diubah oleh sinkronisasi.
 */
require __DIR__ . '/_guard.php';

$r = ProductSync::run();
if ($r['skipped']) { out($r['message']); exit(0); }

out('Saldo Digiflazz: Rp ' . number_format($r['balance'], 0, ',', '.'));
out("Prabayar tersinkron : {$r['prepaid']}");
out("Pascabayar tersinkron: {$r['postpaid']}");
out("Tidak tersedia (tidak ada di price list): {$r['deactivated']}");
if ($r['unmapped']) {
    out('');
    out('Brand Digiflazz yang BELUM dipetakan (isi brands.df_category / df_brand jika ingin dijual):');
    foreach ($r['unmapped'] as $type => $rows) {
        foreach ($rows as $k => $n) out("  [{$type}] {$k}  ({$n} produk)");
    }
}
