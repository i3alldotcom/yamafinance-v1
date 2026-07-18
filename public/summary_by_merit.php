<?php
include('../includes/auth.php');   // session_start() + check_login()
check_login();
include('../config/db_connect.php');
require_role('admin', 'accountant');

// ---------- รับพารามิเตอร์ ----------
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'month';
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
} else {
    $start_date = sprintf('%04d-%02d-01', $year, $month);
    $end_date   = date('Y-m-t', strtotime($start_date));
    $period_title = "{$th_months[$month]} $year";
}

// ---------- โหลดรายการบุญ (สำหรับ dropdown ค้นหา) ----------
$merit_list = [];
$mstmt = $conn->prepare("SELECT id, merit_name FROM merits WHERE disable = 0 OR disable IS NULL ORDER BY merit_name");
$mstmt->execute();
$mres = $mstmt->get_result();
while ($m = $mres->fetch_assoc()) { $merit_list[] = $m; }
$mstmt->close();

$merit_id = isset($_GET['merit_id']) ? intval($_GET['merit_id']) : 0;

// ---------- สรุปตามบุญ (กราฟ + ตาราง) ----------
$rows = [];
$grand_total = 0;
$total_count = 0;
$sql = "
    SELECT m.merit_name AS label, SUM(d.amount) AS total, COUNT(*) AS cnt
    FROM donation_items d
    LEFT JOIN merits m ON d.merit_id = m.id
    WHERE d.donation_date BETWEEN ? AND ? AND d.is_deleted = 0
    GROUP BY d.merit_id ORDER BY total DESC
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

// ---------- ถ้าเลือกบุญ: รวมยอดตามผู้นำบุญ + รายละเอียด ----------
// รวมยอดตาม (ผู้นำบุญ + ประเภทบุญ) — แสดงเสมอ ถ้าเลือกบุญก็กรองเฉพาะบุญนั้น
$donor_summary = [];
$donor_details = [];

