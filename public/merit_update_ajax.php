<?php
header('Content-Type: application/json; charset=utf-8');
include('../includes/auth.php');
check_login();
include('../config/db_connect.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status'=>'ERROR','message'=>'Invalid request']);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$merit_name = isset($_POST['merit_name']) ? trim($_POST['merit_name']) : '';

if ($id <= 0 || $merit_name === '') {
    echo json_encode(['status'=>'ERROR','message'=>'ข้อมูลไม่ครบถ้วน']);
    exit;
}

// ตรวจสอบชื่อซ้ำ (ยกเว้นตัวเอง)
$check = $conn->prepare("SELECT id FROM merits WHERE merit_name = ? AND id != ? LIMIT 1");
$check->bind_param('si', $merit_name, $id);
$check->execute();
$check->store_result();
if ($check->num_rows > 0) {
    echo json_encode(['status'=>'ERROR','message'=>'ชื่อบุญนี้มีอยู่แล้ว']);
    exit;
}
$check->close();

$stmt = $conn->prepare("UPDATE merits SET merit_name = ? WHERE id = ?");
$stmt->bind_param('si', $merit_name, $id);
if ($stmt->execute()) {
    echo json_encode(['status'=>'OK']);
} else {
    echo json_encode(['status'=>'ERROR','message'=>$conn->error]);
}

$stmt->close();
$conn->close();

?>
