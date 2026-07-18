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
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';

if ($id <= 0 || $name === '') {
    echo json_encode(['status'=>'ERROR','message'=>'ข้อมูลไม่ครบถ้วน']);
    exit;
}

$check = $conn->prepare("SELECT id FROM expense_categories WHERE name = ? AND id != ? LIMIT 1");
$check->bind_param('si', $name, $id);
$check->execute();
$check->store_result();
if ($check->num_rows > 0) {
    echo json_encode(['status'=>'ERROR','message'=>'ชื่อประเภทนี้มีอยู่แล้ว']);
    exit;
}
$check->close();

$stmt = $conn->prepare("UPDATE expense_categories SET name = ?, description = ? WHERE id = ?");
$stmt->bind_param('ssi', $name, $description, $id);
if ($stmt->execute()) {
    echo json_encode(['status'=>'OK']);
} else {
    echo json_encode(['status'=>'ERROR','message'=>$conn->error]);
}
$stmt->close();
$conn->close();
?>
