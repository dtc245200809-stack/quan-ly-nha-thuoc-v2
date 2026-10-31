-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: nmp
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `admin`
--

DROP TABLE IF EXISTS `admin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin` (
  `admin_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `date` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`admin_id`),
  UNIQUE KEY `uq_admin_username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin`
--

LOCK TABLES `admin` WRITE;
/*!40000 ALTER TABLE `admin` DISABLE KEYS */;
INSERT INTO `admin` VALUES (1,'admin','$2y$10$kAPIfnCgYPEdn8kJAj5KvOJeXKclRh9FWPNUdeJOccMTwhYWjWydy','2026-04-07 19:09:31');
/*!40000 ALTER TABLE `admin` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `backup_log`
--

DROP TABLE IF EXISTS `backup_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_log` (
  `backup_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `file_name` varchar(120) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`backup_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `backup_log`
--

LOCK TABLES `backup_log` WRITE;
/*!40000 ALTER TABLE `backup_log` DISABLE KEYS */;
INSERT INTO `backup_log` VALUES (1,'backup-20260331-090000.sql','Bản sao lưu trước khi demo','2026-04-07 19:09:31');
/*!40000 ALTER TABLE `backup_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cashier`
--

DROP TABLE IF EXISTS `cashier`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cashier` (
  `cashier_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `staff_id` varchar(30) NOT NULL,
  `postal_address` varchar(120) NOT NULL,
  `phone` varchar(25) NOT NULL,
  `email` varchar(120) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `date` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`cashier_id`),
  UNIQUE KEY `uq_cashier_username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cashier`
--

LOCK TABLES `cashier` WRITE;
/*!40000 ALTER TABLE `cashier` DISABLE KEYS */;
INSERT INTO `cashier` VALUES (1,'Nguyễn Minh','Phương','BH-001','Thái Nguyên','0901122334','phuong@nhathuoc.vn','phuong','phuong','2026-04-07 19:09:31'),(2,'Thân Phú','Cường','BH-002','Thái Nguyên','0905566778','cuong@nhathuoc.vn','cuong','cuong','2026-04-07 19:09:31');
/*!40000 ALTER TABLE `cashier` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `combo_items`
--

DROP TABLE IF EXISTS `combo_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `combo_items` (
  `combo_item_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `combo_id` int(10) unsigned NOT NULL,
  `stock_id` int(10) unsigned NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_note` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`combo_item_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `combo_items`
--

LOCK TABLES `combo_items` WRITE;
/*!40000 ALTER TABLE `combo_items` DISABLE KEYS */;
INSERT INTO `combo_items` VALUES (1,1,1,6,'viên'),(2,1,3,3,'viên'),(3,1,6,1,'chai'),(4,2,7,10,'viên'),(5,2,8,10,'viên');
/*!40000 ALTER TABLE `combo_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `combos`
--

DROP TABLE IF EXISTS `combos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `combos` (
  `combo_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `combo_name` varchar(150) NOT NULL,
  `sale_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `target_days` int(11) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`combo_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `combos`
--

LOCK TABLES `combos` WRITE;
/*!40000 ALTER TABLE `combos` DISABLE KEYS */;
INSERT INTO `combos` VALUES (1,'Liều cảm cúm 3 ngày',165000.00,3,'Paracetamol + Vitamin C + Natri Clorid'),(2,'Combo đau dạ dày 5 ngày',228000.00,5,'Omeprazol + Alpha Chymotrypsin');
/*!40000 ALTER TABLE `combos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customers` (
  `customer_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `customer_name` varchar(120) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `loyalty_points` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`customer_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customers`
--

LOCK TABLES `customers` WRITE;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
INSERT INTO `customers` VALUES (1,'Nguyễn Văn An','0911111111',25,'2026-04-07 19:09:31'),(2,'Trần Thị Hạnh','0922222222',18,'2026-04-07 19:09:31'),(3,'Lê Thu Trang','0933333333',5,'2026-04-07 19:09:31');
/*!40000 ALTER TABLE `customers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `drugs`
--

DROP TABLE IF EXISTS `drugs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `drugs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `drug_code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `active_ingredient` varchar(255) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `sale_price` decimal(10,2) DEFAULT 0.00,
  `company` varchar(120) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `drug_code` (`drug_code`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `drugs`
--

LOCK TABLES `drugs` WRITE;
/*!40000 ALTER TABLE `drugs` DISABLE KEYS */;
INSERT INTO `drugs` VALUES (1,'D0008','Alpha Chymotrypsin','Alpha Chymotrypsin','Hộp',82000.00,'Mekophar','2026-04-07 19:08:05'),(2,'D0002','Amoxicillin 500mg','Amoxicillin','Hộp',98000.00,'Imexpharm','2026-04-07 19:08:05'),(3,'D0005','Cetirizin 10mg','Cetirizine','Hộp',72000.00,'Traphaco','2026-04-07 19:08:05'),(4,'D0006','Natri Clorid 0.9%','Natri Clorid','Chai',15000.00,'Merap','2026-04-07 19:08:05'),(5,'D0007','Omeprazol 20mg','Omeprazole','Hộp',76000.00,'Stella','2026-04-07 19:08:05'),(6,'D0004','Oresol cam','Oresol','Hộp',45000.00,'Bidiphar','2026-04-07 19:08:05'),(7,'D0001','Paracetamol 500mg','Paracetamol','Hộp',62000.00,'Dược Hậu Giang','2026-04-07 19:08:05'),(8,'D0003','Vitamin C 500mg','Acid Ascorbic','Tuýp',28000.00,'OPC','2026-04-07 19:08:05');
/*!40000 ALTER TABLE `drugs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoice`
--

DROP TABLE IF EXISTS `invoice`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoice` (
  `invoice_no` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `customer_name` varchar(120) NOT NULL,
  `drug_id` int(10) unsigned NOT NULL,
  `drug_name` varchar(120) NOT NULL,
  `quantity` int(11) NOT NULL,
  `cost` decimal(10,2) NOT NULL,
  `tax` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`invoice_no`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice`
--

LOCK TABLES `invoice` WRITE;
/*!40000 ALTER TABLE `invoice` DISABLE KEYS */;
INSERT INTO `invoice` VALUES (1,'Nguyễn Văn An',1,'Paracetamol 500mg',2,124000.00,0.00,'2026-03-31 09:15:00'),(2,'Trần Thị Hạnh',5,'Cetirizin 10mg',1,72000.00,0.00,'2026-03-31 10:30:00'),(3,'Nguyễn Văn An',1,'Liều cảm cúm 3 ngày',1,164500.00,0.00,'2026-03-31 11:20:00');
/*!40000 ALTER TABLE `invoice` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoice_header`
--

DROP TABLE IF EXISTS `invoice_header`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoice_header` (
  `invoice_no` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_code` varchar(30) NOT NULL,
  `customer_name` varchar(120) NOT NULL,
  `payment_type` varchar(50) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `customer_id` int(10) unsigned DEFAULT NULL,
  `used_points` int(11) NOT NULL DEFAULT 0,
  `note` varchar(255) DEFAULT NULL,
  `status` enum('active','cancelled') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`invoice_no`),
  UNIQUE KEY `uq_invoice_code` (`invoice_code`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice_header`
--

LOCK TABLES `invoice_header` WRITE;
/*!40000 ALTER TABLE `invoice_header` DISABLE KEYS */;
INSERT INTO `invoice_header` VALUES (1,'HD-20260331-001','Nguyễn Văn An','cash',124000.00,0.00,0.00,124000.00,1,0,NULL,'active','2026-03-31 09:15:00'),(2,'HD-20260331-002','Trần Thị Hạnh','transfer',72000.00,0.00,0.00,72000.00,2,0,NULL,'active','2026-03-31 10:30:00'),(3,'HD-20260331-003','Nguyễn Văn An','cash',165000.00,0.00,500.00,164500.00,1,5,'Bán theo combo: Liều cảm cúm 3 ngày [HỦY: ngáo]','cancelled','2026-03-31 11:20:00'),(4,'HD-20260407-004','Khách lẻ','cash',82000.00,0.00,0.00,82000.00,NULL,0,NULL,'active','2026-04-08 03:13:04'),(5,'HD-20260407-005','Khách lẻ','cash',28000.00,0.00,0.00,28000.00,NULL,0,NULL,'active','2026-04-08 03:13:35'),(6,'HD-20260407-006','Khách lẻ','cash',471000.00,0.00,0.00,471000.00,NULL,0,'Bán theo combo: Liều cảm cúm 3 ngày','active','2026-04-08 03:14:03'),(7,'HD-20260407-007','Khách lẻ','cash',1862000.00,0.00,3000.00,1859000.00,NULL,0,NULL,'active','2026-04-08 03:19:35');
/*!40000 ALTER TABLE `invoice_header` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoice_item`
--

DROP TABLE IF EXISTS `invoice_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoice_item` (
  `item_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_no` int(10) unsigned NOT NULL,
  `drug_id` int(10) unsigned NOT NULL,
  `drug_name` varchar(120) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `line_total` decimal(10,2) NOT NULL,
  PRIMARY KEY (`item_id`),
  KEY `idx_item_invoice` (`invoice_no`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice_item`
--

LOCK TABLES `invoice_item` WRITE;
/*!40000 ALTER TABLE `invoice_item` DISABLE KEYS */;
INSERT INTO `invoice_item` VALUES (1,1,1,'Paracetamol 500mg',2,62000.00,124000.00),(2,2,5,'Cetirizin 10mg',1,72000.00,72000.00),(6,4,8,'Alpha Chymotrypsin',1,82000.00,82000.00),(7,5,3,'Vitamin C 500mg',1,28000.00,28000.00),(8,6,6,'Natri Clorid 0.9%',1,15000.00,15000.00),(9,6,1,'Paracetamol 500mg',6,62000.00,372000.00),(10,6,3,'Vitamin C 500mg',3,28000.00,84000.00),(11,7,2,'Amoxicillin 500mg',19,98000.00,1862000.00);
/*!40000 ALTER TABLE `invoice_item` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loyalty_logs`
--

DROP TABLE IF EXISTS `loyalty_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `loyalty_logs` (
  `log_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` int(10) unsigned NOT NULL,
  `invoice_no` int(10) unsigned DEFAULT NULL,
  `points_delta` int(11) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loyalty_logs`
--

LOCK TABLES `loyalty_logs` WRITE;
/*!40000 ALTER TABLE `loyalty_logs` DISABLE KEYS */;
INSERT INTO `loyalty_logs` VALUES (1,1,1,12,'Tích điểm hóa đơn HD-20260331-001','2026-03-31 09:15:00'),(2,2,2,7,'Tích điểm hóa đơn HD-20260331-002','2026-03-31 10:30:00'),(3,1,3,-5,'Sử dụng 5 điểm tại hóa đơn HD-20260331-003','2026-03-31 11:20:00'),(4,1,3,16,'Tích điểm hóa đơn HD-20260331-003','2026-03-31 11:20:00'),(5,1,3,0,'HỦY HĐ HD-20260331-003: Đã hoàn điểm. Lý do: ngáo','2026-04-08 03:01:41');
/*!40000 ALTER TABLE `loyalty_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `manager`
--

DROP TABLE IF EXISTS `manager`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `manager` (
  `manager_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `staff_id` varchar(30) NOT NULL,
  `postal_address` varchar(120) NOT NULL,
  `phone` varchar(25) NOT NULL,
  `email` varchar(120) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `date` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`manager_id`),
  UNIQUE KEY `uq_manager_username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `manager`
--

LOCK TABLES `manager` WRITE;
/*!40000 ALTER TABLE `manager` DISABLE KEYS */;
INSERT INTO `manager` VALUES (1,'Giàng A','Chiến','QL-001','Thái Nguyên','0912345678','chien@nhathuoc.vn','chien','chien','2026-04-07 19:09:31'),(2,'Phùng Văn','Lộc','QL-002','Thái Nguyên','0987654321','loc@nhathuoc.vn','loc','loc','2026-04-07 19:09:31');
/*!40000 ALTER TABLE `manager` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_details`
--

DROP TABLE IF EXISTS `payment_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_details` (
  `payment_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_no` int(10) unsigned NOT NULL,
  `customer_name` varchar(120) NOT NULL,
  `payment_type` varchar(50) NOT NULL,
  `total_ammount` decimal(10,2) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`payment_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_details`
--

LOCK TABLES `payment_details` WRITE;
/*!40000 ALTER TABLE `payment_details` DISABLE KEYS */;
INSERT INTO `payment_details` VALUES (1,1,'Nguyễn Văn An','cash',124000.00,'2026-03-31 09:15:00'),(2,2,'Trần Thị Hạnh','transfer',72000.00,'2026-03-31 10:30:00'),(4,4,'Khách lẻ','cash',82000.00,'2026-04-08 03:13:04'),(5,5,'Khách lẻ','cash',28000.00,'2026-04-08 03:13:35'),(6,6,'Khách lẻ','cash',471000.00,'2026-04-08 03:14:03'),(7,7,'Khách lẻ','cash',1859000.00,'2026-04-08 03:19:35');
/*!40000 ALTER TABLE `payment_details` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pharmacist`
--

DROP TABLE IF EXISTS `pharmacist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pharmacist` (
  `pharmacist_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `staff_id` varchar(30) NOT NULL,
  `postal_address` varchar(120) NOT NULL,
  `phone` varchar(25) NOT NULL,
  `email` varchar(120) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `date` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`pharmacist_id`),
  UNIQUE KEY `uq_pharmacist_username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pharmacist`
--

LOCK TABLES `pharmacist` WRITE;
/*!40000 ALTER TABLE `pharmacist` DISABLE KEYS */;
INSERT INTO `pharmacist` VALUES (1,'Nguyễn Duy','Kiên','DS-001','Thái Nguyên','0911223344','kien@nhathuoc.vn','kien','kien','2026-04-07 19:09:31'),(2,'Dược sĩ','Trực quầy','DS-002','Thái Nguyên','0944556677','duocsi@nhathuoc.vn','duocsi','duocsi','2026-04-07 19:09:31');
/*!40000 ALTER TABLE `pharmacist` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `prescription`
--

DROP TABLE IF EXISTS `prescription`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `prescription` (
  `prescription_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `drug_id` int(10) unsigned NOT NULL,
  `drug_name` varchar(120) NOT NULL,
  `strength` varchar(50) NOT NULL,
  `dose` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL,
  PRIMARY KEY (`prescription_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prescription`
--

LOCK TABLES `prescription` WRITE;
/*!40000 ALTER TABLE `prescription` DISABLE KEYS */;
INSERT INTO `prescription` VALUES (1,2,'Amoxicillin 500mg','500mg','1 viên x 3 lần/ngày',21),(2,7,'Omeprazol 20mg','20mg','1 viên trước ăn sáng',10);
/*!40000 ALTER TABLE `prescription` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `return_header`
--

DROP TABLE IF EXISTS `return_header`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `return_header` (
  `return_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_no` int(10) unsigned NOT NULL,
  `invoice_code` varchar(30) NOT NULL,
  `customer_name` varchar(120) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `refund_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`return_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `return_header`
--

LOCK TABLES `return_header` WRITE;
/*!40000 ALTER TABLE `return_header` DISABLE KEYS */;
/*!40000 ALTER TABLE `return_header` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `return_item`
--

DROP TABLE IF EXISTS `return_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `return_item` (
  `return_item_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `return_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `drug_name` varchar(120) NOT NULL,
  `quantity` int(11) NOT NULL,
  `refund_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`return_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `return_item`
--

LOCK TABLES `return_item` WRITE;
/*!40000 ALTER TABLE `return_item` DISABLE KEYS */;
/*!40000 ALTER TABLE `return_item` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sale_batch_usage`
--

DROP TABLE IF EXISTS `sale_batch_usage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sale_batch_usage` (
  `usage_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_no` int(10) unsigned NOT NULL,
  `stock_id` int(10) unsigned NOT NULL,
  `batch_no` varchar(60) NOT NULL,
  `quantity_used` int(11) NOT NULL,
  PRIMARY KEY (`usage_id`),
  KEY `idx_usage_invoice` (`invoice_no`),
  KEY `idx_usage_stock` (`stock_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sale_batch_usage`
--

LOCK TABLES `sale_batch_usage` WRITE;
/*!40000 ALTER TABLE `sale_batch_usage` DISABLE KEYS */;
INSERT INTO `sale_batch_usage` VALUES (1,1,1,'PARA-2401',2),(2,2,5,'CETI-2405',1),(6,4,8,'ALPHA-2408',1),(7,5,3,'VITC-2403',1),(8,6,6,'NACL-2406',1),(9,6,1,'PARA-2401',6),(10,6,3,'VITC-2403',3),(11,7,2,'AMOX-2402',19);
/*!40000 ALTER TABLE `sale_batch_usage` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock`
--

DROP TABLE IF EXISTS `stock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock` (
  `stock_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `barcode` varchar(50) DEFAULT NULL,
  `drug_name` varchar(120) NOT NULL,
  `active_ingredient` varchar(120) DEFAULT NULL,
  `category` varchar(60) NOT NULL,
  `dosage_form` varchar(60) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `company` varchar(120) NOT NULL,
  `manufacturer` varchar(120) DEFAULT NULL,
  `supplier_name` varchar(120) DEFAULT NULL,
  `unit` varchar(30) DEFAULT 'Hộp',
  `retail_unit` varchar(30) DEFAULT 'Viên',
  `conversion_factor` int(11) NOT NULL DEFAULT 1,
  `batch_no` varchar(60) NOT NULL,
  `mfg_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `min_quantity` int(11) NOT NULL DEFAULT 20,
  `purchase_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `sale_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `notes` varchar(255) DEFAULT NULL,
  `date_supplied` date NOT NULL DEFAULT curdate(),
  `is_prescription` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`stock_id`),
  KEY `idx_stock_drug` (`drug_name`),
  KEY `idx_stock_expiry` (`expiry_date`),
  KEY `idx_stock_batch` (`batch_no`),
  CONSTRAINT `chk_stock_qty_non_negative` CHECK (`quantity` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock`
--

LOCK TABLES `stock` WRITE;
/*!40000 ALTER TABLE `stock` DISABLE KEYS */;
INSERT INTO `stock` VALUES (1,'8931234500012','Paracetamol 500mg','Paracetamol','Giảm đau - hạ sốt','Viên nén','Thuốc giảm đau, hạ sốt thông dụng','Dược Hậu Giang','DHG Pharma','Công ty Dược Thái Nguyên','Hộp','Viên',100,'PARA-2401','2025-10-01','2027-10-01',320,50,45000.00,62000.00,62000.00,'Dùng cho cảm sốt, đau đầu','2026-01-10',0),(2,'8931234500029','Amoxicillin 500mg','Amoxicillin','Kháng sinh','Viên nang','Kháng sinh đường uống theo chỉ định','Imexpharm','Imexpharm','Công ty Dược Việt Bắc','Hộp','Viên',100,'AMOX-2402','2025-11-12','2027-05-30',161,30,76000.00,98000.00,98000.00,'Bán theo đơn','2026-01-18',1),(3,'8931234500036','Vitamin C 500mg','Acid Ascorbic','Vitamin','Viên sủi','Hỗ trợ tăng đề kháng','OPC','OPC Pharma','Công ty Dược Thái Nguyên','Tuýp','Viên',10,'VITC-2403','2025-12-01','2026-08-15',64,20,18000.00,28000.00,28000.00,'Ưu tiên đẩy bán do còn hạn ngắn','2026-02-05',0),(4,'8931234500043','Oresol cam','Oresol','Điện giải','Gói bột','Bù nước và điện giải','Bidiphar','Bidiphar','Nhà phân phối An Khang','Hộp','Gói',30,'ORES-2404','2025-09-20','2027-02-28',140,25,32000.00,45000.00,45000.00,'Bán nhiều mùa nóng','2026-02-12',0),(5,'8931234500050','Cetirizin 10mg','Cetirizine','Dị ứng','Viên nén','Giảm triệu chứng viêm mũi dị ứng','Traphaco','Traphaco','Nhà phân phối An Khang','Hộp','Viên',100,'CETI-2405','2025-11-15','2026-06-20',40,20,52000.00,72000.00,72000.00,'Cận date cần theo dõi','2026-02-20',0),(6,'8931234500067','Natri Clorid 0.9%','Natri Clorid','Rửa mũi - nhỏ mắt','Dung dịch','Dùng vệ sinh mũi và mắt','Merap','Merap','Công ty Dược Việt Bắc','Chai','Chai',1,'NACL-2406','2025-12-10','2027-12-01',90,15,9000.00,15000.00,15000.00,'Phù hợp bán lẻ nhanh','2026-03-01',0),(7,'8931234500074','Omeprazol 20mg','Omeprazole','Dạ dày','Viên nang','Giảm tiết acid dạ dày','Stella','Stella','Dược miền núi','Hộp','Viên',100,'OMEP-2407','2025-12-20','2027-11-12',120,20,58000.00,76000.00,76000.00,'Thường bán theo combo đau dạ dày','2026-03-05',0),(8,'8931234500081','Alpha Chymotrypsin','Alpha Chymotrypsin','Kháng viêm','Viên nén','Giảm phù nề, sưng viêm','Mekophar','Mekophar','Dược miền núi','Hộp','Viên',100,'ALPHA-2408','2025-11-01','2026-05-15',49,20,65000.00,82000.00,82000.00,'Cần theo dõi hạn gần','2026-03-10',0);
/*!40000 ALTER TABLE `stock` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `suppliers`
--

DROP TABLE IF EXISTS `suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `suppliers`
--

LOCK TABLES `suppliers` WRITE;
/*!40000 ALTER TABLE `suppliers` DISABLE KEYS */;
/*!40000 ALTER TABLE `suppliers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_logs`
--

DROP TABLE IF EXISTS `system_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_logs`
--

LOCK TABLES `system_logs` WRITE;
/*!40000 ALTER TABLE `system_logs` DISABLE KEYS */;
INSERT INTO `system_logs` VALUES (1,'admin','Đăng nhập','Đăng nhập hệ thống phân hệ Admin','::1','2026-04-07 19:10:03'),(2,'admin','Đăng nhập','Đăng nhập hệ thống phân hệ Admin','::1','2026-04-07 20:00:04'),(3,'admin','Hủy hóa đơn','Hủy hóa đơn HD-20260331-003 (ID: 3). Lý do: ngáo. Đã hoàn kho 3 lô, hoàn 5 điểm.','::1','2026-04-07 20:01:41'),(4,'admin','Bán hàng','Tạo hóa đơn HD-20260407-004 cho Khách lẻ — trừ kho FEFO 1 lô','::1','2026-04-07 20:13:04'),(5,'admin','Bán hàng','Tạo hóa đơn HD-20260407-005 cho Khách lẻ — trừ kho FEFO 1 lô','::1','2026-04-07 20:13:35'),(6,'admin','Bán combo','Bán combo Liều cảm cúm 3 ngày — HĐ HD-20260407-006 cho Khách lẻ — 3 lô','::1','2026-04-07 20:14:03'),(7,'admin','Bán hàng','Tạo hóa đơn HD-20260407-007 cho Khách lẻ — trừ kho FEFO 1 lô','::1','2026-04-07 20:19:35');
/*!40000 ALTER TABLE `system_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'nmp'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-04-08  3:36:26
