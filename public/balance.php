<?php
include('../includes/auth.php');
check_login();
include('../config/db_connect.php');
require_role('admin', 'accountant');

// ---------- รับพารามิเตอร์ (โหมด + งวด) ----------
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

// ---------- คำนวณช่วงวันที่ + หัวข้องวด ----------
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

/* -------------------------------
   รายรับรวม (donation_items)
-------------------------------- */
$stmt = $conn->prepare("
    SELECT SUM(amount) AS total_income
    FROM donation_items
    WHERE donation_date BETWEEN ? AND ? AND is_deleted = 0
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$row_income = $stmt->get_result()->fetch_assoc();
$total_income = $row_income['total_income'] !== null ? (float)$row_income['total_income'] : 0;
$stmt->close();

/* -------------------------------
   รายจ่ายรวม (expenses)
-------------------------------- */
$stmt = $conn->prepare("
    SELECT SUM(amount) AS total_expense
    FROM expenses
    WHERE expense_date BETWEEN ? AND ? AND is_deleted = 0
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$row_expense = $stmt->get_result()->fetch_assoc();
$total_expense = $row_expense['total_expense'] !== null ? (float)$row_expense['total_expense'] : 0;
$stmt->close();

$balance = $total_income - $total_expense;
$income_percent  = ($total_income + $total_expense > 0)
    ? round(($total_income / ($total_income + $total_expense)) * 100, 1)
    : 0;
$expense_percent = 100 - $income_percent;

/* -------------------------------
   รายรับตามประเภทบุญ
-------------------------------- */
$merit_labels = [];
$merit_values = [];
$stmt = $conn->prepare("
    SELECT m.merit_name AS label, SUM(d.amount) AS total
    FROM donation_items d
    LEFT JOIN merits m ON d.merit_id = m.id
    WHERE d.donation_date BETWEEN ? AND ? AND d.is_deleted = 0
    GROUP BY d.merit_id
    ORDER BY total DESC
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$res_merit = $stmt->get_result();
while ($row = $res_merit->fetch_assoc()) {
    $merit_labels[] = $row['label'] ?: 'ไม่ระบุ';
    $merit_values[] = (float)$row['total'];
}
$stmt->close();

/* -------------------------------
   รายจ่ายตามประเภท (expense_categories)
-------------------------------- */
$exp_labels = [];
$exp_values = [];
$stmt = $conn->prepare("
    SELECT c.name AS label, SUM(e.amount) AS total
    FROM expenses e
    LEFT JOIN expense_categories c ON e.category_id = c.id
    WHERE e.expense_date BETWEEN ? AND ? AND e.is_deleted = 0
    GROUP BY e.category_id
    ORDER BY total DESC
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$res_exp = $stmt->get_result();
while ($row = $res_exp->fetch_assoc()) {
    $exp_labels[] = $row['label'] ?: 'ไม่ระบุ';
    $exp_values[] = (float)$row['total'];
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>สรุปการเงิน</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="bg-light">
<?php include('../includes/navbar.php'); ?>

<div class="container mt-4">
    <h3 class="mb-4">สรุปการเงิน</h3>

    <!-- ฟอร์มเลือกโหมด + งวด -->
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

        <div class="col-md-1 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-search"></i>
            </button>
        </div>
        <div class="col-md-1 d-flex align-items-end">
            <a id="excelBtn" href="#" class="btn btn-success w-100">
                <i class="bi bi-file-earmark-excel"></i>
            </a>
        </div>
    </form>

    <div class="text-muted mb-3">งวด: <strong><?php echo htmlspecialchars($period_title); ?></strong></div>

    <!-- การ์ดยอดรวม -->
    <div class="row text-center mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm p-3">
                <h1 class="text-<?php echo ($balance >= 0 ? 'success' : 'danger'); ?>">
                    <?php echo number_format($balance, 0); ?> เยน
                </h1>
                <small class="text-muted">งบดุล</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm p-3">
                <h4 class="text-success"><?php echo number_format($total_income, 0); ?> เยน</h4>
                <small class="text-muted">รายรับ</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm p-3">
                <h4 class="text-danger"><?php echo number_format($total_expense, 0); ?> เยน</h4>
                <small class="text-muted">รายจ่าย</small>
            </div>
        </div>
    </div>

    <!-- แถบเปอร์เซ็นต์รายรับ-รายจ่าย -->
    <div class="progress mb-4" style="height: 25px;">
        <div class="progress-bar bg-success" style="width: <?php echo $income_percent; ?>%">
            รายรับ <?php echo $income_percent; ?>%
        </div>
        <div class="progress-bar bg-danger" style="width: <?php echo $expense_percent; ?>%">
            รายจ่าย <?php echo $expense_percent; ?>%
        </div>
    </div>

    <!-- กราฟแนวนอน -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h5 class="text-success mb-3">สรุปรายรับตามประเภทบุญ</h5>
            <?php if (empty($merit_labels)): ?>
                <p class="text-muted">ไม่มีข้อมูลในช่วงเวลานี้</p>
            <?php else: ?>
                <canvas id="incomeChart"></canvas>
            <?php endif; ?>
        </div>
        <div class="col-md-6">
            <h5 class="text-danger mb-3">สรุปรายจ่ายตามประเภท</h5>
            <?php if (empty($exp_labels)): ?>
                <p class="text-muted">ไม่มีข้อมูลในช่วงเวลานี้</p>
            <?php else: ?>
                <canvas id="expenseChart"></canvas>
            <?php endif; ?>
        </div>
    </div>

    <!-- ตารางสรุป -->
    <div class="row">
        <div class="col-md-6">
            <table class="table table-striped bg-white shadow-sm">
                <thead class="table-success">
                    <tr><th>ประเภทบุญ</th><th class="text-end">ยอดรวม (เยน)</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($merit_labels as $idx => $label): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($label); ?></td>
                        <td class="text-end"><?php echo number_format($merit_values[$idx], 0); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($merit_labels)): ?>
                    <tr><td colspan="2" class="text-center text-muted">ไม่มีข้อมูลในช่วงเวลานี้</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="col-md-6">
            <table class="table table-striped bg-white shadow-sm">
                <thead class="table-danger">
                    <tr><th>ประเภทรายจ่าย</th><th class="text-end">ยอดรวม (เยน)</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($exp_labels as $idx => $label): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($label); ?></td>
                        <td class="text-end"><?php echo number_format($exp_values[$idx], 0); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($exp_labels)): ?>
                    <tr><td colspan="2" class="text-center text-muted">ไม่มีข้อมูลในช่วงเวลานี้</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
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
    document.getElementById('excelBtn').href = 'balance_export.php?' + params;
}
document.getElementById('filterForm').addEventListener('change', updateExcelLink);
document.getElementById('filterForm').addEventListener('input', updateExcelLink);
updateExcelLink();

const meritLabels = <?php echo json_encode($merit_labels, JSON_UNESCAPED_UNICODE); ?>;
const meritValues = <?php echo json_encode($merit_values); ?>;
const expLabels   = <?php echo json_encode($exp_labels,   JSON_UNESCAPED_UNICODE); ?>;
const expValues   = <?php echo json_encode($exp_values); ?>;

if (meritLabels.length > 0) {
    new Chart(document.getElementById('incomeChart'), {
        type: 'bar',
        data: { labels: meritLabels, datasets: [{ data: meritValues, backgroundColor: '#28a745' }] },
        options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } } }
    });
}
if (expLabels.length > 0) {
    new Chart(document.getElementById('expenseChart'), {
        type: 'bar',
        data: { labels: expLabels, datasets: [{ data: expValues, backgroundColor: '#dc3545' }] },
        options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } } }
    });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
