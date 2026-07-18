<?php
include('../includes/auth.php');
check_login();
include('../config/db_connect.php');

$id = intval($_GET['id']);

$sql = "
    SELECT e.*, c.name AS category_name, v.vendor_name
    FROM expenses e
    LEFT JOIN expense_categories c ON e.category_id = c.id
    LEFT JOIN vendors v ON e.vendor_id = v.id
    WHERE e.id = $id
";
$res = $conn->query($sql);
$data = $res->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>บันทึกรายจ่ายสำเร็จ</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
<?php include('../includes/navbar.php'); ?>

<div class="container mt-4">

    <h3 class="text-success">บันทึกรายจ่ายสำเร็จ!</h3>

    <div class="card p-3">

        <p><strong>วันที่:</strong> <?php echo $data['expense_date']; ?></p>
        <p><strong>ประเภทรายจ่าย:</strong> <?php echo htmlspecialchars($data['category_name']); ?></p>
        <p><strong>ร้านค้า:</strong> <?php echo htmlspecialchars($data['vendor_name']); ?></p>
        <p><strong>รายละเอียด:</strong> <?php echo nl2br(htmlspecialchars($data['detail'])); ?></p>
        <p><strong>จำนวนเงิน:</strong> ¥<?php echo number_format($data['amount']); ?></p>

        <hr>

        <h4 class="text-primary">รหัสบิล: <?php echo $data['expense_code']; ?></h4>

    </div>

    <a href="expenses.php" class="btn btn-secondary mt-3">กลับไปหน้ารายจ่าย</a>
    <a href="expense_add.php" class="btn btn-success mt-3">ทำรายการใหม่</a>

</div>

</body>
</html>
