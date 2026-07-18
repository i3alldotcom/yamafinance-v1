<?php
include('../includes/auth.php');   // session_start() + check_login()
check_login();
include('../config/db_connect.php');
require_role('admin', 'accountant');

// ---------- รับพารามิเตอร์ ----------
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'month';   // month | quarter | year
if (!in_array($mode, ['month', 'quarter', 'year'])) $mode = 'month';

$now_year    = (int)date('Y');
$now_month   = (int)date('n');
$now_quarter = (int)ceil($now_month / 3);

$year    = isset($_GET['year'])    ? intval($_GET['year'])    : $now_year;
$month   = isset($_GET['month'])   ? intval($_GET['month'])   : $now_month;
$quarter = isset($_GET['quarter']) ? intval($_GET['quarter']) : $now_quarter;

if ($year < 2000 || $year > 2999) $year = $now_year;
if ($month < 1 || $month > 12)    $month = $now_month;
if ($quarter < 1 || $quarter > 4) $quarter = $now_quarter;

$th_months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',
              7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];

// ---------- คำนวณช่วงวันที่ + ชื่อหัวข้องวด ----------
if ($mode === 'year') {
    $start_date = sprintf('%04d-01-01', $year);
    $end_date   = sprintf('%04d-12-31', $year);
    $period_title = "ปี $year";
} elseif ($mode === 'quarter') {
    $q_start_month = ($quarter - 1) * 3 + 1;
    $q_end_month   = $q_start_month + 2;
    $start_date = sprintf('%04d-%02d-01', $year, $q_start_month);
    $end_date   = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $year, $q_end_month)));
    $period_title = "ไตรมาส $quarter ปี $year ({$th_months[$q_start_month]}–{$th_months[$q_end_month]})";
} else { // month
    $start_date = sprintf('%04d-%02d-01', $year, $month);
    $end_date   = date('Y-m-t', strtotime($start_date));
    $period_title = "{$th_months[$month]} $year";
}

// ---------- ดึงรายจ่ายแยกตามประเภท ----------
$rows = [];
$grand_total = 0;
$total_count = 0;
$sql = "
    SELECT c.name AS label, SUM(e.amount) AS total, COUNT(*) AS cnt
    FROM expenses e
    LEFT JOIN expense_categories c ON e.category_id = c.id
    WHERE e.expense_date BETWEEN ? AND ? AND e.is_deleted = 0
    GROUP BY e.category_id ORDER BY total DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $r['label'] = $r['label'] ?: 'ไม่ระบุ';
    $rows[] = $r;
    $grand_total += (float)$r['total'];
    $total_count += (int)$r['cnt'];
}
$stmt->close();

$labels = [];
$values = [];
foreach ($rows as $r) {
    $labels[] = $r['label'];
    $values[] = (float)$r['total'];
}

// ---------- ดึงรายละเอียดรายการ (แบ่งหน้า) ----------
$limit = 20;
$page  = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

