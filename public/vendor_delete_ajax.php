<?php
header('Content-Type: application/json; charset=utf-8');
include('../includes/auth.php');
check_login();
include('../config/db_connect.php');

// ตรวจสอบว่ามีคอลัมน์ในตารางหรือไม่
function has_column($conn, $table, $column) {
    $sql = "SELECT COUNT(*) AS cnt 
            FROM information_schema.columns 
            WHERE table_schema = DATABASE() 
              AND table_name = '$table' 
              AND column_name = '$column'";
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

// ตรวจสอบว่าตาราง vendors มีคอลัมน์ disable หรือไม่
$hasDisable = has_column($conn, 'vendors', 'disable');
if (!$hasDisable) {
    // เพิ่มคอลัมน์ disable หากไม่มี
    $alterSql = "ALTER TABLE vendors ADD COLUMN disable TINYINT(1) NOT NULL DEFAULT 0";
    if ($conn->query($alterSql)) {
        $hasDisable = true;
    }
}

// ตรวจสอบการใช้งานในตาราง expenses
// ถ้ามี vendor_id อยู่ใน expenses → ห้ามลบ ต้อง disable เท่านั้น
$check_sql = "SELECT COUNT(*) AS cnt FROM expenses WHERE vendor_id = $id";
$res = $conn->query($check_sql);

if (!$res) {
    echo json_encode(['status'=>'ERROR','message'=>'DB error: '.$conn->error]);
    exit;
}

$row = $res->fetch_assoc();
$count = (int)$row['cnt'];

if ($count > 0) {
    // มีการใช้งาน → disable แทนการลบ
    if ($hasDisable) {
        $stmt = $conn->prepare("UPDATE vendors SET disable = 1 WHERE id = ?");
        $stmt->bind_param('i', $id);

        if ($stmt->execute()) {
            echo json_encode([
                'status' => 'OK',
                'action' => 'disabled',
                'message' => 'บริษัทนี้ถูกตั้งเป็นปิดใช้งาน เนื่องจากมีการใช้งานอยู่ในรายการรายจ่าย'
            ]);
        } else {
            echo json_encode(['status'=>'ERROR','message'=>$conn->error]);
        }

        $stmt->close();
    } else {
        echo json_encode([
            'status'=>'ERROR',
            'message'=>'ไม่สามารถลบหรือปิดใช้งานบริษัทนี้ได้ เพราะตาราง vendors ไม่มีคอลัมน์ disable'
        ]);
    }

} else {
    // ไม่มีการใช้งาน → ลบถาวร
    $stmt = $conn->prepare("DELETE FROM vendors WHERE id = ?");
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        echo json_encode([
            'status'=>'OK',
            'action'=>'deleted',
            'message'=>'ลบบริษัท / ร้านค้าเรียบร้อยแล้ว'
        ]);
    } else {
        echo json_encode(['status'=>'ERROR','message'=>$conn->error]);
    }

    $stmt->close();
}

$conn->close();
?>
