<?php
include_once 'app.php';
$tables = ['admin', 'manager', 'cashier', 'pharmacist'];
foreach ($tables as $table) {
    try {
        pms_exec("ALTER TABLE `$table` ADD COLUMN `notes` TEXT AFTER `avatar` ");
        echo "Added notes column to $table\n";
    } catch (Exception $e) {
        echo "Error on $table: " . $e->getMessage() . "\n";
    }
}
?>
