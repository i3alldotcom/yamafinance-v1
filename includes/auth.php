<?php
session_start();

function check_login() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: index.php");
        exit;
    }
}

function require_role() {
    $roles = func_get_args(); // รับทุก argument เป็น array อัตโนมัติ

    if (!isset($_SESSION['role'])) {
        die("คุณยังไม่ได้เข้าสู่ระบบ");
    }

    if (!in_array($_SESSION['role'], $roles)) {
        die('<div style="font-family:sans-serif;text-align:center;margin-top:100px">
            <h2>⛔ ไม่มีสิทธิ์เข้าถึงหน้านี้</h2>
            <a href="dashboard.php">← กลับหน้าหลัก</a>
        </div>');
    }
}
?>
