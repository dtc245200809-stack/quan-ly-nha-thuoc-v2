SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

DROP TABLE IF EXISTS `backup_log`;
DROP TABLE IF EXISTS `system_logs`;
DROP TABLE IF EXISTS `return_item`;
DROP TABLE IF EXISTS `return_header`;
DROP TABLE IF EXISTS `loyalty_logs`;
DROP TABLE IF EXISTS `combo_items`;
DROP TABLE IF EXISTS `combos`;
DROP TABLE IF EXISTS `customers`;
DROP TABLE IF EXISTS `sale_batch_usage`;
DROP TABLE IF EXISTS `invoice_item`;
DROP TABLE IF EXISTS `invoice_header`;
DROP TABLE IF EXISTS `payment_details`;
DROP TABLE IF EXISTS `invoice`;
DROP TABLE IF EXISTS `prescription`;
DROP TABLE IF EXISTS `stock`;
DROP TABLE IF EXISTS `drugs`;
DROP TABLE IF EXISTS `admin`;
DROP TABLE IF EXISTS `cashier`;
DROP TABLE IF EXISTS `manager`;
DROP TABLE IF EXISTS `pharmacist`;

CREATE TABLE `admin` (
  `admin_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`admin_id`),
  UNIQUE KEY `uq_admin_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Mật khẩu mặc định: admin123
INSERT INTO `admin` VALUES (1,'admin','$2y$10$R/ZjOJp2HSrQqCTEgmloq.5XHDakuJrfcw6VcU02EMWOmfTapaAjm',NOW());

CREATE TABLE `manager` (
  `manager_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `first_name` VARCHAR(50) NOT NULL,
  `last_name` VARCHAR(50) NOT NULL,
  `staff_id` VARCHAR(30) NOT NULL,
  `postal_address` VARCHAR(120) NOT NULL,
  `phone` VARCHAR(25) NOT NULL,
  `email` VARCHAR(120) NOT NULL,
  `username` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`manager_id`),
  UNIQUE KEY `uq_manager_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Mật khẩu mặc định: chien123, loc123
INSERT INTO `manager` VALUES
(1,'Giàng A','Chiến','QL-001','Thái Nguyên','0912345678','chien@nhathuoc.vn','chien','$2y$10$Ftir5Ky0ZIU7mEZ5X5Ivo..lxKdrbIZn69zjpWZN73jziBikvXM0O',NOW()),
(2,'Phùng Văn','Lộc','QL-002','Thái Nguyên','0987654321','loc@nhathuoc.vn','loc','$2y$10$SMEgXGvTgBgvpCNSp9cgBuZNJYspQYv0eLYubeL69VpaMRHfN/y8i',NOW());

CREATE TABLE `cashier` (
  `cashier_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `first_name` VARCHAR(50) NOT NULL,
  `last_name` VARCHAR(50) NOT NULL,
  `staff_id` VARCHAR(30) NOT NULL,
  `postal_address` VARCHAR(120) NOT NULL,
  `phone` VARCHAR(25) NOT NULL,
  `email` VARCHAR(120) NOT NULL,
  `username` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`cashier_id`),
  UNIQUE KEY `uq_cashier_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Mật khẩu mặc định: phuong123, cuong123
INSERT INTO `cashier` VALUES
(1,'Nguyễn Minh','Phương','BH-001','Thái Nguyên','0901122334','phuong@nhathuoc.vn','phuong','$2y$10$NGWRc/0dUVHJNnIuRL1U3eqN.6d3WvJGzOBJjWOra3xRGCUKzQn7G',NOW()),
(2,'Thân Phú','Cường','BH-002','Thái Nguyên','0905566778','cuong@nhathuoc.vn','cuong','$2y$10$aLLpNDwbrdd91JdHUBWeJe6L7406G/eweErwT/k2NhfuaTQAMqd5a',NOW());

CREATE TABLE `pharmacist` (
  `pharmacist_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `first_name` VARCHAR(50) NOT NULL,
  `last_name` VARCHAR(50) NOT NULL,
  `staff_id` VARCHAR(30) NOT NULL,
  `postal_address` VARCHAR(120) NOT NULL,
  `phone` VARCHAR(25) NOT NULL,
  `email` VARCHAR(120) NOT NULL,
  `username` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`pharmacist_id`),
  UNIQUE KEY `uq_pharmacist_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Mật khẩu mặc định: kien123, duocsi123
INSERT INTO `pharmacist` VALUES
(1,'Nguyễn Duy','Kiên','DS-001','Thái Nguyên','0911223344','kien@nhathuoc.vn','kien','$2y$10$nIjD6iaK7t7F0aR00VRCWeEoo2.Bha2lrKy7Wvbk1O4ehWzP/j2Xy',NOW()),
(2,'Dược sĩ','Trực quầy','DS-002','Thái Nguyên','0944556677','duocsi@nhathuoc.vn','duocsi','$2y$10$FJgwZ/80bMcpTsg30RnvEO9zy.uSKNnXvhO5K27xXkBzcBo8vPJtu',NOW());

CREATE TABLE `customers` (
  `customer_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_name` VARCHAR(120) NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `loyalty_points` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `customers` VALUES
(1,'Nguyễn Văn An','0911111111',36,NOW()),
(2,'Trần Thị Hạnh','0922222222',18,NOW()),
(3,'Lê Thu Trang','0933333333',5,NOW());

CREATE TABLE `drugs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `drug_code` VARCHAR(50) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `active_ingredient` VARCHAR(255) DEFAULT NULL,
  `category` VARCHAR(100) DEFAULT NULL,
  `dosage_form` VARCHAR(100) DEFAULT NULL,
  `unit` VARCHAR(50) DEFAULT 'Hộp',
  `retail_unit` VARCHAR(50) DEFAULT 'Viên',
  `conversion_factor` INT NOT NULL DEFAULT 1,
  `sale_price` DECIMAL(10,2) DEFAULT 0.00,
  `company` VARCHAR(120) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_drug_code` (`drug_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `drugs` (`id`, `drug_code`, `name`, `active_ingredient`, `category`, `unit`, `sale_price`, `company`) VALUES
(1, 'PARA01', 'Paracetamol 500mg', 'Paracetamol', 'Giảm đau - hạ sốt', 'Hộp', 62000, 'Dược Hậu Giang'),
(2, 'AMOX01', 'Amoxicillin 500mg', 'Amoxicillin', 'Kháng sinh', 'Hộp', 98000, 'Imexpharm'),
(3, 'VITC01', 'Vitamin C 500mg', 'Acid Ascorbic', 'Vitamin', 'Tuýp', 28000, 'OPC'),
(4, 'ORES01', 'Oresol cam', 'Oresol', 'Điện giải', 'Hộp', 45000, 'Bidiphar'),
(5, 'CETI01', 'Cetirizin 10mg', 'Cetirizine', 'Dị ứng', 'Hộp', 72000, 'Traphaco'),
(6, 'NACL01', 'Natri Clorid 0.9%', 'Natri Clorid', 'Rửa mũi - nhỏ mắt', 'Chai', 15000, 'Merap'),
(7, 'OMEP01', 'Omeprazol 20mg', 'Omeprazole', 'Dạ dày', 'Hộp', 76000, 'Stella'),
(8, 'ALPH01', 'Alpha Chymotrypsin', 'Alpha Chymotrypsin', 'Kháng viêm', 'Hộp', 82000, 'Mekophar');

CREATE TABLE `stock` (
  `stock_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `drug_id` INT UNSIGNED DEFAULT NULL,
  `barcode` VARCHAR(50) DEFAULT NULL,
  `drug_name` VARCHAR(120) DEFAULT NULL,
  `batch_no` VARCHAR(60) NOT NULL,
  `mfg_date` DATE DEFAULT NULL,
  `expiry_date` DATE DEFAULT NULL,
  `quantity` INT NOT NULL DEFAULT 0,
  `min_quantity` INT NOT NULL DEFAULT 20,
  `purchase_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `sale_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `notes` varchar(255) DEFAULT NULL,
  `date_supplied` date NOT NULL,
  PRIMARY KEY (`stock_id`),
  KEY `idx_stock_drug` (`drug_id`),
  KEY `idx_stock_expiry` (`expiry_date`),
  KEY `idx_stock_batch` (`batch_no`),
  CONSTRAINT `fk_stock_drug` FOREIGN KEY (`drug_id`) REFERENCES `drugs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `stock` (`stock_id`, `drug_id`, `drug_name`, `batch_no`, `expiry_date`, `quantity`, `purchase_price`, `sale_price`, `date_supplied`) VALUES
(1, 1, 'Paracetamol 500mg', 'PARA-2401', '2027-10-01', 320, 45000, 62000, '2026-01-10'),
(2, 2, 'Amoxicillin 500mg', 'AMOX-2402', '2027-05-30', 180, 76000, 98000, '2026-01-18'),
(3, 3, 'Vitamin C 500mg', 'VITC-2403', '2026-08-15', 65, 18000, 28000, '2026-02-05'),
(4, 4, 'Oresol cam', 'ORES-2404', '2027-02-28', 140, 32000, 45000, '2026-02-12'),
(5, 5, 'Cetirizin 10mg', 'CETI-2405', '2026-06-20', 40, 52000, 72000, '2026-02-20'),
(6, 6, 'Natri Clorid 0.9%', 'NACL-2406', '2027-12-01', 90, 9000, 15000, '2026-03-01'),
(7, 7, 'Omeprazol 20mg', 'OMEP-2407', '2027-11-12', 120, 58000, 76000, '2026-03-05'),
(8, 8, 'Alpha Chymotrypsin', 'ALPHA-2408', '2026-05-15', 50, 65000, 82000, '2026-03-10');

CREATE TABLE `combos` (
  `combo_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `combo_name` VARCHAR(150) NOT NULL,
  `sale_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `target_days` INT DEFAULT NULL,
  `note` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`combo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `combos` VALUES
(1,'Liều cảm cúm 3 ngày',165000,3,'Paracetamol + Vitamin C + Natri Clorid'),
(2,'Combo đau dạ dày 5 ngày',228000,5,'Omeprazol + Alpha Chymotrypsin');

CREATE TABLE `combo_items` (
  `combo_item_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `combo_id` INT UNSIGNED NOT NULL,
  `stock_id` INT UNSIGNED NOT NULL,
  `quantity` INT NOT NULL,
  `unit_note` VARCHAR(50) DEFAULT NULL,
  PRIMARY KEY (`combo_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `combo_items` VALUES
(1,1,1,6,'viên'),
(2,1,3,3,'viên'),
(3,1,6,1,'chai'),
(4,2,7,10,'viên'),
(5,2,8,10,'viên');

CREATE TABLE `prescription` (
  `prescription_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `drug_id` INT UNSIGNED NOT NULL,
  `drug_name` VARCHAR(120) NOT NULL,
  `strength` VARCHAR(50) NOT NULL,
  `dose` VARCHAR(50) NOT NULL,
  `quantity` INT NOT NULL,
  PRIMARY KEY (`prescription_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `prescription` VALUES
(1,2,'Amoxicillin 500mg','500mg','1 viên x 3 lần/ngày',21),
(2,7,'Omeprazol 20mg','20mg','1 viên trước ăn sáng',10);

CREATE TABLE `invoice_header` (
  `invoice_no` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_code` VARCHAR(30) NOT NULL,
  `customer_name` VARCHAR(120) NOT NULL,
  `payment_type` VARCHAR(50) NOT NULL,
  `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `tax` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `grand_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `customer_id` INT UNSIGNED DEFAULT NULL,
  `used_points` INT NOT NULL DEFAULT 0,
  `note` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('active','cancelled') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`invoice_no`),
  UNIQUE KEY `uq_invoice_code` (`invoice_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `invoice_item` (
  `item_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_no` INT UNSIGNED NOT NULL,
  `drug_id` INT UNSIGNED NOT NULL,
  `drug_name` VARCHAR(120) NOT NULL,
  `quantity` INT NOT NULL,
  `unit_price` DECIMAL(10,2) NOT NULL,
  `line_total` DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`item_id`),
  KEY `idx_item_invoice` (`invoice_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sale_batch_usage` (
  `usage_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_no` INT UNSIGNED NOT NULL,
  `stock_id` INT UNSIGNED NOT NULL,
  `batch_no` VARCHAR(60) NOT NULL,
  `quantity_used` INT NOT NULL,
  PRIMARY KEY (`usage_id`),
  KEY `idx_usage_invoice` (`invoice_no`),
  KEY `idx_usage_stock` (`stock_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `invoice` (
  `invoice_no` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_name` VARCHAR(120) NOT NULL,
  `drug_id` INT UNSIGNED NOT NULL,
  `drug_name` VARCHAR(120) NOT NULL,
  `quantity` INT NOT NULL,
  `cost` DECIMAL(10,2) NOT NULL,
  `tax` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`invoice_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `payment_details` (
  `payment_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_no` INT UNSIGNED NOT NULL,
  `customer_name` VARCHAR(120) NOT NULL,
  `payment_type` VARCHAR(50) NOT NULL,
  `total_ammount` DECIMAL(10,2) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`payment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `loyalty_logs` (
  `log_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` INT UNSIGNED NOT NULL,
  `invoice_no` INT UNSIGNED DEFAULT NULL,
  `points_delta` INT NOT NULL,
  `note` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `return_header` (
  `return_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_no` INT UNSIGNED NOT NULL,
  `invoice_code` VARCHAR(30) NOT NULL,
  `customer_name` VARCHAR(120) NOT NULL,
  `reason` VARCHAR(255) DEFAULT NULL,
  `refund_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`return_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `return_item` (
  `return_item_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `return_id` INT UNSIGNED NOT NULL,
  `item_id` INT UNSIGNED NOT NULL,
  `drug_name` VARCHAR(120) NOT NULL,
  `quantity` INT NOT NULL,
  `refund_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`return_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `backup_log` (
  `backup_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `file_name` VARCHAR(120) NOT NULL,
  `note` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`backup_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `system_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `action` VARCHAR(120) NOT NULL,
  `details` VARCHAR(255) DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `invoice_header`
(`invoice_no`, `invoice_code`, `customer_name`, `payment_type`,
 `subtotal`, `tax`, `discount`, `grand_total`,
 `customer_id`, `used_points`, `note`, `status`, `created_at`)
VALUES
(1,'HD-20260331-001','Nguyễn Văn An','cash',124000,0,0,124000,1,0,NULL,'active','2026-03-31 09:15:00'),
(2,'HD-20260331-002','Trần Thị Hạnh','transfer',72000,0,0,72000,2,0,NULL,'active','2026-03-31 10:30:00'),
(3,'HD-20260331-003','Nguyễn Văn An','cash',165000,0,500,164500,1,5,'Bán theo combo: Liều cảm cúm 3 ngày','active','2026-03-31 11:20:00');
INSERT INTO `invoice_item` VALUES
(1,1,1,'Paracetamol 500mg',2,62000,124000),
(2,2,5,'Cetirizin 10mg',1,72000,72000),
(3,3,1,'Paracetamol 500mg',6,62000,372000),
(4,3,3,'Vitamin C 500mg',3,28000,84000),
(5,3,6,'Natri Clorid 0.9%',1,15000,15000);
INSERT INTO `sale_batch_usage` VALUES
(1,1,1,'PARA-2401',2),
(2,2,5,'CETI-2405',1),
(3,3,1,'PARA-2401',6),
(4,3,3,'VITC-2403',3),
(5,3,6,'NACL-2406',1);
INSERT INTO `invoice` VALUES
(1,'Nguyễn Văn An',1,'Paracetamol 500mg',2,124000,0,'2026-03-31 09:15:00'),
(2,'Trần Thị Hạnh',5,'Cetirizin 10mg',1,72000,0,'2026-03-31 10:30:00'),
(3,'Nguyễn Văn An',1,'Liều cảm cúm 3 ngày',1,164500,0,'2026-03-31 11:20:00');
INSERT INTO `payment_details` VALUES
(1,1,'Nguyễn Văn An','cash',124000,'2026-03-31 09:15:00'),
(2,2,'Trần Thị Hạnh','transfer',72000,'2026-03-31 10:30:00'),
(3,3,'Nguyễn Văn An','cash',164500,'2026-03-31 11:20:00');
INSERT INTO `loyalty_logs` VALUES
(1,1,1,12,'Tích điểm hóa đơn HD-20260331-001','2026-03-31 09:15:00'),
(2,2,2,7,'Tích điểm hóa đơn HD-20260331-002','2026-03-31 10:30:00'),
(3,1,3,-5,'Sử dụng 5 điểm tại hóa đơn HD-20260331-003','2026-03-31 11:20:00'),
(4,1,3,16,'Tích điểm hóa đơn HD-20260331-003','2026-03-31 11:20:00');
INSERT INTO `backup_log` VALUES
(1,'backup-20260331-090000.sql','Bản sao lưu trước khi demo',NOW());

COMMIT;

