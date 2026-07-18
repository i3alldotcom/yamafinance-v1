<?php
include('../includes/auth.php');
check_login();

if ($_SESSION['role'] !== 'admin') {
    die("คุณไม่มีสิทธิ์เพิ่มผู้ใช้");
}

include('../config/db_connect.php');

$full_name = trim($_POST['full_name']);
$username = trim($_POST['username']);
$password = trim($_POST['password']);
$role = trim($_POST['role']);

if (!in_array($role, ['admin','accountant','data_entry'])) {
    die("สิทธิ์ไม่ถูกต้อง");
}

$check = $conn->prepare("SELECT id FROM users WHERE username = ?");
$check->bind_param("s", $username);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    die("ชื่อผู้ใช้นี้มีอยู่แล้ว");
}

$password_hash = password_hash($password, PASSWORD_DEFAULT);

$sql = "INSERT INTO users (full_name, username, password_hash, role, is_active)
        VALUES (?, ?, ?, ?, 1)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $full_name, $username, $password_hash, $role);

if ($stmt->execute()) {
    echo "OK";
} else {
    echo "เกิดข้อผิดพลาด";
}
?>
