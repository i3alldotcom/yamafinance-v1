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

// ดึงค่า disable ปัจจุบัน
$stmt = $conn->prepare("SELECT disable FROM vendors WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->bind_result($disable);

if (!$stmt->fetch()) {
    $stmt->close();
    echo json_encode(['status'=>'ERROR','message'=>'ไม่พบข้อมูลบริษัท / ร้านค้า']);
    exit;
}
$stmt->close();

// สลับค่า disable
$new = $disable ? 0 : 1;

$stmt = $conn->prepare("UPDATE vendors SET disable = ? WHERE id = ?");
$stmt->bind_param('ii', $new, $id);

if ($stmt->execute()) {
    echo json_encode(['status'=>'OK','disable'=>$new]);
} else {
    echo json_encode(['status'=>'ERROR','message'=>$conn->error]);
}

$stmt->close();
$conn->close();
?>
