<?php
include('../includes/auth.php');
check_login();
include('../config/db_connect.php');

$expense_date = $_POST['expense_date'];
$category_id  = $_POST['category_id'];
$vendor_id    = $_POST['vendor_id'];
$vendor_name  = $_POST['vendor_search'];
$detail       = $_POST['detail'];
$amount       = floatval($_POST['amount']);
$payment_method = $_POST['payment_method'];

// ถ้าไม่มี vendor_id แต่มีชื่อร้านค้า → เพิ่มร้านค้าใหม่
if ($vendor_id == 0 && $vendor_name !== '') {
    $stmt = $conn->prepare("INSERT INTO vendors (vendor_name, disable) VALUES (?, 0)");
    $stmt->bind_param("s", $vendor_name);
    $stmt->execute();
    $vendor_id = $stmt->insert_id;
    $stmt->close();
}

// สร้าง expense_code
$year  = date('Y', strtotime($expense_date));
$month = date('m', strtotime($expense_date));

$sql_run = "
    SELECT COUNT(*) + 1 AS run_no
    FROM expenses
    WHERE YEAR(expense_date) = $year
      AND MONTH(expense_date) = $month
";
$res_run = $conn->query($sql_run);
$run_no = str_pad($res_run->fetch_assoc()['run_no'], 3, '0', STR_PAD_LEFT);

$expense_code = "$year/$month-$run_no";

// บันทึกข้อมูลรายจ่าย
$stmt = $conn->prepare("
    INSERT INTO expenses (expense_code, expense_date, category_id, vendor_id, detail, amount, payment_method, is_deleted, uploaded)
    VALUES (?, ?, ?, ?, ?, ?, ?, 0, '0')
");
$stmt->bind_param("ssiisds", $expense_code, $expense_date, $category_id, $vendor_id, $detail, $amount, $payment_method);
$stmt->execute();
$new_id = $stmt->insert_id;
$stmt->close();

/* ---------------------------------------------------------
   ส่วนที่เพิ่ม: อัปโหลดใบเสร็จ
--------------------------------------------------------- */
if (!empty($_FILES['receipt']['name'])) {

    $target_dir = "../uploads/receipts/";
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $ext = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));
    $code_parts = explode('-', $expense_code);
    $xxx = $code_parts[1]; // ได้ "015"

// สร้างชื่อไฟล์ใหม่ YYYYMM-XXX.ext
    $new_name = $year . $month . "-" . $xxx . "." . $ext;
    $target_file = $target_dir . $new_name;

    if (move_uploaded_file($_FILES['receipt']['tmp_name'], $target_file)) {
        $stmt = $conn->prepare("UPDATE expenses SET uploaded = ? WHERE id = ?");
        $stmt->bind_param("si", $new_name, $new_id);
        $stmt->execute();
        $stmt->close();
    }
}

/* --------------------------------------------------------- */

header("Location: expense_saved.php?id=" . $new_id);
exit;

?>
