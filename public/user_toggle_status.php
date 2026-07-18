<?php
include('../includes/auth.php');
check_login();

if ($_SESSION['role'] !== 'admin') {
    die("คุณไม่มีสิทธิ์เปลี่ยนสถานะผู้ใช้");
}

include('../config/db_connect.php');

$id = $_GET['id'];

// ดึงสถานะปัจจุบัน
$sql = "SELECT is_active FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$new_status = ($user['is_active'] == 1) ? 0 : 1;

// อัปเดตสถานะใหม่
$sql = "UPDATE users SET is_active = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $new_status, $id);
$stmt->execute();

echo "OK";
?>
