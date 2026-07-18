<?php

header('Content-Type: application/json; charset=utf-8');
include('../includes/auth.php');
check_login();
include('../config/db_connect.php');

$q = trim($_GET['q']);

if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

// ป้องกัน collation mismatch
$conn->query("SET NAMES utf8");

$q_safe = $conn->real_escape_string($q);

$sql = "
    SELECT id, vendor_name
    FROM vendors
    WHERE disable = 0
      AND vendor_name LIKE '%$q_safe%'
    ORDER BY vendor_name ASC
    LIMIT 20
";
//echo $sql; // Debug: แสดง SQL query
$res = $conn->query($sql);

$out = [];
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $out[] = $r;
    }
}

echo json_encode($out);

?>
