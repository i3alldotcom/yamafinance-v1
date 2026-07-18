<?php
// ต้องไม่มีช่องว่างหรือ BOM ก่อนบรรทัดนี้
header('Content-Type: application/json; charset=utf-8');
include('../config/db_connect.php'); // ถ้าไฟล์นี้มี echo ให้ลบออก
include('../includes/auth.php');
check_login();

// helper: check if a column exists
function has_column($conn, $table, $column) {
    $sql = "SELECT COUNT(*) AS cnt FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '$table' AND column_name = '$column'";
    $res = $conn->query($sql);
    if (!$res) return false;
    $r = $res->fetch_assoc();
    return (int)$r['cnt'] > 0;
}

$full_name = trim($_POST['full_name']);
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$line_id = isset($_POST['line_id']) ? trim($_POST['line_id']) : '';
$birth_date = isset($_POST['birth_date']) ? trim($_POST['birth_date']) : '';

if ($full_name == "") {
    echo json_encode(["status" => "ERROR", "message" => "กรุณากรอกชื่อ"]);
    exit;
}

$full_name = mysqli_real_escape_string($conn, $full_name);
$phone = mysqli_real_escape_string($conn, $phone);
$line_id = mysqli_real_escape_string($conn, $line_id);
$birth_date = mysqli_real_escape_string($conn, $birth_date);

$hasIsDeleted = has_column($conn, 'donors', 'is_deleted');
if ($hasIsDeleted) {
    $sql = "INSERT INTO donors (full_name, phone, line_id, birth_date, is_deleted) VALUES ('$full_name', '$phone', '$line_id', '$birth_date', 0)";
} else {
    $sql = "INSERT INTO donors (full_name, phone, line_id, birth_date) VALUES ('$full_name', '$phone', '$line_id', '$birth_date')";
}

if ($conn->query($sql)) {
    $result = [
        "status" => "OK",
        "id" => $conn->insert_id,
        "full_name" => $full_name
    ];
    echo json_encode($result);
} else {
    echo json_encode(["status" => "ERROR", "message" => $conn->error]);
}
?>
