-- =========================================================
-- Migrasi 003 — Saldo akun (refund otomatis), kirim ulang pesanan, 2FA
-- Jalankan SEKALI di phpMyAdmin (setelah 002_admin.sql).
-- Instalasi baru: sudah termasuk di schema.sql.
-- =========================================================
ALTER TABLE users
  ADD COLUMN balance        INT UNSIGNED NOT NULL DEFAULT 0 AFTER phone,
  ADD COLUMN totp_secret    VARCHAR(255) NULL,
  ADD COLUMN totp_enabled   TINYINT(1)   NOT NULL DEFAULT 0,
  ADD COLUMN totp_last_step BIGINT       NULL,
  ADD COLUMN recovery_codes TEXT         NULL;

ALTER TABLE orders
  ADD COLUMN supplier_ref  VARCHAR(40)      NULL AFTER supplier,
  ADD COLUMN retry_count   TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER supplier_ref,
  ADD COLUMN refund_method VARCHAR(20)      NULL AFTER refund_status,
  ADD KEY idx_orders_supref (supplier_ref);

UPDATE orders SET supplier_ref = invoice WHERE supplier_ref IS NULL;

CREATE TABLE IF NOT EXISTS wallet_ledger (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  type          VARCHAR(20)  NOT NULL,          -- refund | payment | refund_reversal | adjust
  amount        INT          NOT NULL,          -- + masuk, − keluar
  balance_after INT UNSIGNED NOT NULL,
  ref           VARCHAR(64)  NOT NULL,          -- kunci idempoten (mis. refund:GDK...R1)
  order_id      BIGINT UNSIGNED NULL,
  note          VARCHAR(255) NULL,
  created_by    VARCHAR(190) NULL,              -- 'system' atau email admin
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ledger_ref (ref),
  KEY idx_ledger_user (user_id, id),
  CONSTRAINT fk_ledger_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
