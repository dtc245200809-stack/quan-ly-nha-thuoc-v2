<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

/*
 |--------------------------------------------------------------------------
 | Database configuration
 |--------------------------------------------------------------------------
 | Update these 4 values before deploying.
 | InfinityFree example:
 |   DB_HOST = 'sql123.infinityfree.com' or 'sql123.epizy.com'
 |   DB_NAME = 'if0_12345678_pms'
 |   DB_USER = 'if0_12345678'
 |   DB_PASS = 'your_password'
 */
if (!defined('DB_HOST'))
    define('DB_HOST', getenv('PMS_DB_HOST') ?: 'localhost');
if (!defined('DB_NAME'))
    define('DB_NAME', getenv('PMS_DB_NAME') ?: 'npm2006');
if (!defined('DB_USER'))
    define('DB_USER', getenv('PMS_DB_USER') ?: 'root');
if (!defined('DB_PASS'))
    define('DB_PASS', getenv('PMS_DB_PASS') ?: '');

$GLOBALS['___pms_db'] = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$GLOBALS['___pms_db']) {
    die('Database connection failed. Please check connect_db.php settings. Error: ' . mysqli_connect_error());
}

mysqli_set_charset($GLOBALS['___pms_db'], 'utf8mb4');

// -------------------------------------------------------------------------
// Backward compatibility layer for the old mysql_* API used throughout the
// project. This lets the legacy pages run on modern PHP 8+ hosting.
// -------------------------------------------------------------------------
if (!function_exists('mysql_connect')) {
    function mysql_connect($host = null, $user = null, $pass = null)
    {
        return mysqli_connect($host ?: DB_HOST, $user ?: DB_USER, $pass ?: DB_PASS, DB_NAME);
    }
}

if (!function_exists('mysql_pconnect')) {
    function mysql_pconnect($host = null, $user = null, $pass = null)
    {
        return mysql_connect($host, $user, $pass);
    }
}

if (!function_exists('mysql_select_db')) {
    function mysql_select_db($database_name, $link_identifier = null)
    {
        $link = $link_identifier ?: $GLOBALS['___pms_db'];
        return mysqli_select_db($link, $database_name);
    }
}

if (!function_exists('mysql_query')) {
    function mysql_query($query, $link_identifier = null)
    {
        $link = $link_identifier ?: $GLOBALS['___pms_db'];
        return mysqli_query($link, $query);
    }
}

if (!function_exists('mysql_fetch_array')) {
    function mysql_fetch_array($result, $result_type = MYSQLI_BOTH)
    {
        return mysqli_fetch_array($result, $result_type);
    }
}

if (!function_exists('mysql_fetch_assoc')) {
    function mysql_fetch_assoc($result)
    {
        return mysqli_fetch_assoc($result);
    }
}

if (!function_exists('mysql_fetch_row')) {
    function mysql_fetch_row($result)
    {
        return mysqli_fetch_row($result);
    }
}

if (!function_exists('mysql_error')) {
    function mysql_error($link_identifier = null)
    {
        $link = $link_identifier ?: $GLOBALS['___pms_db'];
        return mysqli_error($link);
    }
}

if (!function_exists('mysql_close')) {
    function mysql_close($link_identifier = null)
    {
        $link = $link_identifier ?: $GLOBALS['___pms_db'];
        return mysqli_close($link);
    }
}

if (!function_exists('pms_escape')) {
    function pms_escape($value)
    {
        return mysqli_real_escape_string($GLOBALS['___pms_db'], trim((string) $value));
    }
}

$con = $GLOBALS['___pms_db'];

