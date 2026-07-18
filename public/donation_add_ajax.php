<?php
header('Content-Type: application/json; charset=utf-8');
include('../includes/auth.php');
check_login();
include('../config/db_connect.php');

$full_name = trim($_POST['full_name']);

if ($full_name == "") {
    echo json_encode(["status" => "ERROR", "message" => "กรุณากรอกชื่อ"]);
    exit;
}

$full_name = mysqli_real_escape_string($conn, $full_name);

$sql = "INSERT INTO donors (full_name, is_deleted) VALUES ('$full_name', 0)";

if ($conn->query($sql)) {
    echo json_encode([
        "status" => "OK",
        "id" => $conn->insert_id,
        "full_name" => $full_name
    ]);
} else {
    echo json_encode(["status" => "ERROR", "message" => $conn->error]);
}
?>
