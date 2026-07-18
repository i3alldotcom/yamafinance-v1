<nav class="navbar navbar-expand-lg navbar-dark" style="background-color:#1e3d59;">
    <div class="container-fluid">

        <a class="navbar-brand text-warning" href="../public/index.php">
            วัดพระธรรมกายยามานาชิ
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">

            <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                <li class="nav-item">
                    <a class="nav-link text-warning" href="../public/index.php">หน้าหลัก</a>
                </li>

                <!-- ระบบบริจาค -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-warning" href="#" data-bs-toggle="dropdown">
                        ระบบบริจาค
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="../public/donations.php">เข้าสู่ระบบบริจาค</a></li>
                        <li><a class="dropdown-item" href="../public/donors.php">จัดการผู้บริจาค</a></li>
                        <li><a class="dropdown-item" href="../public/merits.php">รายการบุญ (Merits)</a></li>
                    </ul>
                </li>

                <!-- ระบบรายจ่าย -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-warning" href="#" data-bs-toggle="dropdown">
                        ระบบรายจ่าย
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="../public/expenses.php">เข้าสู่ระบบรายจ่าย</a></li>
                        <li><a class="dropdown-item" href="../public/expense_categories.php">ประเภทค่าใช้จ่าย</a></li>
                        <li><a class="dropdown-item" href="../public/vendors.php">ร้านค้า / บริษัท</a></li>
                    </ul>
                </li>

                <!-- สรุปรายรับ -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-warning" href="#" data-bs-toggle="dropdown">
                        สรุปรายรับ
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="../public/summary_income.php">สรุปรายเดือน / ปี</a></li>
                        <li><a class="dropdown-item" href="../public/summary_by_merit.php">สรุปตามบุญ</a></li>
                        <li><a class="dropdown-item" href="../public/summary_by_donor.php">สรุปตามผู้นำบุญ</a></li>
                    </ul>
                </li>

                <!-- สรุปรายจ่าย -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-warning" href="#" data-bs-toggle="dropdown">
                        สรุปรายจ่าย
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="../public/summary_expenses.php">สรุปรายเดือน / ปี</a></li>
                        <li><a class="dropdown-item" href="../public/summary_by_category.php">สรุปตามประเภท</a></li>
                        <li><a class="dropdown-item" href="../public/balance.php">สรุปรายรับ-รายจ่าย</a></li>
                    </ul>
                </li>

                <!-- จัดการผู้ใช้ -->
                <li class="nav-item">
                    <a class="nav-link text-warning" href="../public/users.php">จัดการผู้ใช้</a>
                </li>

            </ul>

            <!-- เมนูผู้ใช้ล็อกอิน -->
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" style="color:#d4af37;" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i>
                        <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="../public/profile.php">จัดการข้อมูลส่วนตัว</a></li>
                        <li><a class="dropdown-item" href="../public/logout.php">ออกจากระบบ</a></li>
                    </ul>
                </li>
            </ul>

        </div>
    </div>
</nav>
