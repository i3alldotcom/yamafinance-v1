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

$check = $conn->prepare("SELECT COUNT(*) AS cnt FROM donation_items WHERE merit_id = ? AND is_deleted = 0");
$check->bind_param('i', $id);
$check->execute();
$row = $check->get_result()->fetch_assoc();
$count = (int)$row['cnt'];
$check->close();

if ($count > 0) {
    // มีการใช้งาน -> ทำ soft-disable
    $stmt = $conn->prepare("UPDATE merits SET disable = 1 WHERE id = ?");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        echo json_encode(['status'=>'OK','action'=>'disabled','message'=>'บุญนี้ถูกตั้งเป็นปิดใช้งานเพราะมีการใช้งานในรายการบริจาค']);
    } else {
        echo json_encode(['status'=>'ERROR','message'=>$conn->error]);
    }
    $stmt->close();

} else {
    // ไม่มีการใช้งาน -> ลบถาวร
    $stmt = $conn->prepare("DELETE FROM merits WHERE id = ?");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        echo json_encode(['status'=>'OK','action'=>'deleted','message'=>'ลบข้อมูลบุญเรียบร้อยแล้ว']);
    } else {
        echo json_encode(['status'=>'ERROR','message'=>$conn->error]);
    }
    $stmt->close();
}

$conn->close();

?>
