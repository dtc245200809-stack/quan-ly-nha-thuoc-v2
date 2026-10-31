-- ============================================================
-- MIGRATION V2 — Nâng cấp Hệ thống Quản lý Nhà thuốc (Group 10)
-- Chạy SAU khi đã import pms.sql. Idempotent — chạy lại an toàn.
-- ============================================================
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET NAMES utf8mb4;

-- ── 1. Bổ sung cột cho bảng `drugs` ──
ALTER TABLE `drugs`
  ADD COLUMN IF NOT EXISTS `category` VARCHAR(100) DEFAULT NULL AFTER `active_ingredient`,
  ADD COLUMN IF NOT EXISTS `dosage_form` VARCHAR(100) DEFAULT NULL AFTER `category`,
  ADD COLUMN IF NOT EXISTS `retail_unit` VARCHAR(50) DEFAULT 'Viên' AFTER `unit`,
  ADD COLUMN IF NOT EXISTS `conversion_factor` INT NOT NULL DEFAULT 1 AFTER `retail_unit`,
  ADD COLUMN IF NOT EXISTS `strength` VARCHAR(100) DEFAULT NULL AFTER `conversion_factor`,
  ADD COLUMN IF NOT EXISTS `purchase_price` DECIMAL(10,2) DEFAULT 0.00 AFTER `sale_price`,
  ADD COLUMN IF NOT EXISTS `min_stock` INT NOT NULL DEFAULT 20 AFTER `purchase_price`,
  ADD COLUMN IF NOT EXISTS `description` TEXT DEFAULT NULL AFTER `min_stock`,
  ADD COLUMN IF NOT EXISTS `image_path` VARCHAR(255) DEFAULT NULL AFTER `description`;

-- ── 2. Bổ sung cột cho bảng `admin` ──
ALTER TABLE `admin`
  ADD COLUMN IF NOT EXISTS `first_name` VARCHAR(50) DEFAULT '' AFTER `password`,
  ADD COLUMN IF NOT EXISTS `last_name` VARCHAR(50) DEFAULT '' AFTER `first_name`,
  ADD COLUMN IF NOT EXISTS `is_locked` TINYINT(1) NOT NULL DEFAULT 0 AFTER `last_name`;

-- ── 3. Bổ sung cột khóa tài khoản cho các bảng user ──
ALTER TABLE `manager`
  ADD COLUMN IF NOT EXISTS `is_locked` TINYINT(1) NOT NULL DEFAULT 0 AFTER `password`;
ALTER TABLE `cashier`
  ADD COLUMN IF NOT EXISTS `is_locked` TINYINT(1) NOT NULL DEFAULT 0 AFTER `password`;
ALTER TABLE `pharmacist`
  ADD COLUMN IF NOT EXISTS `is_locked` TINYINT(1) NOT NULL DEFAULT 0 AFTER `password`;

-- ── 4. Bổ sung các cột stock thiếu ──
ALTER TABLE `stock`
  ADD COLUMN IF NOT EXISTS `active_ingredient` VARCHAR(255) DEFAULT NULL AFTER `drug_name`,
  ADD COLUMN IF NOT EXISTS `category` VARCHAR(100) DEFAULT NULL AFTER `active_ingredient`,
  ADD COLUMN IF NOT EXISTS `company` VARCHAR(120) DEFAULT NULL AFTER `category`,
  ADD COLUMN IF NOT EXISTS `supplier_name` VARCHAR(120) DEFAULT NULL AFTER `company`,
  ADD COLUMN IF NOT EXISTS `unit` VARCHAR(50) DEFAULT 'Hộp' AFTER `supplier_name`,
  ADD COLUMN IF NOT EXISTS `retail_unit` VARCHAR(50) DEFAULT 'Viên' AFTER `unit`,
  ADD COLUMN IF NOT EXISTS `conversion_factor` INT NOT NULL DEFAULT 1 AFTER `retail_unit`,
  ADD COLUMN IF NOT EXISTS `is_prescription` TINYINT(1) NOT NULL DEFAULT 0 AFTER `sale_price`;

-- ── 5. Bảng phiếu nhập kho (Purchase Order) ──
CREATE TABLE IF NOT EXISTS `purchase_header` (
  `purchase_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `purchase_code` VARCHAR(30) NOT NULL,
  `supplier_id` INT UNSIGNED DEFAULT NULL,
  `supplier_name` VARCHAR(150) NOT NULL,
  `document_no` VARCHAR(100) DEFAULT NULL COMMENT 'Số chứng từ / hóa đơn nguồn',
  `note` TEXT DEFAULT NULL,
  `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `created_by` VARCHAR(50) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`purchase_id`),
  UNIQUE KEY `uq_purchase_code` (`purchase_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchase_item` (
  `item_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `purchase_id` INT UNSIGNED NOT NULL,
  `drug_id` INT UNSIGNED NOT NULL,
  `drug_name` VARCHAR(150) NOT NULL,
  `batch_no` VARCHAR(60) NOT NULL,
  `mfg_date` DATE DEFAULT NULL,
  `expiry_date` DATE NOT NULL,
  `quantity` INT NOT NULL,
  `purchase_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `sale_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `line_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`item_id`),
  KEY `idx_pi_purchase` (`purchase_id`),
  KEY `idx_pi_drug` (`drug_id`),
  CONSTRAINT `fk_pi_purchase` FOREIGN KEY (`purchase_id`) REFERENCES `purchase_header` (`purchase_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 6. Bảng cảnh báo có trạng thái ──
CREATE TABLE IF NOT EXISTS `canh_bao` (
  `alert_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `alert_type` ENUM('low_stock','near_expiry','expired') NOT NULL,
  `drug_name` VARCHAR(150) DEFAULT NULL,
  `batch_no` VARCHAR(60) DEFAULT NULL,
  `stock_id` INT UNSIGNED DEFAULT NULL,
  `message` VARCHAR(255) NOT NULL,
  `status` ENUM('pending','resolved') NOT NULL DEFAULT 'pending',
  `resolved_by` VARCHAR(50) DEFAULT NULL,
  `resolved_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`alert_id`),
  KEY `idx_cb_status` (`status`),
  KEY `idx_cb_type` (`alert_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 7. Index bổ sung cho hiệu suất ──
-- Nếu đã có index thì suppress lỗi bằng IF NOT EXISTS (MySQL 8.0.29+)
-- Với phiên bản cũ hơn thì bỏ qua (các truy vấn vẫn chạy, chỉ chậm hơn)
