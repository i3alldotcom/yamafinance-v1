<?php
include('../includes/auth.php');
check_login();
include('../config/db_connect.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $expense_id = intval($_POST['expense_id']);
    $target_dir = "../uploads/receipts/";

    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    // ดึง expense_code และ expense_date จากฐานข้อมูล
    $row = $conn->query("SELECT expense_code, expense_date FROM expenses WHERE id = $expense_id")->fetch_assoc();
    $expense_code = $row['expense_code'];
    $expense_date = $row['expense_date'];

    // fallback ถ้า expense_date ว่าง
    if (empty($expense_date)) {
        $expense_date = date('Y-m-d');
    }

    // แยก XXX จาก expense_code เช่น 2026/06-015 → 015
    $code_parts = explode('-', $expense_code);
    $xxx = $code_parts[1];

    // ดึงปีและเดือนจาก expense_date
    $year  = date('Y', strtotime($expense_date));
    $month = date('m', strtotime($expense_date));

    // รับไฟล์ที่แนบ
    $file = $_FILES['receipt'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    // ตั้งชื่อไฟล์ใหม่แบบ YYYYMM-XXX.ext
    $new_name = $year . $month . "-" . $xxx . "." . $ext;
    $target_file = $target_dir . $new_name;

    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        $stmt = $conn->prepare("UPDATE expenses SET uploaded = ? WHERE id = ?");
        $stmt->bind_param("si", $new_name, $expense_id);
        $stmt->execute();
        echo json_encode(["status" => "OK"]);
    } else {
        echo json_encode(["status" => "ERROR", "message" => "อัปโหลดไม่สำเร็จ"]);
    }
}
?>
