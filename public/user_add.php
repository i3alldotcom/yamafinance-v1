<?php
include('../includes/auth.php');
check_login();
require_role('admin');

include('../config/db_connect.php');

$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role = trim($_POST['role']);

    if (!in_array($role, ['admin','accountant','data_entry'])) {
        die("สิทธิ์ไม่ถูกต้อง");
    }

    $check = $conn->prepare("SELECT id FROM users WHERE username = ? AND is_deleted = 0");
    $check->bind_param("s", $username);
    $check->execute();
    $check_result = $check->get_result();

    if ($check_result->num_rows > 0) {
        $error = "ชื่อผู้ใช้นี้มีอยู่แล้ว";
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (username, password_hash, role, full_name)
                VALUES (?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $username, $password_hash, $role, $full_name);

        if ($stmt->execute()) {
            $success = "เพิ่มผู้ใช้ใหม่สำเร็จแล้ว!";
        } else {
            $error = "เกิดข้อผิดพลาด";
        }
    }
}
?>
