<?php
include('../includes/auth.php');   // session_start() + check_login()
check_login();
include('../config/db_connect.php');
header('Content-Type: application/json; charset=utf-8');

$donor_id = isset($_GET['donor_id']) ? intval($_GET['donor_id']) : 0;
$q        = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($donor_id <= 0) {
    echo json_encode([], JSON_UNESCAPED_UNICODE);
    exit;
}

// ค้นหาชื่อบนใบอนุโมทนาบัตรที่ผู้นำบุญคนนี้เคยใช้ (ไม่ซ้ำ) เรียงจากล่าสุด
$like = '%' . $q . '%';
$sql = "
    SELECT di.receipt_name,
           MAX(di.donation_date) AS last_date,
           MAX(di.id)            AS last_id
    FROM donation_items di
    WHERE di.donor_id = ?
      AND di.is_deleted = 0
      AND di.receipt_name <> ''
      AND di.receipt_name LIKE ?
    GROUP BY di.receipt_name
    ORDER BY last_date DESC, last_id DESC
    LIMIT 10
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['error' => $conn->error], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt->bind_param("is", $donor_id, $like);
$stmt->execute();
$result = $stmt->get_result();

$names = [];
while ($row = $result->fetch_assoc()) {
    $names[] = $row['receipt_name'];
}
$stmt->close();

echo json_encode($names, JSON_UNESCAPED_UNICODE);
?>
