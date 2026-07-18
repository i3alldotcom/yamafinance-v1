<?php
include('../config/db_connect.php');

// ดึงรายการบุญที่เปิดใช้งาน (disable = 0)
$sql = "SELECT id, merit_name FROM merits WHERE disable = 0 ORDER BY merit_name";
$result = $conn->query($sql);

$merits = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $merits[] = $row;
    }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($merits);
?>
