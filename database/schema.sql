-- =========================================================
-- GDK Commerce — Skema Database (MySQL 5.7+ / MariaDB 10.3+)
-- Import lewat phpMyAdmin (hPanel → Databases → phpMyAdmin → Import)
-- Setelah ini, import juga database/seed.sql
-- =========================================================
SET NAMES utf8mb4;
SET time_zone = '+07:00';

-- ---------- Pengguna ----------
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100)  NOT NULL,
  email         VARCHAR(190)  NOT NULL,
  phone         VARCHAR(20)   NOT NULL,
  balance       INT UNSIGNED  NOT NULL DEFAULT 0,   -- Saldo GDK (refund otomatis, bisa dipakai bayar)
  password_hash VARCHAR(255)  NOT NULL,
  role          ENUM('customer','agent','admin') NOT NULL DEFAULT 'customer',
  -- Program agen: pengajuan → disetujui admin (role = 'agent')
  agent_status      ENUM('none','pending','approved','rejected') NOT NULL DEFAULT 'none',
  agent_store       VARCHAR(100) NULL,               -- nama toko / konter agen
  agent_city        VARCHAR(100) NULL,
  agent_note        VARCHAR(500) NULL,               -- keterangan dari pemohon
  agent_admin_note  VARCHAR(255) NULL,               -- alasan ditolak / dicabut
  agent_applied_at  DATETIME     NULL,
  agent_approved_at DATETIME     NULL,
  is_active     TINYINT(1)    NOT NULL DEFAULT 1,
  last_login_at DATETIME      NULL,
  totp_secret    VARCHAR(255) NULL,                 -- secret 2FA (terenkripsi)
  totp_enabled   TINYINT(1)   NOT NULL DEFAULT 0,
  totp_last_step BIGINT       NULL,                 -- anti pemakaian ulang kode
  recovery_codes TEXT         NULL,                 -- hash kode cadangan (JSON)
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_agent (agent_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pembatasan percobaan login / daftar (anti brute force)
CREATE TABLE IF NOT EXISTS rate_limits (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bucket     VARCHAR(64)  NOT NULL,           -- contoh: login:ip, login:email, register:ip
  ident      VARCHAR(190) NOT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_rl (bucket, ident, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Katalog ----------
CREATE TABLE IF NOT EXISTS categories (
  id         VARCHAR(30)  NOT NULL PRIMARY KEY,   -- pulsa, data, pln, game, ewallet, tagihan, streaming
  name       VARCHAR(60)  NOT NULL,
  description VARCHAR(120) NULL,
  icon       VARCHAR(30)  NULL,
  sort_order INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Brand = "kartu produk" di website (mis. Pulsa Telkomsel, Mobile Legends)
CREATE TABLE IF NOT EXISTS brands (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug          VARCHAR(60)  NOT NULL,
  name          VARCHAR(100) NOT NULL,
  category_id   VARCHAR(30)  NOT NULL,
  type          ENUM('prepaid','postpaid') NOT NULL DEFAULT 'prepaid',
  color         VARCHAR(9)   NOT NULL DEFAULT '#0084ff',
  mark          VARCHAR(5)   NOT NULL,
  is_hot        TINYINT(1)   NOT NULL DEFAULT 0,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order    INT          NOT NULL DEFAULT 0,
  -- Validasi input tujuan (regex JS & PHP kompatibel)
  input_label   VARCHAR(60)  NOT NULL,
  input_hint    VARCHAR(120) NOT NULL,
  input_pattern VARCHAR(160) NOT NULL,
  input_error   VARCHAR(120) NOT NULL,
  normalize     ENUM('none','digits') NOT NULL DEFAULT 'digits',  -- 'digits' = buang spasi/kurung (mis. ML "123 (456)" → "123456")
  check_name    TINYINT(1)   NOT NULL DEFAULT 0,                   -- 1 = cek nama pelanggan PLN prabayar
  admin_fee     INT UNSIGNED NOT NULL DEFAULT 0,                   -- biaya admin TAMBAHAN dari kita (pascabayar)
  admin_fee_agent INT UNSIGNED NULL,                               -- biaya admin untuk agen (NULL = sama dengan admin_fee)
  -- Pemetaan ke price list Digiflazz (cocokkan, tidak case-sensitive)
  df_category   VARCHAR(60)  NULL,
  df_brand      VARCHAR(60)  NULL,
  UNIQUE KEY uq_brands_slug (slug),
  KEY idx_brands_cat (category_id),
  CONSTRAINT fk_brands_cat FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Produk = SKU yang dijual (nominal)
CREATE TABLE IF NOT EXISTS products (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  brand_id     INT UNSIGNED NOT NULL,
  sku          VARCHAR(60)  NOT NULL,              -- buyer_sku_code Digiflazz
  name         VARCHAR(150) NOT NULL,
  price_modal  INT UNSIGNED NOT NULL DEFAULT 0,    -- harga beli dari supplier
  price_sell   INT UNSIGNED NOT NULL DEFAULT 0,    -- harga jual ke pelanggan
  price_agent  INT UNSIGNED NOT NULL DEFAULT 0,    -- harga agen (0 = otomatis: modal + markup agen)
  is_active    TINYINT(1)   NOT NULL DEFAULT 1,
  supplier_ok  TINYINT(1)   NOT NULL DEFAULT 1,    -- status dari supplier (gangguan = 0)
  price_locked TINYINT(1)   NOT NULL DEFAULT 0,    -- 1 = harga jual tidak ditimpa sync otomatis
  sort_order   INT          NOT NULL DEFAULT 0,
  synced_at    DATETIME     NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_products_sku (sku),
  KEY idx_products_brand (brand_id, is_active),
  CONSTRAINT fk_products_brand FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Produk Digital ----------
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

-- ---------- Pesanan ----------
-- status: inquiry → unpaid → processing → success | failed ; unpaid → expired
CREATE TABLE IF NOT EXISTS orders (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice          VARCHAR(32)  NOT NULL,           -- juga dipakai sebagai ref_id ke Digiflazz
  kind             VARCHAR(10)  NOT NULL DEFAULT 'ppob', -- ppob | digital | deposit
  user_id          INT UNSIGNED NOT NULL,
  is_agent         TINYINT(1)   NOT NULL DEFAULT 0,  -- 1 = dibeli dengan harga agen
  brand_id         INT UNSIGNED NULL,               -- NULL untuk produk digital
  product_id       INT UNSIGNED NULL,
  digital_product_id INT UNSIGNED NULL,
  sku              VARCHAR(60)  NOT NULL,
  product_name     VARCHAR(150) NOT NULL,
  brand_name       VARCHAR(100) NOT NULL,
  customer_no      VARCHAR(64)  NOT NULL,
  customer_name    VARCHAR(120) NULL,               -- hasil inquiry (pascabayar / PLN)
  buyer_note       VARCHAR(500) NULL,               -- catatan pembeli (produk digital)
  bill_info        TEXT         NULL,               -- JSON detail tagihan (pascabayar)
  type             ENUM('prepaid','postpaid') NOT NULL,
  price            INT UNSIGNED NOT NULL,           -- harga produk / tagihan + admin
  fee              INT UNSIGNED NOT NULL DEFAULT 0, -- biaya metode pembayaran
  total            INT UNSIGNED NOT NULL DEFAULT 0,
  supplier_price   INT UNSIGNED NULL,               -- harga dipotong supplier
  status           ENUM('inquiry','unpaid','processing','success','failed','expired') NOT NULL DEFAULT 'unpaid',
  -- Pembayaran
  pay_provider     VARCHAR(20)  NULL,
  pay_method       VARCHAR(30)  NULL,
  pay_method_name  VARCHAR(60)  NULL,
  pay_reference    VARCHAR(64)  NULL,
  pay_code         VARCHAR(64)  NULL,               -- nomor VA
  pay_qr           TEXT         NULL,               -- string QRIS
  pay_url          VARCHAR(255) NULL,
  pay_expired_at   DATETIME     NULL,
  paid_at          DATETIME     NULL,
  -- Supplier
  supplier         VARCHAR(20)  NULL,
  supplier_ref     VARCHAR(40)  NULL,               -- ref_id ke supplier (= invoice, atau invoice+R1.. saat kirim ulang)
  retry_count      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  supplier_status  VARCHAR(20)  NULL,               -- Pending / Sukses / Gagal
  supplier_rc      VARCHAR(10)  NULL,
  supplier_message VARCHAR(255) NULL,
  sn               VARCHAR(255) NULL,               -- serial number / token / kode voucher
  delivery         TEXT         NULL,               -- produk digital: isi pengiriman (terenkripsi)
  download_count   INT UNSIGNED NOT NULL DEFAULT 0,
  last_checked_at  DATETIME     NULL,
  refund_status    ENUM('none','pending','done') NOT NULL DEFAULT 'none',
  refund_method    VARCHAR(20)  NULL,               -- saldo | manual
  admin_note       VARCHAR(255) NULL,
  refunded_at      DATETIME     NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  completed_at     DATETIME     NULL,
  UNIQUE KEY uq_orders_invoice (invoice),
  KEY idx_orders_user (user_id, created_at),
  KEY idx_orders_status (status, updated_at),
  KEY idx_orders_payref (pay_reference),
  KEY idx_orders_supref (supplier_ref),
  KEY idx_orders_kind (kind, status),
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_orders_brand FOREIGN KEY (brand_id) REFERENCES brands(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mutasi Saldo GDK
CREATE TABLE IF NOT EXISTS wallet_ledger (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  type          VARCHAR(20)  NOT NULL,          -- refund | payment | refund_reversal | adjust | deposit
  amount        INT          NOT NULL,          -- + masuk, − keluar
  balance_after INT UNSIGNED NOT NULL,
  ref           VARCHAR(64)  NOT NULL,          -- kunci idempoten
  order_id      BIGINT UNSIGNED NULL,
  note          VARCHAR(255) NULL,
  created_by    VARCHAR(190) NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ledger_ref (ref),
  KEY idx_ledger_user (user_id, id),
  CONSTRAINT fk_ledger_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Log komunikasi ke supplier & payment gateway (audit / debugging)
CREATE TABLE IF NOT EXISTS api_logs (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  channel    VARCHAR(20)  NOT NULL,     -- digiflazz, mock, payment, webhook
  action     VARCHAR(40)  NOT NULL,
  order_id   BIGINT UNSIGNED NULL,
  request    MEDIUMTEXT   NULL,
  response   MEDIUMTEXT   NULL,
  http_code  SMALLINT     NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_logs_order (order_id),
  KEY idx_logs_created (created_at),
  KEY idx_logs_channel (channel, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
