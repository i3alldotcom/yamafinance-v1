<?php
include('../includes/auth.php');   // session_start() + check_login()
check_login();

include('../config/db_connect.php');
session_start();
/*
// ตรวจสิทธิ์เฉพาะ admin
require_role('admin');
*/
if (!empty($_POST['id'])) {
    $id = intval($_POST['id']);
    $new_password = password_hash('1234', PASSWORD_DEFAULT);

    $sql = "UPDATE users SET password_hash = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $new_password, $id);
    $stmt->execute();

    echo "OK";
}

?>
