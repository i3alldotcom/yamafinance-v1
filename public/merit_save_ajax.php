<?php
include('../config/db_connect.php');
header('Content-Type: application/json; charset=utf-8');

// ตรวจสอบว่ามีข้อมูลส่งมาหรือไม่
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $merit_name = trim($_POST['merit_name']);

    if ($merit_name === '') {
        echo json_encode([
            'status' => 'ERROR',
            'message' => 'กรุณากรอกชื่อบุญ'
        ]);
        exit;
    }

    // ตรวจสอบว่ามีชื่อบุญนี้อยู่แล้วหรือไม่
    $check = $conn->prepare("SELECT id FROM merits WHERE merit_name = ? AND disable = 0 LIMIT 1");
    $check->bind_param("s", $merit_name);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        echo json_encode([
            'status' => 'ERROR',
            'message' => 'ชื่อบุญนี้มีอยู่แล้ว'
        ]);
        exit;
    }

    // บันทึกข้อมูลใหม่
    $stmt = $conn->prepare("INSERT INTO merits (merit_name, disable) VALUES (?, 0)");
    $stmt->bind_param("s", $merit_name);

    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'OK',
            'id' => $stmt->insert_id,
            'merit_name' => $merit_name
        ]);
    } else {
        echo json_encode([
            'status' => 'ERROR',
            'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล'
        ]);
    }

    $stmt->close();
    $check->close();
    $conn->close();

} else {
    echo json_encode([
        'status' => 'ERROR',
        'message' => 'Invalid request method'
    ]);
}
?>
