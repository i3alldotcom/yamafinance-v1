<?php
header('Content-Type: application/json; charset=utf-8');
include('../includes/auth.php');
check_login();
include('../config/db_connect.php');

// ดึงเฉพาะบริษัท / ร้านค้าที่เปิดใช้งาน (disable = 0)
$res = $conn->query("SELECT id, vendor_name FROM vendors WHERE disable = 0 ORDER BY vendor_name ASC");

$out = [];
while ($r = $res->fetch_assoc()) {
    $out[] = $r;
}

echo json_encode($out);
?>