if ($merit_id > 0) {
    $ds = $conn->prepare("
        SELECT d.donor_id, dn.full_name, d.merit_id, m.merit_name,
               SUM(d.amount) AS total, COUNT(*) AS cnt
        FROM donation_items d
        LEFT JOIN donors dn ON d.donor_id = dn.id
        LEFT JOIN merits m ON d.merit_id = m.id
        WHERE d.donation_date BETWEEN ? AND ? AND d.is_deleted = 0 AND d.merit_id = ?
        GROUP BY d.donor_id, d.merit_id
        ORDER BY total DESC
    ");
    $ds->bind_param("ssi", $start_date, $end_date, $merit_id);
} else {
    $ds = $conn->prepare("
        SELECT d.donor_id, dn.full_name, d.merit_id, m.merit_name,
               SUM(d.amount) AS total, COUNT(*) AS cnt
        FROM donation_items d
        LEFT JOIN donors dn ON d.donor_id = dn.id
        LEFT JOIN merits m ON d.merit_id = m.id
        WHERE d.donation_date BETWEEN ? AND ? AND d.is_deleted = 0
        GROUP BY d.donor_id, d.merit_id
        ORDER BY total DESC
    ");
    $ds->bind_param("ss", $start_date, $end_date);
}
$ds->execute();
$dres = $ds->get_result();
while ($d = $dres->fetch_assoc()) {
    $donor_summary[] = $d;
    $did = (int)$d['donor_id'];
    $mid = (int)$d['merit_id'];
    $key = $did . '_' . $mid;
    // รายละเอียดแต่ละรายการของคู่ (ผู้นำบุญ, บุญ)
    $det = $conn->prepare("
        SELECT d.donation_date, d.receipt_name, d.amount, d.comment
        FROM donation_items d
        WHERE d.donor_id = ? AND d.merit_id = ? AND d.donation_date BETWEEN ? AND ? AND d.is_deleted = 0
        ORDER BY d.donation_date DESC, d.id DESC
    ");
    $det->bind_param("iiss", $did, $mid, $start_date, $end_date);
    $det->execute();
    $donor_details[$key] = $det->get_result()->fetch_all(MYSQLI_ASSOC);
    $det->close();
}
$ds->close();
$conn->close();

// หาชื่อบุญที่เลือก
$sel_merit_name = '';
foreach ($merit_list as $m) { if ((int)$m['id'] === $merit_id) { $sel_merit_name = $m['merit_name']; break; } }

$labels = []; $values = [];
foreach ($rows as $r) { $labels[] = $r['label']; $values[] = (float)$r['total']; }

$base_params = http_build_query([
    'mode' => $mode, 'year' => $year, 'month' => $month, 'quarter' => $quarter
]);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>สรุปตามบุญ</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.14.0-beta3/dist/css/bootstrap-select.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.14.0-beta3/dist/js/bootstrap-select.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="bg-light">
<?php include('../includes/navbar.php'); ?>

<div class="container mt-4">
    <h3 class="mb-4"><i class="bi bi-journal-bookmark-fill text-warning"></i> สรุปตามประเภทบุญ</h3>

    <!-- ฟอร์ม -->
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
            <input type="number" name="year" class="form-control" min="2000" max="2999" value="<?php echo htmlspecialchars($year); ?>">
        </div>

        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> แสดงผล</button>
        </div>
        <!-- คงค่าบุญที่เลือกไว้เมื่อ submit ฟอร์ม -->
        <input type="hidden" name="merit_id" id="merit_id" value="<?php echo $merit_id; ?>">
    </form>

    <!-- เลือกบุญ (dropdown ค้นหาได้) + Excel -->
    <div class="row g-3 mb-4">
        <div class="col-md-8">
            <label class="form-label">เลือกบุญ (เว้นว่างหากต้องการทั้งหมด)</label>
            <select id="meritSelect" class="selectpicker form-control" data-live-search="true" title="-- เลือกบุญทั้งหมด --">
                <?php foreach ($merit_list as $m): ?>
                <option value="<?php echo $m['id']; ?>" <?php echo $merit_id===(int)$m['id']?'selected':''; ?>><?php echo htmlspecialchars($m['merit_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4 d-flex align-items-end">
            <a id="excelBtn" href="#" class="btn btn-success w-100"><i class="bi bi-file-earmark-excel"></i> Excel</a>
        </div>
    </div>

    <!-- การ์ดยอดรวม -->
    <div class="card shadow-sm p-3 mb-4 text-center">
        <div class="text-muted mb-1">สรุปรายรับ: <strong><?php echo htmlspecialchars($period_title); ?></strong></div>
        <h1 class="text-success"><?php echo number_format($grand_total, 0); ?> เยน</h1>
        <small class="text-muted"><?php echo number_format($total_count); ?> รายการ • <?php echo count($rows); ?> ประเภทบุญ</small>
    </div>

    <div class="row">
        <div class="col-md-5">
            <div class="card shadow-sm p-3 mb-4">
                <h5 class="text-success mb-3">สัดส่วนตามประเภทบุญ</h5>
                <?php if (empty($rows)): ?>
                    <p class="text-muted">ไม่มีข้อมูลในช่วงเวลานี้</p>
                <?php else: ?>
                    <canvas id="meritChart"></canvas>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-7">
            <table class="table table-striped bg-white shadow-sm">
                <thead class="table-success">
                    <tr><th>ประเภทบุญ</th><th class="text-end">จำนวนรายการ</th><th class="text-end">ยอดรวม (เยน)</th></tr>
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
                <tfoot>
                    <tr class="table-success fw-bold">
                        <td>รวมทั้งหมด</td>
                        <td class="text-end"><?php echo number_format($total_count); ?></td>
                        <td class="text-end"><?php echo number_format($grand_total, 0); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- รวมยอดตามผู้นำบุญ + ประเภทบุญ (แสดงเสมอ / คลิกเพื่อขยายรายการ) -->
    <div class="card shadow-sm p-3">
        <h5 class="text-warning mb-3">
            รวมยอดตามผู้นำบุญ
            <?php if ($merit_id > 0): ?>— <?php echo htmlspecialchars($sel_merit_name); ?><?php endif; ?>
            (<?php echo htmlspecialchars($period_title); ?>)
            <small class="text-muted">— คลิกที่แถวเพื่อดูรายละเอียด</small>
        </h5>
        <div class="table-responsive">
            <table class="table table-bordered table-sm bg-white align-middle">
                <thead class="table-warning">
                    <tr>
                        <th>ผู้นำบุญ</th>
                        <th>ประเภทบุญ</th>
                        <th class="text-end">จำนวนครั้ง</th>
                        <th class="text-end">ยอดรวม (เยน)</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($donor_summary as $idx => $d): ?>
                    <?php
                        $key = (int)$d['donor_id'] . '_' . (int)$d['merit_id'];
                        $has_many = ((int)$d['cnt'] > 1);   // ซ้ำกันหลายรายการ → คลิกขยายได้
                    ?>
                    <tr <?php echo $has_many ? 'style="cursor:pointer" onclick="toggleDetail('.$idx.')"' : ''; ?>>
                        <td><?php echo htmlspecialchars($d['full_name'] ?: 'ไม่ระบุ'); ?></td>
                        <td><?php echo htmlspecialchars($d['merit_name'] ?: 'ไม่ระบุ'); ?></td>
                        <td class="text-end"><?php echo number_format($d['cnt']); ?></td>
                        <td class="text-end"><?php echo number_format($d['total'], 0); ?></td>
                        <td class="text-center">
                            <?php if ($has_many): ?>
                                <i class="bi bi-chevron-down" id="icon<?php echo $idx; ?>"></i>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($has_many): ?>
                    <tr id="detail<?php echo $idx; ?>" style="display:none;">
                        <td colspan="5" class="bg-light">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>วันที่</th><th>ชื่อบนใบโม</th><th>หมายเหตุ</th><th class="text-end">จำนวนเงิน (เยน)</th></tr></thead>
                                <tbody>
                                    <?php foreach ($donor_details[$key] as $det): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($det['donation_date']); ?></td>
                                        <td><?php echo htmlspecialchars($det['receipt_name']); ?></td>
                                        <td><?php echo htmlspecialchars($det['comment']); ?></td>
                                        <td class="text-end"><?php echo number_format($det['amount'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if (empty($donor_summary)): ?>
                    <tr><td colspan="5" class="text-center text-muted">ไม่มีข้อมูลในช่วงเวลานี้</td></tr>
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

function toggleDetail(i) {
    var row = document.getElementById('detail' + i);
    var icon = document.getElementById('icon' + i);
    if (row.style.display === 'none') {
        row.style.display = '';
        icon.className = 'bi bi-chevron-up';
    } else {
        row.style.display = 'none';
        icon.className = 'bi bi-chevron-down';
    }
}

$(function(){
    $('.selectpicker').selectpicker();
    $('#meritSelect').on('changed.bs.select', function(e, clickedIndex, isSelected, previousValue) {
        $('#merit_id').val($(this).val());
        $('#filterForm').submit();
    });
});

function updateExcelLink() {
    var p = '<?php echo $base_params; ?>';
    var mid = document.getElementById('merit_id').value;
    var q = p + (mid ? '&merit_id=' + encodeURIComponent(mid) : '');
    document.getElementById('excelBtn').href = 'summary_by_merit_export.php?' + q;
}
updateExcelLink();

const meritLabels = <?php echo json_encode($labels, JSON_UNESCAPED_UNICODE); ?>;
const meritValues = <?php echo json_encode($values); ?>;
if (meritLabels.length > 0) {
    new Chart(document.getElementById('meritChart'), {
        type: 'pie',
        data: { labels: meritLabels, datasets: [{ data: meritValues }] },
        options: { plugins: { legend: { position: 'bottom' } } }
    });
}
</script>
</body>
</html>
