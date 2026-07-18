<?php

include('../includes/auth.php');
check_login();
include('../config/db_connect.php');

$id = intval($_POST['id']);
if ($id <= 0) die("Invalid ID");

$donation_date = $_POST['donation_date'];
$donor_id      = intval($_POST['donor_id']);
$receipt_name  = trim($_POST['receipt_name']);
$merit_id      = intval($_POST['merit_id']);
$amount        = floatval($_POST['amount']);
$comment       = trim($_POST['comment']);
$item_code_old = trim($_POST['item_code']);

if (!$donor_id || !$merit_id || !$donation_date || !$receipt_name) {
    die("ข้อมูลไม่ครบ");
}

// ดึง item_code เดิม
$sql_old = "SELECT item_code FROM donation_items WHERE id = ?";
$stmt_old = $conn->prepare($sql_old);
$stmt_old->bind_param("i", $id);
$stmt_old->execute();
$res_old = $stmt_old->get_result();
$row_old = $res_old->fetch_assoc();
$item_code_db = $row_old ? $row_old['item_code'] : $item_code_old;
$stmt_old->close();

// แยกส่วนท้าย XX
$parts = explode('-', $item_code_db);
$suffix = isset($parts[1]) ? $parts[1] : '01';

// สร้าง item_code ใหม่
$item_code_new = $donor_id . '/' . $merit_id . '-' . $suffix;

// อัปเดตข้อมูล
$sql_update = "
    UPDATE donation_items
    SET donor_id = ?, merit_id = ?, donation_date = ?, receipt_name = ?, 
        amount = ?, comment = ?, item_code = ?, last_update = NOW()
    WHERE id = ?
";

$stmt = $conn->prepare($sql_update);
$stmt->bind_param(
    "iissdssi",
    $donor_id,
    $merit_id,
    $donation_date,
    $receipt_name,
    $amount,
    $comment,
    $item_code_new,
    $id
);

if ($stmt->execute()) {

    // ดึงข้อมูลล่าสุดหลังบันทึก
    $sql_new = "
        SELECT di.*, d.full_name, m.merit_name
        FROM donation_items di
        LEFT JOIN donors d ON di.donor_id = d.id
        LEFT JOIN merits m ON di.merit_id = m.id
        WHERE di.id = $id
    ";
    $res_new = $conn->query($sql_new);
    $new = $res_new->fetch_assoc();

    echo "<h3>บันทึกข้อมูลเรียบร้อย</h3>";
    echo "<p>วันที่บริจาค: <strong>{$new['donation_date']}</strong></p>";
    echo "<p>ผู้นำบุญ: <strong>{$new['full_name']} (ID: {$new['donor_id']})</strong></p>";
    echo "<p>ชื่อบุญ: <strong>{$new['merit_name']} (ID: {$new['merit_id']})</strong></p>";
    echo "<p>ชื่อบนใบโม: <strong>{$new['receipt_name']}</strong></p>";

    echo "<table border='1' cellpadding='5' cellspacing='0' style='margin-top:15px;'>";
    echo "<tr>
            <th>จำนวนเงิน</th>
            <th>เลขอ้างอิงระบบ</th>
            <th>หมายเหตุ</th>
          </tr>";

    echo "<tr>";
    echo "<td>" . number_format($new['amount'], 2) . "</td>";
    echo "<td><strong>{$new['item_code']}</strong></td>";
    echo "<td>{$new['comment']}</td>";
    echo "</tr>";
    echo "</table>";

    echo "<p style='margin-top:15px;'>กรุณาตรวจสอบข้อมูลด้านบนก่อนกลับไปหน้ารายการรับบริจาค</p>";
    echo "<p><a href='donations.php' class='btn btn-primary'>กลับไปหน้ารายการรับบริจาค</a></p>";

    exit; // ❗ หยุดไม่ให้โค้ดส่วนล่างทำงาน
}


$stmt->close();
$conn->close();
?>
