<?php
include('../includes/auth.php');
check_login();
include('../config/db_connect.php');

$id = intval($_GET['id']);

if ($id <= 0) {
    echo "Invalid ID";
    exit;
}

// ลบแบบ soft delete
$sql = "
    UPDATE donation_items 
    SET is_deleted = 1, deleted_at = NOW(), deleted_by = ".$_SESSION['user_id']."
    WHERE id = $id
";

if ($conn->query($sql)) {
    echo "OK";   // สำคัญมาก ต้องเป็น OK เท่านั้น
} else {
    echo "ERROR: " . $conn->error;
}

$conn->close();
?>
