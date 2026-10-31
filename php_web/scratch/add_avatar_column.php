<?php
include_once 'app.php';
$tables = ['admin', 'manager', 'cashier', 'pharmacist'];
foreach ($tables as $table) {
    try {
        pms_exec("ALTER TABLE `$table` ADD COLUMN `avatar` VARCHAR(255) DEFAULT '01.jpg' AFTER `password` ");
        echo "Added avatar column to $table\n";
    } catch (Exception $e) {
        echo "Error on $table: " . $e->getMessage() . "\n";
    }
}
?>
