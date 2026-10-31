<?php
include_once 'app.php';
pms_auth('pharmacist');
$id = (int)($_GET['prescription_id'] ?? 0);
if ($id > 0) {
    pms_exec('DELETE FROM prescription WHERE prescription_id = ?', 'i', [$id]);
    pms_flash('Record deleted successfully.');
}
pms_redirect('prescription.php');
