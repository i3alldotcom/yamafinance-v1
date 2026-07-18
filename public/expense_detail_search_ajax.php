<?php
include('../includes/auth.php');   // session_start() + check_login()
check_login();
include('../config/db_connect.php');
header('Content-Type: application/json; charset=utf-8');

$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$q           = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($category_id <= 0) {
    echo json_encode([], JSON_UNESCAPED_UNICODE);
    exit;
}

// ค้นหารายละเอียดรายจ่ายที่เคยคีย์ในหมวดนี้ (ไม่ซ้ำ) เรียงจากล่าสุด
$like = '%' . $q . '%';
$sql = "
    SELECT e.detail,
           MAX(e.expense_date) AS last_date,
           MAX(e.id)            AS last_id
    FROM expenses e
    WHERE e.category_id = ?
      AND e.is_deleted = 0
      AND e.detail <> ''
      AND e.detail IS NOT NULL
      AND e.detail LIKE ?
    GROUP BY e.detail
    ORDER BY last_date DESC, last_id DESC
    LIMIT 10
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['error' => $conn->error], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt->bind_param("is", $category_id, $like);
$stmt->execute();
$result = $stmt->get_result();

$details = [];
while ($row = $result->fetch_assoc()) {
    $details[] = $row['detail'];
}
$stmt->close();

echo json_encode($details, JSON_UNESCAPED_UNICODE);
?>
