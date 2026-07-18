<?php
include('../includes/auth.php');   // session_start() + check_login()
check_login();
include('../config/db_connect.php');
header('Content-Type: application/json; charset=utf-8');

$donor_id = isset($_GET['donor_id']) ? intval($_GET['donor_id']) : 0;
if ($donor_id <= 0) {
    echo json_encode(['receipt_name' => ''], JSON_UNESCAPED_UNICODE);
    exit;
}

// ✅ ดึงชื่อบนใบอนุโมทนาบัตรล่าสุดของ donor_id นี้
// แก้บั๊ก: alias ตารางคือ `di` (ไม่ใช่ `d`) และเพิ่ม ORDER BY ให้ได้รายการล่าสุดจริง
$sql = "
    SELECT di.receipt_name
    FROM donation_items di
    WHERE di.donor_id = ? AND di.is_deleted = 0
    ORDER BY di.donation_date DESC, di.id DESC
    LIMIT 1
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['error' => $conn->error], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt->bind_param("i", $donor_id);
$stmt->execute();
$stmt->bind_result($receipt_name);
$stmt->fetch();
$stmt->close();

echo json_encode(['receipt_name' => $receipt_name ?: ''], JSON_UNESCAPED_UNICODE);
?>
