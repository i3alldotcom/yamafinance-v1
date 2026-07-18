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
$full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$line_id = isset($_POST['line_id']) ? trim($_POST['line_id']) : '';
$birth_date = isset($_POST['birth_date']) ? trim($_POST['birth_date']) : '';

if ($id <= 0 || $full_name === '') {
    echo json_encode(['status'=>'ERROR','message'=>'ข้อมูลไม่ครบถ้วน']);
    exit;
}

$check = $conn->prepare("SELECT id FROM donors WHERE full_name = ? AND id != ? LIMIT 1");
$check->bind_param('si', $full_name, $id);
$check->execute();
$check->store_result();
if ($check->num_rows > 0) {
    echo json_encode(['status'=>'ERROR','message'=>'ชื่อผู้บริจาคนี้มีอยู่แล้ว']);
    exit;
}
$check->close();

$stmt = $conn->prepare("UPDATE donors SET full_name = ?, phone = ?, line_id = ?, birth_date = ? WHERE id = ?");
$stmt->bind_param('ssssi', $full_name, $phone, $line_id, $birth_date, $id);
if ($stmt->execute()) {
    echo json_encode(['status'=>'OK']);
} else {
    echo json_encode(['status'=>'ERROR','message'=>$conn->error]);
}

$stmt->close();
$conn->close();

?>
