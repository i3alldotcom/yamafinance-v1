<?php
include('../includes/auth.php');
check_login();
include('../config/db_connect.php');

// ดึงข้อมูลผู้ใช้จาก session
$user_id = $_SESSION['user_id'];

// โหลดข้อมูลผู้ใช้
$sql = "SELECT full_name, username FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($full_name, $username);
$stmt->fetch();
$stmt->close();

// เมื่อกดบันทึก
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_fullname = trim($_POST['full_name']);
    $new_username = trim($_POST['username']);
    $new_password = trim($_POST['password']);

    // ตรวจชื่อผู้ใช้ซ้ำ
    $check = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $check->bind_param("si", $new_username, $user_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $error = "ชื่อผู้ใช้นี้มีคนใช้แล้ว";
    } else {
        // อัปเดตข้อมูล
        if ($new_password !== "") {
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $update = $conn->prepare("
                UPDATE users 
                SET full_name = ?, username = ?, password_hash = ?, is_active = 1
                WHERE id = ?
            ");
            $update->bind_param("sssi", $new_fullname, $new_username, $password_hash, $user_id);
        } else {
            $update = $conn->prepare("
                UPDATE users 
                SET full_name = ?, username = ?, is_active = 1
                WHERE id = ?
            ");
            $update->bind_param("ssi", $new_fullname, $new_username, $user_id);
        }

        if ($update->execute()) {
            $success = "บันทึกข้อมูลเรียบร้อยแล้ว";
            $full_name = $new_fullname;
            $username = $new_username;
        } else {
            $error = "เกิดข้อผิดพลาด ไม่สามารถบันทึกข้อมูลได้";
        }

        $update->close();
    }

    $check->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ข้อมูลส่วนตัว</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body class="bg-light">
<?php include('../includes/navbar.php'); ?>

<div class="container mt-4">

    <h3>ข้อมูลส่วนตัว</h3>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <form method="POST" class="mt-3">

        <div class="mb-3">
            <label class="form-label">ชื่อ - นามสกุล</label>
            <input type="text" name="full_name" class="form-control" required
                   value="<?php echo htmlspecialchars($full_name); ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">ชื่อผู้ใช้</label>
            <input type="text" name="username" class="form-control" required
                   value="<?php echo htmlspecialchars($username); ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">รหัสผ่านใหม่ (ถ้าไม่เปลี่ยนให้เว้นว่าง)</label>
            <input type="password" name="password" class="form-control" placeholder="••••••••">
        </div>

        <button type="submit" class="btn btn-primary">บันทึกข้อมูล</button>
    </form>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
