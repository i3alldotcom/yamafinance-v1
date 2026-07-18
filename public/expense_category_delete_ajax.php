<?php
header('Content-Type: application/json; charset=utf-8');
include('../includes/auth.php');
check_login();
include('../config/db_connect.php');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo json_encode(['status'=>'ERROR','message'=>'Invalid id']);
    exit;
}

// ตรวจสอบว่าตาราง expenses มีคอลัมน์ is_deleted หรือไม่
$hasIsDeleted = false;
$checkCol = $conn->prepare("SELECT COUNT(*) AS cnt FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'expenses' AND column_name = 'is_deleted'");
$checkCol->execute();
$colRes = $checkCol->get_result()->fetch_assoc();
if ($colRes && $colRes['cnt'] > 0) $hasIsDeleted = true;
$checkCol->close();

if ($hasIsDeleted) {
    $check = $conn->prepare("SELECT COUNT(*) AS cnt FROM expenses WHERE category_id = ? AND is_deleted = 0");
    $check->bind_param('i', $id);
} else {
    $check = $conn->prepare("SELECT COUNT(*) AS cnt FROM expenses WHERE category_id = ?");
    $check->bind_param('i', $id);
}

$check->execute();
$row = $check->get_result()->fetch_assoc();
$count = (int)$row['cnt'];
$check->close();

if ($count > 0) {
    // มีการใช้งาน -> ทำ soft-disable
    $stmt = $conn->prepare("UPDATE expense_categories SET disable = 1 WHERE id = ?");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        echo json_encode(['status'=>'OK','action'=>'disabled','message'=>'ประเภทนี้ถูกตั้งเป็นปิดใช้งาน เพราะมีการใช้งานในรายการรายจ่าย']);
    } else {
        echo json_encode(['status'=>'ERROR','message'=>$conn->error]);
    }
    $stmt->close();

} else {
    // ไม่มีการใช้งาน -> ลบถาวร
    $stmt = $conn->prepare("DELETE FROM expense_categories WHERE id = ?");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        echo json_encode(['status'=>'OK','action'=>'deleted','message'=>'ลบประเภทเรียบร้อยแล้ว']);
    } else {
        echo json_encode(['status'=>'ERROR','message'=>$conn->error]);
    }
    $stmt->close();
}

$conn->close();
?>
