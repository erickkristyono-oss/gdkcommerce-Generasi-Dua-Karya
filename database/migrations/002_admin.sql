-- =========================================================
-- Migrasi 002 — Panel Admin
-- Jalankan SEKALI di phpMyAdmin bila database dibuat sebelum panel admin ada.
-- (Instalasi baru: sudah termasuk di schema.sql, tidak perlu dijalankan.)
-- =========================================================
ALTER TABLE orders
  ADD COLUMN admin_note  VARCHAR(255) NULL AFTER refund_status,
  ADD COLUMN refunded_at DATETIME     NULL AFTER admin_note;

ALTER TABLE api_logs ADD KEY idx_logs_channel (channel, created_at);
