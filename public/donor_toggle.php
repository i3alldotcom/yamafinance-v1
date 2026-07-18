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

function ensure_column($conn, $table, $column, $definition) {
    if (has_column($conn, $table, $column)) {
        return true;
    }
    $sql = "ALTER TABLE $table ADD COLUMN $column $definition";
    return (bool)$conn->query($sql);
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo json_encode(['status'=>'ERROR','message'=>'Invalid id']);
    exit;
}

$hasIsDeleted = ensure_column($conn, 'donors', 'is_deleted', 'TINYINT(1) NOT NULL DEFAULT 0');
if (!$hasIsDeleted) {
    echo json_encode(['status'=>'ERROR','message'=>'Cannot toggle: donors table missing is_deleted column']);
    exit;
}

$stmt = $conn->prepare("SELECT is_deleted FROM donors WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->bind_result($is_deleted);
if (!$stmt->fetch()) {
    $stmt->close();
    echo json_encode(['status'=>'ERROR','message'=>'ไม่พบข้อมูล']);
    exit;
}
$stmt->close();

$new = $is_deleted ? 0 : 1;
$stmt = $conn->prepare("UPDATE donors SET is_deleted = ? WHERE id = ?");
$stmt->bind_param('ii', $new, $id);
if ($stmt->execute()) {
    echo json_encode(['status'=>'OK','is_deleted'=>$new]);
} else {
    echo json_encode(['status'=>'ERROR','message'=>$conn->error]);
}

$stmt->close();
$conn->close();

?>
