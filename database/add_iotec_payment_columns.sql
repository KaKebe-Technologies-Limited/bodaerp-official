-- ============================================================
-- BodaERP — add_iotec_payment_columns.sql
-- Adds the columns needed for riders to pay their annual tax
-- online via ioTec Pay mobile money collection, instead of only
-- a chairperson recording a manual cash/bank/momo payment.
-- Safe to run multiple times (MariaDB 10+ / MySQL 8.0.29+).
-- ============================================================

ALTER TABLE `payments`
  ADD COLUMN IF NOT EXISTS `transaction_reference` VARCHAR(100) NULL DEFAULT NULL AFTER `receipt_number`,
  ADD COLUMN IF NOT EXISTS `iotec_transaction_id`  VARCHAR(100) NULL DEFAULT NULL AFTER `transaction_reference`,
  ADD COLUMN IF NOT EXISTS `payer_phone`           VARCHAR(30)  NULL DEFAULT NULL AFTER `iotec_transaction_id`,
  ADD INDEX IF NOT EXISTS `idx_payments_iotec_tx` (`iotec_transaction_id`);
