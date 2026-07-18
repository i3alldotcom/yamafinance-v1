<?php
include('../includes/auth.php');
check_login();

if ($_SESSION['role'] !== 'admin') {
    die("คุณไม่มีสิทธิ์แก้ไขผู้ใช้");
}

include('../config/db_connect.php');

$id = $_POST['id'];
$full_name = trim($_POST['full_name']);
$username = trim($_POST['username']);
$role = trim($_POST['role']);

if (!in_array($role, ['admin','accountant','data_entry'])) {
    die("สิทธิ์ไม่ถูกต้อง");
}

$sql = "UPDATE users SET full_name=?, username=?, role=? WHERE id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssi", $full_name, $username, $role, $id);
$stmt->execute();

header("Location: users.php");
exit;
?>
