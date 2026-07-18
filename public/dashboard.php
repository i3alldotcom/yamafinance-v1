<?php
include('../includes/auth.php');
check_login();

$role = $_SESSION['role'];
$full_name = $_SESSION['full_name'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>หน้าหลักระบบวัด</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body class="bg-light">

<?php include('../includes/navbar.php'); ?>

<div class="container mt-4">
    <h3 class="mb-4">เมนูหลัก</h3>

    <div class="row g-4">

        <!-- ทุกคนเห็น -->
        <div class="col-md-4">
            <div class="card shadow-sm text-center h-100">
                <div class="card-body d-flex flex-column justify-content-center">
                    <i class="bi bi-heart-fill text-danger" style="font-size:3rem;"></i>
                    <h5 class="mt-3">ระบบบริจาค</h5>
                    <a href="donations.php" class="btn btn-primary w-100 mt-auto">เข้าสู่ระบบบริจาค</a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm text-center h-100">
                <div class="card-body d-flex flex-column justify-content-center">
                    <i class="bi bi-cash-coin text-success" style="font-size:3rem;"></i>
                    <h5 class="mt-3">ระบบรายจ่าย</h5>
                    <a href="expenses.php" class="btn btn-success w-100 mt-auto">เข้าสู่ระบบรายจ่าย</a>
                </div>
            </div>
        </div>

        <?php if ($role === 'admin' || $role === 'accountant'): ?>

        <div class="col-md-4">
            <div class="card shadow-sm text-center h-100">
                <div class="card-body d-flex flex-column justify-content-center">
                    <i class="bi bi-people-fill text-primary" style="font-size:3rem;"></i>
                    <h5 class="mt-3">จัดการผู้บริจาค</h5>
                    <a href="donors.php" class="btn btn-secondary w-100 mt-auto">จัดการผู้บริจาค</a>
                </div>
            </div>
        </div>

        <!-- การ์ดรวม: จัดการข้อมูล -->
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-center mb-3">
                        <i class="bi bi-database-fill-gear text-secondary" style="font-size:3rem;"></i>
                        <h5 class="mt-2">จัดการข้อมูล</h5>
                    </div>

                    <!-- ประเภทบุญ -->
                    <div class="mb-2">
                        <div class="text-muted small fw-semibold mb-1">
                            <i class="bi bi-journal-bookmark-fill text-warning me-1"></i>
                            ประเภทบุญ
                        </div>
                        <a href="merits.php" class="btn btn-outline-warning w-100 btn-sm">
                            จัดการประเภทบุญ
                        </a>
                    </div>

                    <hr class="my-2">

                    <!-- ประเภทรายจ่าย -->
                    <div class="mb-2">
                        <div class="text-muted small fw-semibold mb-1">
                            <i class="bi bi-list-check text-info me-1"></i>
                            ประเภทรายจ่าย
                        </div>
                        <a href="expense_categories.php" class="btn btn-outline-info w-100 btn-sm">
                            จัดการประเภทรายจ่าย
                        </a>
                    </div>

                    <hr class="my-2">

                    <!-- ข้อมูลบริษัท/ร้านค้า (รายจ่าย) -->
                    <div class="mb-0">
                        <div class="text-muted small fw-semibold mb-1">
                            <i class="bi bi-building text-dark me-1"></i>
                            ข้อมูลบริษัท / ร้านค้า
                        </div>
                        <a href="vendors.php" class="btn btn-outline-dark w-100 btn-sm">
                            จัดการข้อมูลบริษัท (รายจ่าย)
                        </a>
                    </div>

                </div>
            </div>
        </div>

        <!-- การ์ดรวม: สรุปรายรับ -->
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-center mb-3">
                        <i class="bi bi-graph-up-arrow text-success" style="font-size:3rem;"></i>
                        <h5 class="mt-2">สรุปรายรับ</h5>
                    </div>

                    <div class="mb-2">
                        <div class="text-muted small fw-semibold mb-1">
                            <i class="bi bi-calendar3 text-success me-1"></i>
                            ตามช่วงเวลา
                        </div>
                        <a href="summary_by_merit.php" class="btn btn-outline-success w-100 btn-sm">
                            สรุปรายเดือน / ปี
                        </a>
                    </div>
<!--
                    <hr class="my-2">

                    <div class="mb-2">
                        <div class="text-muted small fw-semibold mb-1">
                            <i class="bi bi-journal-bookmark-fill text-warning me-1"></i>
                            ตามประเภทบุญ
                        </div>
                        <a href="summary_by_merit.php" class="btn btn-outline-warning w-100 btn-sm">
                            สรุปรายบุญ
                        </a>
                    </div>
        -->
                    <hr class="my-2">

                    <div class="mb-0">
                        <div class="text-muted small fw-semibold mb-1">
                            <i class="bi bi-person-heart text-primary me-1"></i>
                            ตามผู้นำบุญ
                        </div>
                        <a href="summary_by_donor.php" class="btn btn-outline-primary w-100 btn-sm">
                            สรุปตามผู้นำบุญ
                        </a>
                    </div>

                </div>
            </div>
        </div>

        <!-- การ์ดรวม: สรุปรายจ่าย -->
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-center mb-3">
                        <i class="bi bi-graph-down-arrow text-danger" style="font-size:3rem;"></i>
                        <h5 class="mt-2">สรุปรายจ่าย</h5>
                    </div>

                    <div class="mb-2">
                        <div class="text-muted small fw-semibold mb-1">
                            <i class="bi bi-calendar3 text-danger me-1"></i>
                            ตามช่วงเวลา
                        </div>
                        <a href="summary_expenses.php" class="btn btn-outline-danger w-100 btn-sm">
                            สรุปรายเดือน / ปี
                        </a>
                    </div>

                    <hr class="my-2">

                    <div class="mb-0">
                        <div class="text-muted small fw-semibold mb-1">
                            <i class="bi bi-list-check text-info me-1"></i>
                            ตามประเภทรายจ่าย
                        </div>
                        <a href="summary_by_category.php" class="btn btn-outline-info w-100 btn-sm">
                            สรุปตามประเภท
                        </a>
                    </div>

                </div>
            </div>
        </div>
        <!-- การ์ดรวม: สรุปการเงิน -->
        <div class="col-md-4">
            <div class="card shadow-sm text-center h-100">
                <div class="card-body d-flex flex-column justify-content-center">
                    <i class="bi bi-wallet2 text-warning" style="font-size:3rem;"></i>
                    <h5 class="mt-3">สรุปการเงิน</h5>
                    <a href="balance.php" class="btn btn-warning w-100 mt-auto">เข้าสู่สรุปการเงิน</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($role === 'admin'): ?>
        <div class="col-md-4">
            <div class="card shadow-sm text-center h-100">
                <div class="card-body d-flex flex-column justify-content-center">
                    <i class="bi bi-person-gear text-dark" style="font-size:3rem;"></i>
                    <h5 class="mt-3">จัดการผู้ใช้</h5>
                    <a href="users.php" class="btn btn-dark w-100 mt-auto">จัดการผู้ใช้</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>