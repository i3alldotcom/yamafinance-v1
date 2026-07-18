<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

include('../includes/auth.php');
check_login();
include('../config/db_connect.php');
/*
echo "<pre>";
print_r($_POST);
echo "</pre>";
*/

$donor_id = intval($_POST['donor_id']);
$donation_date = $_POST['donation_date'];

/*echo "donor_id: $donor_id <br>";
echo "donation_date: $donation_date";
*/
if ($donor_id <= 0) {
    die("ข้อมูลไม่ครบ");
}

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เพิ่มรายการบริจาค</title>

    <!-- jQuery ต้องมาก่อน Bootstrap Select -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Bootstrap + Bootstrap Select -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.14.0-beta3/dist/css/bootstrap-select.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.14.0-beta3/dist/js/bootstrap-select.min.js"></script>
</head>

<body class="bg-light">
<?php
include('../includes/navbar.php');

$stmt2 = $conn->prepare("
    INSERT INTO donation_items 
    (donor_id, receipt_name, merit_id, amount, donation_date, item_code, comment, create_by, last_update, is_deleted, deleted_by)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 0, NULL)
");

$item_codes = []; // เพิ่มให้ชัดเจนกัน notice

foreach ($_POST['items'] as $index => $item) {
    $receipt_name = $item['receipt_name'];
    $merit_id     = intval($item['merit_id']);
    $amount       = floatval($item['amount']);
    $comment      = $item['comment'];

    $result = $conn->query("SELECT IFNULL(MAX(id),0)+1 AS next_id FROM donation_items");
    $row = $result->fetch_assoc();
    $next_id = $row['next_id'];

    $item_code = sprintf("%d/%d-%d", $donor_id, $merit_id, $next_id);

    $created_by = $_SESSION['user_id'];

    $stmt2->bind_param(
        "isidsssi",
        $donor_id,
        $receipt_name,
        $merit_id,
        $amount,
        $donation_date,
        $item_code,
        $comment,
        $created_by
    );
    $stmt2->execute();

    // เก็บข้อความสรุปไว้ (เพิ่ม merit_id เข้าไปด้วย)
    $item_codes[] = [
        'no'          => $index + 1,
        'merit_id'    => $merit_id,
        'receipt_name'=> $receipt_name,
        'amount'      => $amount,
        'item_code'   => $item_code,
    ];
}

$stmt2->close();

/**
 * ดึงชื่อบุญทั้งหมดจาก merits มาเก็บใน array ก่อนปิด connection
 */
$merit_map = [];
$merit_rs = $conn->query("SELECT id, merit_name FROM merits");
while ($m = $merit_rs->fetch_assoc()) {
    $merit_map[$m['id']] = $m['merit_name'];
}

$conn->close();

// ไม่ redirect ทันที แสดงสรุปก่อน
echo "<h3>บันทึกข้อมูลเรียบร้อย</h3>";
echo "<p>วันที่บริจาค: {$donation_date}</p>";
echo "<p>ผู้นำบุญ ID: {$donor_id}</p>";

echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr>
        <th>ลำดับ</th>
        <th>รายการทำบุญ</th>
        <th>จำนวนเงิน</th>
        <th>เลขอ้างอิงระบบ</th>
      </tr>";
$total_amount = 0;
foreach ($item_codes as $row) {

    // ใช้ merit_id ไปหา merit_name จาก array ที่โหลดไว้
    if (isset($merit_map[$row['merit_id']])) {
        $merit_name = $merit_map[$row['merit_id']];
    } else {
        $merit_name = $row['receipt_name']; // fallback กันกรณีไม่พบใน merits
    }

    echo "<tr>";
    echo "<td>{$row['no']}</td>";
    echo "<td>{$merit_name}</td>";
    echo "<td>" . number_format($row['amount'], 2) . "</td>";
    echo "<td>{$row['item_code']}</td>";
    echo "</tr>";
    $total_amount += $row['amount'];
}

echo "</table>";
echo "<p><strong>รวมยอดเงินทั้งหมด: " . number_format($total_amount, 2) . " เยน</strong></p>";
echo "<p>กรุณาเขียนเลขอ้างอิงระบบลงในใบรับบริจาคตามรายการด้านบน</p>";
echo "<p><a href='donations.php' class='btn btn-secondary mt-3'>กลับไปหน้ารายการรับบริจาค</a> <a href='donation_add.php' class='btn btn-success mt-3'>เพิ่มรายการรับบริจาค</a></p>";
?>
</body>
</html>
<?php
exit;
?>
