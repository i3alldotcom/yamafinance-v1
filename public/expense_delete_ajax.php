<?php

header('Content-Type: application/json; charset=utf-8');
include('../includes/auth.php');
check_login();
include('../config/db_connect.php');

$id = trim($_GET['id']);
//echo "ID: $id";

if ($id <= 0) {
    echo json_encode(['status' => 'ERROR', 'message' => 'Invalid ID']);
    exit;
}

$deleted_by = $_SESSION['user_id'];
//echo "Deleted by: $deleted_by";


// ลบแบบ soft delete: อัปเดต deleted, deleted_by, deleted_at
$stmt = $conn->prepare("
    UPDATE expenses
    SET is_deleted = 1,
        deleted_by = ?,
        deleted_at = NOW()
    WHERE id = ?
");
$stmt->bind_param("ii", $deleted_by, $id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'OK']);
} else {
    echo json_encode(['status' => 'ERROR', 'message' => $conn->error]);
}

$stmt->close();
$conn->close();

?>
