<?php
header('Content-Type: application/json; charset=utf-8');
include('../includes/auth.php');
check_login();
include('../config/db_connect.php');

$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';

if ($name === '') {
    echo json_encode(['status'=>'ERROR','message'=>'กรุณากรอกชื่อประเภท']);
    exit;
}

$check = $conn->prepare("SELECT id FROM expense_categories WHERE name = ? AND disable = 0 LIMIT 1");
$check->bind_param('s', $name);
$check->execute();
$check->store_result();
if ($check->num_rows > 0) {
    echo json_encode(['status'=>'ERROR','message'=>'ประเภทนี้มีอยู่แล้ว']);
    exit;
}
$check->close();

$stmt = $conn->prepare("INSERT INTO expense_categories (name, description, disable) VALUES (?, ?, 0)");
$stmt->bind_param('ss', $name, $description);
if ($stmt->execute()) {
    echo json_encode(['status'=>'OK','id'=>$conn->insert_id,'name'=>$name]);
} else {
    echo json_encode(['status'=>'ERROR','message'=>$conn->error]);
}
$stmt->close();
$conn->close();
?>