$count_stmt = $conn->prepare("
    SELECT COUNT(*) AS c FROM expenses
    WHERE expense_date BETWEEN ? AND ? AND is_deleted = 0
");
$count_stmt->bind_param("ss", $start_date, $end_date);
$count_stmt->execute();
$total_detail = (int)$count_stmt->get_result()->fetch_assoc()['c'];
$count_stmt->close();
$total_pages = ceil($total_detail / $limit);

$det_stmt = $conn->prepare("
    SELECT e.expense_date, e.expense_code, c.name AS category_name,
           v.vendor_name, e.detail, e.payment_method, e.amount, e.uploaded
    FROM expenses e
    LEFT JOIN expense_categories c ON e.category_id = c.id
    LEFT JOIN vendors v ON e.vendor_id = v.id
    WHERE e.expense_date BETWEEN ? AND ? AND e.is_deleted = 0
    ORDER BY e.expense_date DESC, e.id DESC
    LIMIT ? OFFSET ?
");
$det_stmt->bind_param("ssii", $start_date, $end_date, $limit, $offset);
$det_stmt->execute();
$details = $det_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$det_stmt->close();

$pay_map = ['cash' => 'เงินสด', 'transfer' => 'โอน', 'card' => 'บัตร'];
$base_params = http_build_query([
    'mode' => $mode, 'year' => $year, 'month' => $month, 'quarter' => $quarter
]);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>สรุปรายจ่าย รายเดือน / ไตรมาส / ปี</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="bg-light">
<?php include('../includes/navbar.php'); ?>

<div class="container mt-4">
    <h3 class="mb-4"><i class="bi bi-graph-down-arrow text-danger"></i> สรุปรายจ่าย (รายเดือน / ไตรมาส / ปี)</h3>

    <form class="row g-3 mb-4" method="get" id="filterForm">
        <div class="col-md-2">
            <label class="form-label">สรุปแบบ</label>
            <select name="mode" id="modeSelect" class="form-select" onchange="togglePeriodInputs()">
                <option value="month"   <?php echo $mode==='month'?'selected':''; ?>>รายเดือน</option>
                <option value="quarter" <?php echo $mode==='quarter'?'selected':''; ?>>รายไตรมาส</option>
                <option value="year"    <?php echo $mode==='year'?'selected':''; ?>>รายปี</option>
            </select>
        </div>

        <div class="col-md-3" id="monthGroup">
            <label class="form-label">เดือน</label>
            <select name="month" class="form-select">
                <?php foreach ($th_months as $mn => $mname): ?>
                <option value="<?php echo $mn; ?>" <?php echo $month===$mn?'selected':''; ?>><?php echo $mname; ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-3" id="quarterGroup">
            <label class="form-label">ไตรมาส</label>
            <select name="quarter" class="form-select">
                <option value="1" <?php echo $quarter===1?'selected':''; ?>>ไตรมาส 1 (ม.ค.–มี.ค.)</option>
                <option value="2" <?php echo $quarter===2?'selected':''; ?>>ไตรมาส 2 (เม.ย.–มิ.ย.)</option>
                <option value="3" <?php echo $quarter===3?'selected':''; ?>>ไตรมาส 3 (ก.ค.–ก.ย.)</option>
                <option value="4" <?php echo $quarter===4?'selected':''; ?>>ไตรมาส 4 (ต.ค.–ธ.ค.)</option>
            </select>
        </div>

        <div class="col-md-2" id="yearGroup">
            <label class="form-label">ปี (ค.ศ.)</label>
            <input type="number" name="year" class="form-control" min="2000" max="2999"
                   value="<?php echo htmlspecialchars($year); ?>">
        </div>

        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> แสดงผล</button>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <a id="excelBtn" href="#" class="btn btn-success w-100"><i class="bi bi-file-earmark-excel"></i> Excel</a>
        </div>
    </form>

    <div class="card shadow-sm p-3 mb-4 text-center">
        <div class="text-muted mb-1">สรุปรายจ่าย: <strong><?php echo htmlspecialchars($period_title); ?></strong></div>
        <h1 class="text-danger"><?php echo number_format($grand_total, 0); ?> เยน</h1>
        <small class="text-muted"><?php echo number_format($total_count); ?> รายการ • <?php echo count($rows); ?> ประเภทรายจ่าย</small>
    </div>

    <div class="row">
        <div class="col-md-5">
            <div class="card shadow-sm p-3 mb-4">
                <h5 class="text-danger mb-3">สัดส่วนตามประเภทรายจ่าย</h5>
                <?php if (empty($rows)): ?>
                    <p class="text-muted">ไม่มีข้อมูลในช่วงเวลานี้</p>
                <?php else: ?>
                    <canvas id="catChart"></canvas>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-md-7">
            <table class="table table-striped bg-white shadow-sm">
                <thead class="table-danger">
                    <tr><th>ประเภทรายจ่าย</th><th class="text-end">จำนวนรายการ</th><th class="text-end">ยอดรวม (เยน)</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($r['label']); ?></td>
                        <td class="text-end"><?php echo number_format($r['cnt']); ?></td>
                        <td class="text-end"><?php echo number_format($r['total'], 0); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($rows)): ?>
                    <tr><td colspan="3" class="text-center text-muted">ไม่มีข้อมูลในช่วงเวลานี้</td></tr>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($rows)): ?>
                <tfoot>
                    <tr class="table-danger fw-bold">
                        <td>รวมทั้งหมด</td>
                        <td class="text-end"><?php echo number_format($total_count); ?></td>
                        <td class="text-end"><?php echo number_format($grand_total, 0); ?></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <!-- ตารางรายละเอียดรายการ (แบ่งหน้า) -->
    <div class="card shadow-sm p-3">
        <h5 class="text-danger mb-3">รายละเอียดรายการ (<?php echo number_format($total_detail); ?> รายการ)</h5>
        <div class="table-responsive">
            <table class="table table-bordered table-sm bg-white">
                <thead class="table-danger">
                    <tr>
                        <th>วันที่</th><th>รหัสบิล</th><th>ประเภทรายจ่าย</th><th>ร้านค้า</th>
                        <th>รายละเอียด</th><th>วิธีจ่าย</th><th class="text-end">จำนวนเงิน (เยน)</th><th>ใบเสร็จ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($details as $d): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($d['expense_date']); ?></td>
                        <td><?php echo htmlspecialchars($d['expense_code']); ?></td>
                        <td><?php echo htmlspecialchars($d['category_name']); ?></td>
                        <td><?php echo htmlspecialchars($d['vendor_name']); ?></td>
                        <td><?php echo htmlspecialchars($d['detail']); ?></td>
                        <td><?php echo htmlspecialchars(isset($pay_map[$d['payment_method']]) ? $pay_map[$d['payment_method']] : $d['payment_method']); ?></td>
                        <td class="text-end"><?php echo number_format($d['amount'], 2); ?></td>
                        <td><?php echo $d['uploaded'] && $d['uploaded'] !== '0' ? 'มี' : '—'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($details)): ?>
                    <tr><td colspan="8" class="text-center text-muted">ไม่มีข้อมูลในช่วงเวลานี้</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
        <nav>
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo $i===$page?'active':''; ?>">
                    <a class="page-link" href="?<?php echo $base_params; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<script>
function togglePeriodInputs() {
    var mode = document.getElementById('modeSelect').value;
    document.getElementById('monthGroup').style.display   = (mode === 'month')   ? '' : 'none';
    document.getElementById('quarterGroup').style.display = (mode === 'quarter') ? '' : 'none';
}
togglePeriodInputs();

function updateExcelLink() {
    var f = document.getElementById('filterForm');
    var params = new URLSearchParams(new FormData(f)).toString();
    document.getElementById('excelBtn').href = 'summary_expenses_export.php?' + params;
}
document.getElementById('filterForm').addEventListener('change', updateExcelLink);
document.getElementById('filterForm').addEventListener('input', updateExcelLink);
updateExcelLink();

const catLabels = <?php echo json_encode($labels, JSON_UNESCAPED_UNICODE); ?>;
const catValues = <?php echo json_encode($values); ?>;
if (catLabels.length > 0) {
    new Chart(document.getElementById('catChart'), {
        type: 'pie',
        data: { labels: catLabels, datasets: [{ data: catValues }] },
        options: { plugins: { legend: { position: 'bottom' } } }
    });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
