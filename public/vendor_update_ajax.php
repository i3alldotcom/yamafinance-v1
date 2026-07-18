<?php
header('Content-Type: application/json; charset=utf-8');
include('../includes/auth.php');
check_login();
include('../config/db_connect.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'ERROR', 'message' => 'Invalid request']);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$vendor_name = isset($_POST['vendor_name']) ? trim($_POST['vendor_name']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';

if ($id <= 0 || $vendor_name === '') {
    echo json_encode(['status' => 'ERROR', 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

// ตรวจสอบชื่อซ้ำ (ยกเว้นตัวเอง)
$check = $conn->prepare("SELECT id FROM vendors WHERE vendor_name = ? AND id != ? LIMIT 1");
$check->bind_param('si', $vendor_name, $id);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    echo json_encode(['status' => 'ERROR', 'message' => 'ชื่อบริษัท / ร้านค้านี้มีอยู่แล้ว']);
    exit;
}
$check->close();

// อัปเดตข้อมูล
$stmt = $conn->prepare("UPDATE vendors SET vendor_name = ?, description = ? WHERE id = ?");
$stmt->bind_param('ssi', $vendor_name, $description, $id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'OK']);
} else {
    echo json_encode(['status' => 'ERROR', 'message' => $conn->error]);
}

$stmt->close();
$conn->close();
?>
