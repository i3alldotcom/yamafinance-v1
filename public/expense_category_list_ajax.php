<?php
header('Content-Type: application/json; charset=utf-8');
include('../includes/auth.php');
check_login();
include('../config/db_connect.php');

$res = $conn->query("SELECT id, name FROM expense_categories WHERE disable = 0 ORDER BY name ASC");
$out = [];
while ($r = $res->fetch_assoc()) $out[] = $r;

echo json_encode($out);
?>
