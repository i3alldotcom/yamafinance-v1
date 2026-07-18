<?php
include('../includes/auth.php');
check_login();
include('../config/db_connect.php');

// ตั้งค่าการค้นหา + แบ่งหน้า + เรียงลำดับ
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'donation_date';
$order = isset($_GET['order']) ? strtoupper($_GET['order']) : 'DESC';
if (!in_array($order, ['ASC', 'DESC'])) $order = 'DESC';

// Validate sort parameter
$allowed_sorts = ['donation_date', 'receipt_name', 'merit_name', 'amount', 'item_code', 'comment'];
if (!in_array($sort, $allowed_sorts)) $sort = 'donation_date';

$allowed_limits = [10, 30, 50, 100];
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
if (!in_array($limit, $allowed_limits)) $limit = 10;

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// เงื่อนไขค้นหา (ชื่อบนใบโม / ชื่อบุญ / เลขอ้างอิง / หมายเหตุ)
$like = '%' . $q . '%';

// Helper สำหรับสร้าง URL เรียงลำดับ
function sort_url($column, $current_sort, $current_order, $q, $limit) {
    $new_order = ($current_sort === $column && $current_order === 'ASC') ? 'desc' : 'asc';
    $params = [
        'sort' => $column,
        'order' => $new_order,
        'limit' => $limit,
        'page' => 1, // reset page when sorting
    ];
    if ($q !== '') $params['q'] = $q;
    return '?' . http_build_query($params);
}

// Helper สำหรับไอคอนเรียงลำดับ
function sort_icon($column, $current_sort, $current_order) {
    if ($current_sort !== $column) {
        return '<i class="bi bi-arrow-down-up text-muted ms-1"></i>';
    }
    return $current_order === 'ASC'
        ? '<i class="bi bi-arrow-up text-primary ms-1"></i>'
        : '<i class="bi bi-arrow-down text-primary ms-1"></i>';
}

// Map column name to SQL column (ใช้ di ให้ตรงกับตาราง donation_items)
$sort_map = [
    'donation_date' => 'di.donation_date',
    'receipt_name'  => 'di.receipt_name',
    'merit_name'    => 'm.merit_name',
    'amount'        => 'di.amount',
    'item_code'     => 'di.item_code',
    'comment'       => 'di.comment',
];

// ดึงฟิลด์จัดเรียง ถ้าไม่มีให้ใช้ di.donation_date เป็นหลัก
$sort_column = isset($sort_map[$sort]) ? $sort_map[$sort] : 'di.donation_date';
$order_by = $sort_column . ' ' . $order . ', di.id ' . $order;

// นับจำนวนทั้งหมด
if ($q !== '') {
    $count_stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM donation_items di
        LEFT JOIN merits m ON di.merit_id = m.id
        WHERE di.is_deleted = 0
          AND (di.receipt_name LIKE ? OR m.merit_name LIKE ? OR di.item_code LIKE ? OR di.comment LIKE ?)
    ");
    $count_stmt->bind_param("ssss", $like, $like, $like, $like);
} else {
    // แก้ไขให้นับจากตารางตรงๆ ป้องกัน Ambiguous Column
    $count_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM donation_items WHERE is_deleted = 0");
}
$count_stmt->execute();
$total_rows = (int)$count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();
$total_pages = max(1, ceil($total_rows / $limit));

