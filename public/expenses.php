<?php
include('../includes/auth.php');
check_login();
include('../config/db_connect.php');

// ตั้งค่าการค้นหา + แบ่งหน้า + เรียงลำดับ
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'expense_date';
$order = isset($_GET['order']) && strtolower($_GET['order']) === 'asc' ? 'ASC' : 'DESC';

$allowed_sorts = ['expense_date', 'expense_code', 'category_name', 'vendor_name', 'detail', 'amount', 'payment_method'];
if (!in_array($sort, $allowed_sorts)) $sort = 'expense_date';

$allowed_limits = [10, 30, 50, 100];
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
if (!in_array($limit, $allowed_limits)) $limit = 10;

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

$like = '%' . $q . '%';

// Helper สำหรับสร้าง URL เรียงลำดับ
function sort_url($column, $current_sort, $current_order, $q, $limit, $page) {
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

// Map column name to SQL column
$sort_map = [
    'expense_date' => 'e.expense_date',
    'expense_code' => 'e.expense_code',
    'category_name' => 'c.name',
    'vendor_name' => 'v.vendor_name',
    'detail' => 'e.detail',
    'amount' => 'e.amount',
    'payment_method' => 'e.payment_method',
];
$order_by = $sort_map[$sort] . ' ' . $order . ', e.id ' . $order;

// นับจำนวนทั้งหมด
if ($q !== '') {
    $count_stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM expenses e
        LEFT JOIN vendors v ON e.vendor_id = v.id
        LEFT JOIN expense_categories c ON e.category_id = c.id
        WHERE e.is_deleted = 0
          AND (e.detail LIKE ? OR e.expense_code LIKE ? OR v.vendor_name LIKE ? OR c.name LIKE ?)
    ");
    $count_stmt->bind_param("ssss", $like, $like, $like, $like);
} else {
    $count_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM expenses WHERE is_deleted = 0");
}
$count_stmt->execute();
$total_rows = (int)$count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();
$total_pages = max(1, ceil($total_rows / $limit));

// ดึงข้อมูล
if ($q !== '') {
    $stmt = $conn->prepare("
        SELECT e.id, e.expense_date, e.expense_code, e.detail, e.amount,
               v.vendor_name, c.name AS category_name, e.payment_method, e.uploaded
        FROM expenses e
        LEFT JOIN vendors v ON e.vendor_id = v.id
        LEFT JOIN expense_categories c ON e.category_id = c.id
        WHERE e.is_deleted = 0
          AND (e.detail LIKE ? OR e.expense_code LIKE ? OR v.vendor_name LIKE ? OR c.name LIKE ?)
        ORDER BY $order_by
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("ssssii", $like, $like, $like, $like, $limit, $offset);
} else {
    $stmt = $conn->prepare("
        SELECT e.id, e.expense_date, e.expense_code, e.detail, e.amount,
               v.vendor_name, c.name AS category_name, e.payment_method, e.uploaded
        FROM expenses e
        LEFT JOIN vendors v ON e.vendor_id = v.id
        LEFT JOIN expense_categories c ON e.category_id = c.id
        WHERE e.is_deleted = 0
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
<title>ระบบบันทึกรายจ่าย</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body class="bg-light">
<?php include('../includes/navbar.php'); ?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>รายการรายจ่ายล่าสุด</h3>
        <a href="expense_add.php" class="btn btn-success">
            <i class="bi bi-plus-circle"></i> บันทึกรายจ่ายใหม่
        </a>
    </div>

    <!-- ค้นหา + จำนวนต่อหน้า -->
    <form class="row g-2 mb-3" method="get" action="expenses.php">
        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
        <input type="hidden" name="order" value="<?php echo strtolower($order); ?>">
        <div class="col-md-6">
            <div class="input-group">
                <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" class="form-control"
                       placeholder="ค้นหา: รายละเอียด / รหัสบิล / ร้านค้า / ประเภท">
                <button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i> ค้นหา</button>
                <?php if ($q !== ''): ?>
                <a href="expenses.php?limit=<?php echo $limit; ?>&sort=<?php echo $sort; ?>&order=<?php echo strtolower($order); ?>" class="btn btn-outline-secondary">ล้าง</a>
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
        <thead class="table-danger">
    <tr>
        <th>
            <a href="<?php echo sort_url('expense_date', $sort, $order, $q, $limit, $page); ?>" class="text-dark text-decoration-none">
                วันที่รายจ่าย <?php echo sort_icon('expense_date', $sort, $order); ?>
            </a>
        </th>
        <th>
            <a href="<?php echo sort_url('expense_code', $sort, $order, $q, $limit, $page); ?>" class="text-dark text-decoration-none">
                รหัสบิล <?php echo sort_icon('expense_code', $sort, $order); ?>
            </a>
        </th>
        <th>
            <a href="<?php echo sort_url('category_name', $sort, $order, $q, $limit, $page); ?>" class="text-dark text-decoration-none">
                ประเภท <?php echo sort_icon('category_name', $sort, $order); ?>
            </a>
        </th>
        <th>
            <a href="<?php echo sort_url('vendor_name', $sort, $order, $q, $limit, $page); ?>" class="text-dark text-decoration-none">
                บริษัท / ร้านค้า <?php echo sort_icon('vendor_name', $sort, $order); ?>
            </a>
        </th>
        <th>
            <a href="<?php echo sort_url('detail', $sort, $order, $q, $limit, $page); ?>" class="text-dark text-decoration-none">
                รายละเอียด <?php echo sort_icon('detail', $sort, $order); ?>
            </a>
        </th>
        <th class="text-end">
            <a href="<?php echo sort_url('amount', $sort, $order, $q, $limit, $page); ?>" class="text-dark text-decoration-none">
                จำนวนเงิน <?php echo sort_icon('amount', $sort, $order); ?>
            </a>
        </th>
        <th>
            <a href="<?php echo sort_url('payment_method', $sort, $order, $q, $limit, $page); ?>" class="text-dark text-decoration-none">
                วิธีชำระ <?php echo sort_icon('payment_method', $sort, $order); ?>
            </a>
        </th>
        <th>ใบเสร็จ</th>
        <th>แก้ไข</th>
        <th>ลบ</th>
    </tr>
</thead>
<tbody>
<?php while ($row = $result->fetch_assoc()): ?>
    <tr>
        <td><?php echo htmlspecialchars($row['expense_date']); ?></td>
        <td><?php echo htmlspecialchars($row['expense_code']); ?></td>
        <td><?php echo htmlspecialchars($row['category_name']); ?></td>
        <td><?php echo htmlspecialchars($row['vendor_name']); ?></td>
        <td><?php echo htmlspecialchars($row['detail']); ?></td>
        <td class="text-end"><?php echo number_format($row['amount'], 2); ?></td>
        <td><?php echo htmlspecialchars($row['payment_method']); ?></td>

        <!-- ใบเสร็จ -->
        <td class="text-center">
            <?php if ($row['uploaded'] !== '0' && !empty($row['uploaded'])): ?>
                 <!-- ถ้ามีไฟล์แล้ว -->
                <button class="btn btn-outline-success btn-sm" onclick="viewReceipt('<?php echo $row['id']; ?>', '<?php echo $row['uploaded']; ?>')">
                    <i class="bi bi-receipt-cutoff"></i>
                </button>
            <?php else: ?>
                <!-- ถ้ายังไม่มีไฟล์ -->
                <button class="btn btn-outline-secondary btn-sm" onclick="uploadReceipt('<?php echo $row['id']; ?>')">
                    <i class="bi bi-cloud-upload"></i>
                </button>
            <?php endif; ?>
        </td>

        <td>
            <button class="btn btn-warning btn-sm" onclick="editExpense(<?php echo $row['id']; ?>)">
                <i class="bi bi-pencil-square"></i>
            </button>
        </td>
        <td>
            <button class="btn btn-danger btn-sm" onclick="deleteExpense(<?php echo $row['id']; ?>)">
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
function editExpense(id) {
     window.location.href = "expense_edit.php?id=" + id;
}
</script>

<script>
function deleteExpense(id) {
    if (!confirm("ต้องการลบรายการนี้ใช่ไหม?")) return;

    fetch("expense_delete_ajax.php?id=" + id)
        .then(response => response.json())
        .then(data => {
            if (data.status === "OK") {
                // ใช้ setTimeout เพื่อให้ alert แสดงก่อน reload
                alert("ลบรายการสำเร็จ");
                setTimeout(() => location.reload(), 300);
            } else {
                alert("เกิดข้อผิดพลาด: " + data.message);
            }
        })
        .catch(error => {
            alert("เกิดข้อผิดพลาดในการเชื่อมต่อ: " + error);
        });
}
function viewReceipt(id, filename) {
    const imgPath = `../uploads/receipts/${filename}`;
    document.getElementById('receiptImage').src = imgPath;

    const modal = new bootstrap.Modal(document.getElementById('viewReceiptModal'));
    modal.show();
}


function uploadReceipt(id) {
    document.getElementById('expense_id').value = id;
    const modal = new bootstrap.Modal(document.getElementById('uploadReceiptModal'));
    modal.show();
}

document.addEventListener('DOMContentLoaded', function() {
    const uploadForm = document.getElementById('uploadForm');
    if (uploadForm) {
        uploadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(uploadForm);

            fetch('upload_receipt.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'OK') {
                    alert('อัปโหลดสำเร็จ');
                    location.reload();
                } else {
                    alert('เกิดข้อผิดพลาด: ' + data.message);
                }
            })
            .catch(err => alert('เกิดข้อผิดพลาดในการเชื่อมต่อ: ' + err));
        });
    }
});


</script>
<!-- Modal ดูใบเสร็จ -->
<div class="modal fade" id="viewReceiptModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">ใบเสร็จรายจ่าย</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center">
        <img id="receiptImage" src="" class="img-fluid rounded" alt="Receipt">
      </div>
    </div>
  </div>
</div>

<!-- Modal อัปโหลดใบเสร็จ -->
<div class="modal fade" id="uploadReceiptModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">อัปโหลดใบเสร็จ</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="uploadForm" enctype="multipart/form-data">
          <input type="hidden" name="expense_id" id="expense_id">
          <div class="mb-3">
            <label class="form-label">เลือกไฟล์ใบเสร็จ (JPG, PNG)</label>
            <input type="file" name="receipt" class="form-control" accept="image/*" required>
          </div>
          <button type="submit" class="btn btn-primary w-100">อัปโหลด</button>
        </form>
      </div>
    </div>
  </div>
</div>

</body>
</html>


