-- =========================================================
-- Migrasi 005 — Program Agen (harga khusus agen, pendaftaran agen, deposit saldo)
-- Jalankan SEKALI di phpMyAdmin (setelah 004_digital.sql).
-- Instalasi baru: sudah termasuk di schema.sql.
-- =========================================================
ALTER TABLE users
  MODIFY COLUMN role ENUM('customer','agent','admin') NOT NULL DEFAULT 'customer',
  ADD COLUMN agent_status      ENUM('none','pending','approved','rejected') NOT NULL DEFAULT 'none' AFTER role,
  ADD COLUMN agent_store       VARCHAR(100) NULL AFTER agent_status,
  ADD COLUMN agent_city        VARCHAR(100) NULL AFTER agent_store,
  ADD COLUMN agent_note        VARCHAR(500) NULL AFTER agent_city,
  ADD COLUMN agent_admin_note  VARCHAR(255) NULL AFTER agent_note,
  ADD COLUMN agent_applied_at  DATETIME     NULL AFTER agent_admin_note,
  ADD COLUMN agent_approved_at DATETIME     NULL AFTER agent_applied_at,
  ADD KEY idx_users_agent (agent_status);

-- Harga agen per produk. 0 = otomatis (modal + markup agen di config).
ALTER TABLE products
  ADD COLUMN price_agent INT UNSIGNED NOT NULL DEFAULT 0 AFTER price_sell;

-- Biaya admin pascabayar untuk agen. NULL = sama dengan admin_fee.
ALTER TABLE brands
  ADD COLUMN admin_fee_agent INT UNSIGNED NULL AFTER admin_fee;

-- Penanda pesanan dibuat dengan harga agen
ALTER TABLE orders
  ADD COLUMN is_agent TINYINT(1) NOT NULL DEFAULT 0 AFTER user_id;
