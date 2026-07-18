<?php
header('Content-Type: application/json; charset=utf-8');
include('../includes/auth.php');
check_login();
include('../config/db_connect.php');

// helper: check if column exists
function has_column($conn, $table, $column) {
    $sql = "SELECT COUNT(*) AS cnt FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '$table' AND column_name = '$column'";
    $res = $conn->query($sql);
    if (!$res) return false;
    $r = $res->fetch_assoc();
    return (int)$r['cnt'] > 0;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo json_encode(['status'=>'ERROR','message'=>'Invalid id']);
    exit;
}

$itemsHasIsDeleted = has_column($conn, 'donation_items', 'is_deleted');
$hasIsDeleted = has_column($conn, 'donors', 'is_deleted');
if (!$hasIsDeleted) {
    $alterSql = "ALTER TABLE donors ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0";
    if ($conn->query($alterSql)) {
        $hasIsDeleted = true;
    }
}

// ตรวจสอบการใช้งานใน donation_items (รองรับทั้งกรณีมี/ไม่มี is_deleted)
if ($itemsHasIsDeleted) {
    $check_sql = "SELECT COUNT(*) AS cnt FROM donation_items WHERE donor_id = $id AND is_deleted = 0";
} else {
    $check_sql = "SELECT COUNT(*) AS cnt FROM donation_items WHERE donor_id = $id";
}
$res = $conn->query($check_sql);
if (!$res) {
    echo json_encode(['status'=>'ERROR','message'=>'DB error: '.$conn->error]);
    exit;
}
$row = $res->fetch_assoc();
$count = (int)$row['cnt'];

if ($count > 0) {
    // มีการใช้งาน -> ถ้ามีคอลัมน์ is_deleted ใน donors ให้ soft-delete ได้
    if ($hasIsDeleted) {
        $stmt = $conn->prepare("UPDATE donors SET is_deleted = 1 WHERE id = ?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            echo json_encode(['status'=>'OK','action'=>'disabled','message'=>'ผู้บริจาคถูกตั้งเป็นปิดการมองเห็น เพราะมีการใช้งานในรายการบริจาค']);
        } else {
            echo json_encode(['status'=>'ERROR','message'=>$conn->error]);
        }
        $stmt->close();
    } else {
        // ไม่สามารถลบหรือ soft-delete ได้เพราะตารางไม่มีคอลัมน์ is_deleted
        echo json_encode(['status'=>'ERROR','message'=>'ไม่สามารถลบผู้บริจาคนี้ได้ เพราะมีรายการบริจาคอยู่และตาราง donors ไม่มีคอลัมน์ is_deleted สำหรับ soft-delete']);
    }
} else {
    // ไม่มีการใช้งาน -> ลบถาวร
    $stmt = $conn->prepare("DELETE FROM donors WHERE id = ?");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        echo json_encode(['status'=>'OK','action'=>'deleted','message'=>'ลบผู้บริจาคเรียบร้อยแล้ว']);
    } else {
        echo json_encode(['status'=>'ERROR','message'=>$conn->error]);
    }
    $stmt->close();
}

$conn->close();

?>
