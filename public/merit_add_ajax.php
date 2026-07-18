<?php
header('Content-Type: application/json; charset=utf-8');
include('../includes/auth.php');
check_login();
include('../config/db_connect.php');

// รับค่าจากฟอร์ม
$merit_name = trim($_POST['merit_name']);

if ($merit_name == "") {
    echo json_encode(["status" => "ERROR", "message" => "กรุณากรอกชื่อบุญ"]);
    exit;
}

$merit_name = mysqli_real_escape_string($conn, $merit_name);

// บันทึกลงฐานข้อมูล
$sql = "INSERT INTO merits (merit_name, disable) VALUES ('$merit_name', 0)";

if ($conn->query($sql)) {

    echo json_encode([
        "status" => "OK",
        "id" => $conn->insert_id,
        "merit_name" => $merit_name
    ]);

} else {
    echo json_encode([
        "status" => "ERROR",
        "message" => $conn->error
    ]);
}
?>
