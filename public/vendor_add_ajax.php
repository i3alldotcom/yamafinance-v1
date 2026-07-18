<?php
header('Content-Type: application/json; charset=utf-8');
include('../includes/auth.php');
check_login();
include('../config/db_connect.php');

$vendor_name = isset($_POST['vendor_name']) ? trim($_POST['vendor_name']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';

if ($vendor_name === '') {
    echo json_encode(['status' => 'ERROR', 'message' => 'กรุณากรอกชื่อบริษัท / ร้านค้า']);
    exit;
}

// ตรวจสอบชื่อซ้ำ (เฉพาะที่ยังไม่ disable)
$check = $conn->prepare("SELECT id FROM vendors WHERE vendor_name = ? AND disable = 0 LIMIT 1");
$check->bind_param('s', $vendor_name);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    echo json_encode(['status' => 'ERROR', 'message' => 'บริษัท / ร้านค้านี้มีอยู่แล้ว']);
    exit;
}
$check->close();

// เพิ่มข้อมูลใหม่
$stmt = $conn->prepare("INSERT INTO vendors (vendor_name, description, disable) VALUES (?, ?, 0)");
$stmt->bind_param('ss', $vendor_name, $description);

if ($stmt->execute()) {
    echo json_encode([
        'status' => 'OK',
        'id' => $conn->insert_id,
        'vendor_name' => $vendor_name
    ]);
} else {
    echo json_encode(['status' => 'ERROR', 'message' => $conn->error]);
}

$stmt->close();
$conn->close();
?>