// ดึงข้อมูล
if ($q !== '') {
    $stmt = $conn->prepare("
        SELECT di.id, di.donation_date, di.receipt_name,
               m.merit_name, di.amount, di.item_code, di.comment
        FROM donation_items di
        LEFT JOIN merits m ON di.merit_id = m.id
        WHERE di.is_deleted = 0
          AND (di.receipt_name LIKE ? OR m.merit_name LIKE ? OR di.item_code LIKE ? OR di.comment LIKE ?)
        ORDER BY $order_by
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("ssssii", $like, $like, $like, $like, $limit, $offset);
} else {
    $stmt = $conn->prepare("
        SELECT di.id, di.donation_date, di.receipt_name,
               m.merit_name, di.amount, di.item_code, di.comment
        FROM donation_items di
        LEFT JOIN merits m ON di.merit_id = m.id
        WHERE di.is_deleted = 0
        ORDER BY $order_by
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("ii", $limit, $offset);
}
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ระบบรับบริจาค</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body class="bg-light">

<?php include('../includes/navbar.php'); ?>

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>รายการบริจาคล่าสุด</h3>

        <a href="donation_add.php" class="btn btn-success">
            <i class="bi bi-plus-circle"></i> ทำรายการใหม่
        </a>
    </div>

    <!-- ค้นหา + จำนวนต่อหน้า -->
    <form class="row g-2 mb-3" method="get" action="donations.php">
        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
        <input type="hidden" name="order" value="<?php echo strtolower($order); ?>">
        <div class="col-md-6">
            <div class="input-group">
                <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" class="form-control"
                       placeholder="ค้นหา: ชื่อบนใบโม / ชื่อบุญ / เลขอ้างอิง / หมายเหตุ">
                <button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i> ค้นหา</button>
                <?php if ($q !== ''): ?>
                <a href="donations.php?limit=<?php echo $limit; ?>&sort=<?php echo $sort; ?>&order=<?php echo strtolower($order); ?>" class="btn btn-outline-secondary">ล้าง</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-3 offset-md-3">
            <div class="input-group">
                <span class="input-group-text">แสดง</span>
                <select name="limit" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($allowed_limits as $opt): ?>
                    <option value="<?php echo $opt; ?>" <?php echo $limit===$opt?'selected':''; ?>><?php echo $opt; ?> รายการ</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </form>
    <div class="text-muted mb-2">พบ <?php echo number_format($total_rows); ?> รายการ</div>

    <table class="table table-bordered table-striped">
        <thead class="table-primary">
            <tr>
                <th>
                    <a href="<?php echo sort_url('donation_date', $sort, $order, $q, $limit); ?>" class="text-dark text-decoration-none">
                        วันที่บริจาค <?php echo sort_icon('donation_date', $sort, $order); ?>
                    </a>
                </th>
                <th>
                    <a href="<?php echo sort_url('receipt_name', $sort, $order, $q, $limit); ?>" class="text-dark text-decoration-none">
                        ชื่อบนใบโม <?php echo sort_icon('receipt_name', $sort, $order); ?>
                    </a>
                </th>
                <th>
                    <a href="<?php echo sort_url('merit_name', $sort, $order, $q, $limit); ?>" class="text-dark text-decoration-none">
                        ชื่อบุญ <?php echo sort_icon('merit_name', $sort, $order); ?>
                    </a>
                </th>
                <th>
                    <a href="<?php echo sort_url('item_code', $sort, $order, $q, $limit); ?>" class="text-dark text-decoration-none">
                        เลขอ้างอิง <?php echo sort_icon('item_code', $sort, $order); ?>
                    </a>
                </th>
                <th class="text-end">
                    <a href="<?php echo sort_url('amount', $sort, $order, $q, $limit); ?>" class="text-dark text-decoration-none">
                        จำนวนเงิน <?php echo sort_icon('amount', $sort, $order); ?>
                    </a>
                </th>
                <th>แก้ไข</th>
                <th>ลบ</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['donation_date']); ?></td>
                    <td><?php echo htmlspecialchars($row['receipt_name']); ?></td>
                    <td>
                        <?php echo htmlspecialchars($row['merit_name']); ?>
                        <?php if (!empty($row['comment'])): ?>
                            <div class="text-secondary" style="font-size: 0.9rem; white-space: pre-line;"><?php echo htmlspecialchars($row['comment']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($row['item_code']); ?></td>
                    <td class="text-end"><?php echo number_format($row['amount'], 2); ?></td>

                    <td>
                        <button class="btn btn-warning btn-sm" onclick="editDonation(<?php echo $row['id']; ?>)">
                            <i class="bi bi-pencil-square"></i>
                        </button>
                    </td>

                    <td>
                        <button class="btn btn-danger btn-sm" onclick="deleteDonation(<?php echo $row['id']; ?>)">
                            <i class="bi bi-trash-fill"></i>
                        </button>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <nav>
        <ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&limit=<?php echo $limit; ?>&q=<?php echo urlencode($q); ?>&sort=<?php echo $sort; ?>&order=<?php echo strtolower($order); ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
function deleteDonation(id) {
    if (confirm("ต้องการลบรายการนี้ใช่หรือไม่?")) {
        fetch("donation_delete_ajax.php?id=" + id)
            .then(response => response.text())
            .then(data => {
                if (data === "OK") {
                    location.reload();
                } else {
                    alert(data);
                }
            });
    }
}

function editDonation(id) {
    window.location.href = "donation_edit.php?id=" + id;
}
</script>

</body>
</html>
