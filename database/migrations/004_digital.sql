-- =========================================================
-- Migrasi 004 — Toko Produk Digital (game, plugin, software, website service, e-book)
-- Jalankan SEKALI di phpMyAdmin (setelah 003). Instalasi baru: sudah termasuk di schema.sql.
-- Setelah ini, import juga database/seed_digital.sql untuk produk contoh.
-- =========================================================
CREATE TABLE IF NOT EXISTS digital_categories (
  id         VARCHAR(30) NOT NULL PRIMARY KEY,
  name       VARCHAR(60) NOT NULL,
  icon       VARCHAR(30) NULL,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS digital_products (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug           VARCHAR(120) NOT NULL,
  name           VARCHAR(150) NOT NULL,
  category_id    VARCHAR(30)  NOT NULL,
  store_name     VARCHAR(100) NULL,                 -- nama toko/penjual yang ditampilkan
  short_desc     VARCHAR(300) NULL,
  description    TEXT         NULL,                 -- teks biasa; baris "- " jadi poin, "## " jadi subjudul
  price          INT UNSIGNED NOT NULL,
  price_strike   INT UNSIGNED NULL,                 -- harga coret (opsional)
  image          VARCHAR(255) NULL,                 -- path gambar di assets/uploads/digital/
  version        VARCHAR(40)  NULL,
  platform       VARCHAR(100) NULL,
  license_type   VARCHAR(100) NULL,
  delivery_type  ENUM('download','license','manual') NOT NULL DEFAULT 'manual',
  file_path      VARCHAR(255) NULL,                 -- file privat di storage/digital/
  file_name      VARCHAR(150) NULL,                 -- nama file saat diunduh pembeli
  file_url       VARCHAR(500) NULL,                 -- alternatif: link eksternal (Google Drive, dsb.)
  delivery_note  TEXT         NULL,                 -- instruksi yang tampil ke pembeli setelah bayar
  buyer_note_label    VARCHAR(100) NULL,            -- mis. "Email akun / domain website" (kosong = tidak ditanya)
  buyer_note_required TINYINT(1) NOT NULL DEFAULT 0,
  is_active      TINYINT(1)   NOT NULL DEFAULT 1,
  is_featured    TINYINT(1)   NOT NULL DEFAULT 0,
  sort_order     INT          NOT NULL DEFAULT 0,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_dp_slug (slug),
  KEY idx_dp_cat (category_id, is_active),
  CONSTRAINT fk_dp_cat FOREIGN KEY (category_id) REFERENCES digital_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS digital_licenses (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id  INT UNSIGNED NOT NULL,
  license_key TEXT NOT NULL,                        -- terenkripsi (app.key)
  key_hash    CHAR(64) NOT NULL,                    -- cegah kode ganda
  status      ENUM('available','sold') NOT NULL DEFAULT 'available',
  order_id    BIGINT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sold_at     DATETIME NULL,
  UNIQUE KEY uq_lic_hash (product_id, key_hash),
  KEY idx_lic_stock (product_id, status),
  CONSTRAINT fk_lic_product FOREIGN KEY (product_id) REFERENCES digital_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pesanan produk digital memakai tabel orders yang sama
ALTER TABLE orders
  MODIFY brand_id INT UNSIGNED NULL,
  ADD COLUMN kind               VARCHAR(10)  NOT NULL DEFAULT 'ppob' AFTER invoice,
  ADD COLUMN digital_product_id INT UNSIGNED NULL AFTER product_id,
  ADD COLUMN buyer_note         VARCHAR(500) NULL AFTER customer_name,
  ADD COLUMN delivery           TEXT         NULL AFTER sn,          -- terenkripsi: kode lisensi / isi pengiriman
  ADD COLUMN download_count     INT UNSIGNED NOT NULL DEFAULT 0 AFTER delivery,
  ADD KEY idx_orders_kind (kind, status);