// Auto-create newly requested tables
mysqli_query($con, "CREATE TABLE IF NOT EXISTS drugs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    drug_code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    active_ingredient VARCHAR(255),
    unit VARCHAR(50),
    sale_price DECIMAL(10,2) DEFAULT 0.00,
    company VARCHAR(120),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Migrate existing drugs from stock if drugs table is empty
$countDrugs = @mysqli_query($con, "SELECT COUNT(*) as c FROM drugs");
if ($countDrugs) {
    $rowDrugs = mysqli_fetch_assoc($countDrugs);
    if ($rowDrugs['c'] == 0) {
        mysqli_query($con, "INSERT IGNORE INTO drugs (drug_code, name, active_ingredient, unit, sale_price, company) SELECT CONCAT('D', LPAD(MIN(stock_id), 4, '0')), drug_name, MAX(active_ingredient), MAX(unit), MAX(sale_price), MAX(company) FROM stock GROUP BY drug_name");
    }
}

mysqli_query($con, "CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    address TEXT,
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

mysqli_query($con, "CREATE TABLE IF NOT EXISTS system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100),
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// ── Chống âm kho ở tầng database (lớp bảo vệ cuối cùng) ──
@mysqli_query($con, "ALTER TABLE stock ADD CONSTRAINT chk_stock_qty_non_negative CHECK (quantity >= 0)");

// ── Auto-migration V2: Bổ sung bảng + cột cho báo cáo Group 10 ──
// Tất cả dùng IF NOT EXISTS / ADD COLUMN IF NOT EXISTS → chạy lại an toàn
$_v2_queries = [
    // Bổ sung cột drugs
    "ALTER TABLE `drugs` ADD COLUMN IF NOT EXISTS `category` VARCHAR(100) DEFAULT NULL AFTER `active_ingredient`",
    "ALTER TABLE `drugs` ADD COLUMN IF NOT EXISTS `dosage_form` VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE `drugs` ADD COLUMN IF NOT EXISTS `retail_unit` VARCHAR(50) DEFAULT 'Viên'",
    "ALTER TABLE `drugs` ADD COLUMN IF NOT EXISTS `conversion_factor` INT NOT NULL DEFAULT 1",
    "ALTER TABLE `drugs` ADD COLUMN IF NOT EXISTS `strength` VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE `drugs` ADD COLUMN IF NOT EXISTS `purchase_price` DECIMAL(10,2) DEFAULT 0.00",
    "ALTER TABLE `drugs` ADD COLUMN IF NOT EXISTS `min_stock` INT NOT NULL DEFAULT 20",
    "ALTER TABLE `drugs` ADD COLUMN IF NOT EXISTS `is_deleted` TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE `drugs` ADD COLUMN IF NOT EXISTS `description` TEXT DEFAULT NULL",
    "ALTER TABLE `drugs` ADD COLUMN IF NOT EXISTS `image_path` VARCHAR(255) DEFAULT NULL",
    // Bổ sung admin name + lock
    "ALTER TABLE `admin` ADD COLUMN IF NOT EXISTS `first_name` VARCHAR(50) DEFAULT ''",
    "ALTER TABLE `admin` ADD COLUMN IF NOT EXISTS `last_name` VARCHAR(50) DEFAULT ''",
    "ALTER TABLE `admin` ADD COLUMN IF NOT EXISTS `is_locked` TINYINT(1) NOT NULL DEFAULT 0",
    // Bổ sung lock cho user tables
    "ALTER TABLE `manager` ADD COLUMN IF NOT EXISTS `is_locked` TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE `cashier` ADD COLUMN IF NOT EXISTS `is_locked` TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE `pharmacist` ADD COLUMN IF NOT EXISTS `is_locked` TINYINT(1) NOT NULL DEFAULT 0",
    // Bổ sung cột stock
    "ALTER TABLE `stock` ADD COLUMN IF NOT EXISTS `active_ingredient` VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE `stock` ADD COLUMN IF NOT EXISTS `category` VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE `stock` ADD COLUMN IF NOT EXISTS `company` VARCHAR(120) DEFAULT NULL",
    "ALTER TABLE `stock` ADD COLUMN IF NOT EXISTS `supplier_name` VARCHAR(120) DEFAULT NULL",
    "ALTER TABLE `stock` ADD COLUMN IF NOT EXISTS `unit` VARCHAR(50) DEFAULT 'Hộp'",
    "ALTER TABLE `stock` ADD COLUMN IF NOT EXISTS `retail_unit` VARCHAR(50) DEFAULT 'Viên'",
    "ALTER TABLE `stock` ADD COLUMN IF NOT EXISTS `conversion_factor` INT NOT NULL DEFAULT 1",
    "ALTER TABLE `stock` ADD COLUMN IF NOT EXISTS `is_prescription` TINYINT(1) NOT NULL DEFAULT 0",

    // =========================================================
    // MAPPING DATABASE VỚI BÁO CÁO NHÓM 10 (Chuẩn Hóa UC-03, UC-04)
    // purchase_header ≈ PhieuNhap
    // purchase_item ≈ ChiTietPhieuNhap
    // invoice_header ≈ HoaDon
    // invoice_item ≈ ChiTietHoaDon
    // sale_batch_usage ≈ ChiTietHoaDon_Lo (Chi tiết lô đã bán/FEFO)
    // =========================================================

    // Bảng Purchase Order
    "CREATE TABLE IF NOT EXISTS `purchase_header` (
      `purchase_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `purchase_code` VARCHAR(30) NOT NULL,
      `supplier_id` INT UNSIGNED DEFAULT NULL,
      `supplier_name` VARCHAR(150) NOT NULL,
      `document_no` VARCHAR(100) DEFAULT NULL,
      `note` TEXT DEFAULT NULL,
      `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
      `created_by` VARCHAR(50) DEFAULT NULL,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`purchase_id`),
      UNIQUE KEY `uq_purchase_code` (`purchase_code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS `purchase_item` (
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
      KEY `idx_pi_purchase` (`purchase_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    // Bảng cảnh báo
    "CREATE TABLE IF NOT EXISTS `canh_bao` (
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
      KEY `idx_cb_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
foreach ($_v2_queries as $_q) {
    @mysqli_query($con, $_q);
}

// ── TẠO UNIQUE INDEX AN TOÀN (Tương thích XAMPP/MySQL cũ không hỗ trợ IF NOT EXISTS ở INDEX) ──
$tables_to_index = [
    'admin' => 'idx_admin_username',
    'manager' => 'idx_manager_username',
    'pharmacist' => 'idx_pharmacist_username',
    'cashier' => 'idx_cashier_username'
];
foreach ($tables_to_index as $tbl => $idx) {
    if ($check = mysqli_query($con, "SHOW INDEX FROM `$tbl` WHERE Key_name = '$idx'")) {
        if (mysqli_num_rows($check) == 0) {
            @mysqli_query($con, "ALTER TABLE `$tbl` ADD UNIQUE INDEX `$idx` (`username`)");
        }
    }
}
unset($_v2_queries, $_q);
?>