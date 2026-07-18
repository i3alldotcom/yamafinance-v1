<?php
include('../includes/auth.php');
check_login();
include('../config/db_connect.php');

$id = intval($_POST['id']);
$expense_date = $_POST['expense_date'];
$category_id  = $_POST['category_id'];
$vendor_id    = $_POST['vendor_id'];
$vendor_name  = $_POST['vendor_name'];
$detail       = $_POST['detail'];
$amount       = floatval($_POST['amount']);
$payment_method = $_POST['payment_method'];

// ถ้าร้านค้าใหม่ → เพิ่มร้านค้า
if ($vendor_id == 0 && $vendor_name != "") {
    $stmt = $conn->prepare("INSERT INTO vendors (vendor_name, disable) VALUES (?, 0)");
    $stmt->bind_param("s", $vendor_name);
    $stmt->execute();
    $vendor_id = $stmt->insert_id;
    $stmt->close();
}

// อัปเดตข้อมูลรายจ่าย
$stmt = $conn->prepare("
    UPDATE expenses
    SET expense_date=?, category_id=?, vendor_id=?, detail=?, amount=?, payment_method=?
    WHERE id=?
");
$stmt->bind_param("siisdsi", $expense_date, $category_id, $vendor_id, $detail, $amount, $payment_method, $id);
$stmt->execute();
$stmt->close();

/* ---------------------------------------------------------
   ส่วนที่เพิ่ม: อัปโหลดใบเสร็จ (ถ้ามี)
--------------------------------------------------------- */
if (!empty($_FILES['receipt']['name'])) {

    $target_dir = "../uploads/receipts/";
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    // ดึงข้อมูลใบเสร็จเดิมเพื่อลบไฟล์เก่า
    $old = $conn->query("SELECT uploaded FROM expenses WHERE id = $id")->fetch_assoc();
    if (!empty($old['uploaded']) && $old['uploaded'] !== '0') {
        $old_file = $target_dir . $old['uploaded'];
        if (file_exists($old_file)) unlink($old_file);
    }

    // ดึง expense_code ของรายการนี้
    $row = $conn->query("SELECT expense_code FROM expenses WHERE id = $id")->fetch_assoc();
    $expense_code = $row['expense_code'];

    // แยกส่วน XXX จาก expense_code เช่น 2026/06-015 → 015
    $code_parts = explode('-', $expense_code);
    $xxx = $code_parts[1];

    // แยกปีและเดือนจาก expense_date
    $year  = date('Y', strtotime($expense_date));
    $month = date('m', strtotime($expense_date));

    // สร้างชื่อไฟล์ใหม่ YYYYMM-XXX.ext
    $ext = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));
    $new_name = $year . $month . "-" . $xxx . "." . $ext;
    $target_file = $target_dir . $new_name;

    // ย้ายไฟล์และบันทึกชื่อไฟล์ลงฐานข้อมูล
    if (move_uploaded_file($_FILES['receipt']['tmp_name'], $target_file)) {
        $stmt = $conn->prepare("UPDATE expenses SET uploaded = ? WHERE id = ?");
        $stmt->bind_param("si", $new_name, $id);
        $stmt->execute();
        $stmt->close();
    }
}


/* --------------------------------------------------------- */

header("Location: expenses.php");
exit;
?>
