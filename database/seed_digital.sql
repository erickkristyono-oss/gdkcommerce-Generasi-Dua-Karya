-- =========================================================
-- GDK Commerce — Produk digital contoh (dari katalog gdkcommerce.id)
-- Cara kirim awal: MANUAL (admin mengirim setelah pembayaran).
-- Ubah ke 'download' / 'license' di Panel Admin → Produk Digital setelah file/kode lisensi tersedia.
-- =========================================================
SET NAMES utf8mb4;

INSERT INTO digital_categories (id, name, icon, sort_order) VALUES
  ('game', 'Game', 'game', 1),
  ('plugin', 'Plugin', 'spark', 2),
  ('software', 'Software', 'code', 3),
  ('website-service', 'Website Service', 'globe', 4),
  ('ebook', 'E-Book', 'book', 5)
ON DUPLICATE KEY UPDATE name=VALUES(name), icon=VALUES(icon), sort_order=VALUES(sort_order);

INSERT INTO digital_products (slug, name, category_id, store_name, short_desc, description, price, platform, license_type, delivery_type, delivery_note, buyer_note_label, buyer_note_required, is_featured, sort_order) VALUES
  ('ebook-tutorial-adobe', 'E-Book Tutorial Adobe Software', 'ebook', 'Generasi Berkarya Store', 'Panduan praktis menguasai aplikasi Adobe dari dasar hingga mahir, lengkap dengan contoh kasus.', '## Isi e-book
- Pengenalan antarmuka Photoshop, Illustrator & Premiere Pro
- Teknik editing foto dan desain grafis
- Dasar editing video
- Tips alur kerja yang efisien

## Format
- PDF, bisa dibaca di HP, tablet & komputer', 62000, 'PDF', 'Penggunaan pribadi', 'manual', 'Terima kasih! Produk Anda sedang disiapkan oleh tim kami dan akan muncul di halaman ini (maks. 1×24 jam). Kami juga akan menghubungi Anda melalui email/WhatsApp bila diperlukan.', NULL, 0, 0, 1),
  ('ebook-tutorial-elementor', 'E-Book Tutorial Elementor', 'ebook', 'Generasi Berkarya Store', 'Belajar membangun website WordPress profesional dengan Elementor tanpa coding.', '## Isi e-book
- Instalasi WordPress & Elementor
- Membuat halaman dengan drag-and-drop
- Template, widget & desain responsif
- Optimasi kecepatan halaman

## Format
- PDF', 80000, 'PDF', 'Penggunaan pribadi', 'manual', 'Terima kasih! Produk Anda sedang disiapkan oleh tim kami dan akan muncul di halaman ini (maks. 1×24 jam). Kami juga akan menghubungi Anda melalui email/WhatsApp bila diperlukan.', NULL, 0, 1, 2),
  ('ebook-tutorial-growtopia', 'E-Book Tutorial Growtopia Game', 'ebook', 'Generasi Berkarya Store', 'Strategi dan tips bermain Growtopia untuk pemula hingga pemain berpengalaman.', '## Isi e-book
- Dasar permainan & pengelolaan world
- Farming & crafting item
- Strategi trading yang aman

## Format
- PDF', 50000, 'PDF', 'Penggunaan pribadi', 'manual', 'Terima kasih! Produk Anda sedang disiapkan oleh tim kami dan akan muncul di halaman ini (maks. 1×24 jam). Kami juga akan menghubungi Anda melalui email/WhatsApp bila diperlukan.', NULL, 0, 0, 3),
  ('ebook-tutorial-ubuntu', 'E-Book Tutorial Ubuntu', 'ebook', 'Generasi Berkarya Store', 'Panduan lengkap memakai Ubuntu Linux untuk kebutuhan sehari-hari dan pengembangan.', '## Isi e-book
- Instalasi Ubuntu & dual boot
- Perintah dasar terminal
- Manajemen paket & pengguna
- Konfigurasi server sederhana

## Format
- PDF', 50000, 'PDF', 'Penggunaan pribadi', 'manual', 'Terima kasih! Produk Anda sedang disiapkan oleh tim kami dan akan muncul di halaman ini (maks. 1×24 jam). Kami juga akan menghubungi Anda melalui email/WhatsApp bila diperlukan.', NULL, 0, 0, 4),
  ('elementor', 'Elementor', 'software', 'INOVASI KARYA SOLUSI', 'Page builder WordPress drag-and-drop untuk membuat website profesional tanpa coding.', '## Fitur utama
- Editor drag-and-drop visual
- Ratusan template siap pakai
- Kustomisasi desain penuh
- Tampilan responsif di semua perangkat
- Integrasi dengan plugin populer', 989000, 'WordPress', 'Lisensi 1 website', 'manual', 'Terima kasih! Produk Anda sedang disiapkan oleh tim kami dan akan muncul di halaman ini (maks. 1×24 jam). Kami juga akan menghubungi Anda melalui email/WhatsApp bila diperlukan.', 'Email untuk pengiriman produk', 1, 1, 5),
  ('game-free-fire', 'GAME-FREE FIRE', 'game', 'Generasi Berkarya Store', 'Game battle royale seru: 50 pemain, satu pulau, satu pemenang dalam 10–15 menit.', '## Tentang game
- Pertarungan 50 pemain dalam satu peta
- Durasi pertandingan sekitar 10–15 menit
- Beragam karakter, senjata & mode permainan', 298000, 'Android / iOS', NULL, 'manual', 'Terima kasih! Produk Anda sedang disiapkan oleh tim kami dan akan muncul di halaman ini (maks. 1×24 jam). Kami juga akan menghubungi Anda melalui email/WhatsApp bila diperlukan.', 'ID akun game / email', 1, 1, 6),
  ('game-growtopia', 'GAME-Growtopia', 'game', 'Generasi Berkarya Store', 'Game sandbox multiplayer: bangun world, crafting, dan berdagang dengan pemain lain.', '## Tentang game
- Bangun world kreasi sendiri
- Ribuan item untuk di-craft
- Komunitas & trading antar pemain', 167000, 'Android / iOS / PC', NULL, 'manual', 'Terima kasih! Produk Anda sedang disiapkan oleh tim kami dan akan muncul di halaman ini (maks. 1×24 jam). Kami juga akan menghubungi Anda melalui email/WhatsApp bila diperlukan.', 'ID akun game / email', 1, 0, 7),
  ('game-honor-of-kings', 'Game-honor_of_king', 'game', 'Generasi Berkarya Store', 'Game MOBA 5v5 dengan hero ikonik dan pertarungan tim yang kompetitif.', '## Tentang game
- Pertarungan tim 5 lawan 5
- Puluhan hero dengan peran berbeda
- Mode ranked & kasual', 149000, 'Android / iOS', NULL, 'manual', 'Terima kasih! Produk Anda sedang disiapkan oleh tim kami dan akan muncul di halaman ini (maks. 1×24 jam). Kami juga akan menghubungi Anda melalui email/WhatsApp bila diperlukan.', 'ID akun game / email', 1, 0, 8),
  ('game-pubg', 'GAME-PUBG', 'game', 'INTEGRASI BERKARYA', 'Battle royale realistis: bertahan hidup hingga menjadi pemain terakhir.', '## Tentang game
- Peta luas dengan berbagai medan
- Mode solo, duo & squad
- Senjata dan kendaraan beragam', 190000, 'Android / iOS / PC', NULL, 'manual', 'Terima kasih! Produk Anda sedang disiapkan oleh tim kami dan akan muncul di halaman ini (maks. 1×24 jam). Kami juga akan menghubungi Anda melalui email/WhatsApp bila diperlukan.', 'ID akun game / email', 1, 0, 9),
  ('game-roblox', 'Game-Roblox', 'game', 'INTEGRASI BERKARYA', 'Platform game dengan jutaan dunia buatan komunitas untuk dimainkan bersama teman.', '## Tentang game
- Jutaan game buatan komunitas
- Kustomisasi avatar
- Bermain bersama teman lintas perangkat', 230000, 'Android / iOS / PC', NULL, 'manual', 'Terima kasih! Produk Anda sedang disiapkan oleh tim kami dan akan muncul di halaman ini (maks. 1×24 jam). Kami juga akan menghubungi Anda melalui email/WhatsApp bila diperlukan.', 'Username Roblox / email', 1, 1, 10),
  ('game-valorant', 'GAME-VALORAN', 'game', 'INTEGRASI BERKARYA', 'Shooter taktis 5v5 berbasis karakter dengan kemampuan unik setiap agent.', '## Tentang game
- Pertarungan taktis 5 lawan 5
- Agent dengan kemampuan khusus
- Mode kompetitif', 140000, 'PC', NULL, 'manual', 'Terima kasih! Produk Anda sedang disiapkan oleh tim kami dan akan muncul di halaman ini (maks. 1×24 jam). Kami juga akan menghubungi Anda melalui email/WhatsApp bila diperlukan.', 'Riot ID / email', 1, 0, 11),
  ('genshin-impact-game', 'Ghensin impact-Game', 'game', 'INTEGRASI BERKARYA', 'Game petualangan open-world dengan dunia fantasi luas dan sistem elemen yang unik.', '## Tentang game
- Dunia terbuka yang luas untuk dijelajahi
- Kombinasi elemen dalam pertarungan
- Karakter & cerita yang terus diperbarui', 280000, 'Android / iOS / PC', NULL, 'manual', 'Terima kasih! Produk Anda sedang disiapkan oleh tim kami dan akan muncul di halaman ini (maks. 1×24 jam). Kami juga akan menghubungi Anda melalui email/WhatsApp bila diperlukan.', 'UID / email akun', 1, 1, 12),
  ('plugin-elementor-wp', 'Plugin Elementor-WP', 'plugin', 'Berkarya Cipta Media', 'Plugin Elementor untuk WordPress: bangun halaman cantik dengan editor visual.', '## Fitur
- Editor visual real-time
- Widget & template lengkap
- Kompatibel dengan tema WordPress populer', 650000, 'WordPress', 'Lisensi 1 website', 'manual', 'Terima kasih! Produk Anda sedang disiapkan oleh tim kami dan akan muncul di halaman ini (maks. 1×24 jam). Kami juga akan menghubungi Anda melalui email/WhatsApp bila diperlukan.', 'Email untuk pengiriman produk', 1, 0, 13),
  ('plugin-all-in-one-seo', 'Plugin-All In One SEO', 'plugin', 'Berkarya Cipta Media', 'Optimasi SEO WordPress lengkap agar website lebih mudah ditemukan di Google.', '## Fitur
- Analisis SEO per halaman
- Sitemap XML otomatis
- Integrasi media sosial & schema markup', 480000, 'WordPress', 'Lisensi 1 website', 'manual', 'Terima kasih! Produk Anda sedang disiapkan oleh tim kami dan akan muncul di halaman ini (maks. 1×24 jam). Kami juga akan menghubungi Anda melalui email/WhatsApp bila diperlukan.', 'Email untuk pengiriman produk', 1, 1, 14),
  ('plugin-contact-form-7', 'Plugin-Contact Form 7', 'plugin', 'Berkarya Cipta Media', 'Plugin formulir kontak fleksibel untuk WordPress, mudah dikustomisasi.', '## Fitur
- Banyak formulir dalam satu website
- Validasi & proteksi spam
- Kustomisasi email notifikasi', 550000, 'WordPress', 'Lisensi 1 website', 'manual', 'Terima kasih! Produk Anda sedang disiapkan oleh tim kami dan akan muncul di halaman ini (maks. 1×24 jam). Kami juga akan menghubungi Anda melalui email/WhatsApp bila diperlukan.', 'Email untuk pengiriman produk', 1, 0, 15),
  ('plugin-woocommerce', 'Plugin-woocommerce', 'plugin', 'INOVASI KARYA SOLUSI', 'Ubah WordPress menjadi toko online lengkap dengan katalog, keranjang, dan checkout.', '## Fitur
- Katalog produk & manajemen stok
- Keranjang & checkout
- Ekstensi pembayaran & pengiriman', 479000, 'WordPress', 'Lisensi 1 website', 'manual', 'Terima kasih! Produk Anda sedang disiapkan oleh tim kami dan akan muncul di halaman ini (maks. 1×24 jam). Kami juga akan menghubungi Anda melalui email/WhatsApp bila diperlukan.', 'Email untuk pengiriman produk', 1, 1, 16),
  ('software-adobe', 'Software-Adobe', 'software', 'Generasi Berkarya Infotek', 'Paket aplikasi kreatif Adobe untuk desain grafis, foto, dan video.', '## Termasuk
- Aplikasi desain grafis & editing foto
- Editing video
- Penyimpanan cloud', 2190000, 'Windows / macOS', 'Lisensi 1 pengguna', 'manual', 'Terima kasih! Produk Anda sedang disiapkan oleh tim kami dan akan muncul di halaman ini (maks. 1×24 jam). Kami juga akan menghubungi Anda melalui email/WhatsApp bila diperlukan.', 'Email untuk pengiriman produk', 1, 1, 17),
  ('software-java', 'Software-Java', 'software', 'Generasi Berkarya Infotek', 'Lingkungan pengembangan Java untuk membangun aplikasi desktop, web, dan enterprise.', '## Termasuk
- Lingkungan pengembangan Java
- Dukungan pembaruan
- Dokumentasi penggunaan', 1870000, 'Windows / macOS / Linux', 'Lisensi 1 pengguna', 'manual', 'Terima kasih! Produk Anda sedang disiapkan oleh tim kami dan akan muncul di halaman ini (maks. 1×24 jam). Kami juga akan menghubungi Anda melalui email/WhatsApp bila diperlukan.', 'Email untuk pengiriman produk', 1, 0, 18),
  ('software-ubuntu-linux', 'Software-Ubuntu-Linux', 'software', 'INOVASI KARYA SOLUSI', 'Paket Ubuntu Linux dengan dukungan untuk kebutuhan server dan desktop bisnis.', '## Termasuk
- Ubuntu untuk desktop & server
- Dukungan pembaruan keamanan
- Panduan instalasi', 2890000, 'PC / Server', 'Lisensi 1 perangkat', 'manual', 'Terima kasih! Produk Anda sedang disiapkan oleh tim kami dan akan muncul di halaman ini (maks. 1×24 jam). Kami juga akan menghubungi Anda melalui email/WhatsApp bila diperlukan.', 'Email untuk pengiriman produk', 1, 0, 19),
  ('website-windows-11', 'WEBSITE-windows 11', 'website-service', 'Generasi Berkarya Infotek', 'Lisensi & layanan instalasi Windows 11 untuk perangkat Anda.', '## Termasuk
- Lisensi Windows 11
- Panduan / bantuan aktivasi
- Dukungan setelah instalasi', 2280000, 'PC', 'Lisensi 1 perangkat', 'manual', 'Terima kasih! Produk Anda sedang disiapkan oleh tim kami dan akan muncul di halaman ini (maks. 1×24 jam). Kami juga akan menghubungi Anda melalui email/WhatsApp bila diperlukan.', 'Email & keterangan perangkat', 1, 0, 20)
ON DUPLICATE KEY UPDATE name=VALUES(name), category_id=VALUES(category_id), store_name=VALUES(store_name), price=VALUES(price);

-- Gambar produk (file di assets/img/)
UPDATE digital_products SET image = CASE slug
  WHEN 'software-adobe'         THEN 'assets/img/Adobe-Creative-Cloud-Collection-2025.webp'
  WHEN 'elementor'              THEN 'assets/img/elementor-pro.webp'
  WHEN 'plugin-elementor-wp'    THEN 'assets/img/plugin-elementor-1.webp'
  WHEN 'game-free-fire'         THEN 'assets/img/Free-Fire.webp'
  WHEN 'genshin-impact-game'    THEN 'assets/img/Ghensin-impact-scaled-1.webp'
  WHEN 'game-growtopia'         THEN 'assets/img/growtopia-45be-original.webp'
  WHEN 'game-honor-of-kings'    THEN 'assets/img/honor_of_king-8e04-original.webp'
  WHEN 'plugin-all-in-one-seo'  THEN 'assets/img/plugin-aioseo.webp'
  WHEN 'plugin-contact-form-7'  THEN 'assets/img/plugin-Contact-form-7-Setup.webp'
  WHEN 'plugin-woocommerce'     THEN 'assets/img/plugin-woocommerce.webp'
  WHEN 'game-pubg'              THEN 'assets/img/PUBG.webp'
  WHEN 'game-roblox'            THEN 'assets/img/Roblox.webp'
  WHEN 'software-java'          THEN 'assets/img/Software-java.webp'
  WHEN 'software-ubuntu-linux'  THEN 'assets/img/software-ubuntu-linux.webp'
  WHEN 'website-windows-11'     THEN 'assets/img/software-windows-11.webp'
  WHEN 'game-valorant'          THEN 'assets/img/Valoran.webp'
  ELSE image END
WHERE image IS NULL OR image LIKE 'assets/img/%';
