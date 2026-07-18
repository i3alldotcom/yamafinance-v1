<?php
header('Content-Type: application/json; charset=utf-8');
include('../includes/auth.php');
check_login();
include('../config/db_connect.php');

// helper: check if column exists
function has_column($conn, $table, $column) {
    $sql = "SELECT COUNT(*) AS cnt FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '$table' AND column_name = '$column'";
    $res = $conn->query($sql);
    if (!$res) return false;
    $r = $res->fetch_assoc();
    return (int)$r['cnt'] > 0;
}

$q = trim($_GET['q']);

if ($q == "") {
    echo json_encode([]);
    exit;
}

$q = mysqli_real_escape_string($conn, $q);

$hasIsDeleted = has_column($conn, 'donors', 'is_deleted');
$whereDeleted = $hasIsDeleted ? " AND is_deleted = 0" : "";

$sql = "SELECT id, full_name 
        FROM donors 
        WHERE full_name LIKE '%$q%' $whereDeleted
        ORDER BY full_name ASC
        LIMIT 10";

$result = $conn->query($sql);

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
?>
