<?php
session_start();

// ถ้าล็อกอินแล้ว ให้ไปหน้า dashboard ทันที
if (isset($_SESSION['user_id'])) {
    header("Location: ../public/dashboard.php");
    exit;
}

include('../config/db_connect.php');

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {

        if ($row['is_active'] == 0) {
            $error = "บัญชีนี้ถูกปิดใช้งาน";

        } elseif (password_verify($password, $row['password_hash'])) {

            // เก็บ session
            $_SESSION['user_id']   = $row['id'];
            $_SESSION['role']      = $row['role'];
            $_SESSION['full_name'] = $row['full_name'];

            header("Location: ../public/dashboard.php");
            exit;

        } else {
            $error = "รหัสผ่านไม่ถูกต้อง";
        }

    } else {
        $error = "ไม่พบชื่อผู้ใช้";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เข้าสู่ระบบ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5" style="max-width: 400px;">
    <div class="card shadow">
        <div class="card-body">
            <h4 class="text-center mb-4">เข้าสู่ระบบ</h4>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">ชื่อผู้ใช้</label>
                    <input type="text" name="username" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">รหัสผ่าน</label>
                    <input type="password" name="password" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-primary w-100">เข้าสู่ระบบ</button>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
