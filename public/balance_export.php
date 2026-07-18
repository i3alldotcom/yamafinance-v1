<?php
include('../includes/auth.php');   // session_start() + check_login()
check_login();
include('../config/db_connect.php');
require_role('admin', 'accountant');

// ---------- รับพารามิเตอร์ (โหมด + งวด) ----------
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
    $period_title = "ไตรมาส $quarter ปี $year";
} else {
    $start_date = sprintf('%04d-%02d-01', $year, $month);
    $end_date   = date('Y-m-t', strtotime($start_date));
    $period_title = "{$th_months[$month]} $year";
}

/* -------- รายรับรวม -------- */
$stmt = $conn->prepare("SELECT SUM(amount) AS t FROM donation_items WHERE donation_date BETWEEN ? AND ? AND is_deleted = 0");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$total_income = (float)$stmt->get_result()->fetch_assoc()['t'];
$stmt->close();

/* -------- รายจ่ายรวม -------- */
$stmt = $conn->prepare("SELECT SUM(amount) AS t FROM expenses WHERE expense_date BETWEEN ? AND ? AND is_deleted = 0");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$total_expense = (float)$stmt->get_result()->fetch_assoc()['t'];
$stmt->close();

$balance = $total_income - $total_expense;

/* -------- รายรับตามประเภทบุญ -------- */
$merit_rows = [];
$stmt = $conn->prepare("
    SELECT m.merit_name AS label, SUM(d.amount) AS total
    FROM donation_items d
    LEFT JOIN merits m ON d.merit_id = m.id
    WHERE d.donation_date BETWEEN ? AND ? AND d.is_deleted = 0
    GROUP BY d.merit_id ORDER BY total DESC
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $merit_rows[] = ['label' => $r['label'] ?: 'ไม่ระบุ', 'total' => (float)$r['total']];
}
$stmt->close();

/* -------- รายจ่ายตามประเภท -------- */
$exp_rows = [];
$stmt = $conn->prepare("
    SELECT c.name AS label, SUM(e.amount) AS total
    FROM expenses e
    LEFT JOIN expense_categories c ON e.category_id = c.id
    WHERE e.expense_date BETWEEN ? AND ? AND e.is_deleted = 0
    GROUP BY e.category_id ORDER BY total DESC
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $exp_rows[] = ['label' => $r['label'] ?: 'ไม่ระบุ', 'total' => (float)$r['total']];
}
$stmt->close();
$conn->close();

// ---------- ฟังก์ชันช่วยสร้าง xlsx ----------
function xml_esc($s) { return htmlspecialchars($s === null ? '' : $s, ENT_QUOTES, 'UTF-8'); }
function col_letter($i) {
    $s = '';
    while ($i > 0) { $i--; $s = chr(65 + ($i % 26)) . $s; $i = (int)($i / 26); }
    return $s;
}
function build_sheet($rows) {
    $out = '';
    $rn = 0;
    foreach ($rows as $cells) {
        $rn++;
        $out .= '<row r="' . $rn . '">';
        foreach ($cells as $i => $c) {
            $ref = col_letter($i + 1) . $rn;
            if (isset($c['n'])) {
                $out .= '<c r="' . $ref . '" t="n"><v>' . $c['n'] . '</v></c>';
            } else {
                $out .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">'
                      . xml_esc($c['s']) . '</t></is></c>';
            }
        }
        $out .= '</row>';
    }
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetData>' . $out . '</sheetData></worksheet>';
}

// ===== Sheet เดียว: สรุปการเงินตามช่วงที่เลือก =====
$rows = [];
$rows[] = [['s' => 'สรุปการเงิน — วัดพระธรรมกายยามานาชิ']];
$rows[] = [['s' => 'งวด'], ['s' => $period_title]];
$rows[] = [['s' => 'ช่วงวันที่'], ['s' => $start_date . '  ถึง  ' . $end_date]];
$rows[] = [['s' => '']];
$rows[] = [['s' => 'รายรับรวม (เยน)'], ['n' => $total_income]];
$rows[] = [['s' => 'รายจ่ายรวม (เยน)'], ['n' => $total_expense]];
$rows[] = [['s' => 'ยอดคงเหลือ (เยน)'], ['n' => $balance]];
$rows[] = [['s' => '']];

// รายรับตามประเภทบุญ
$rows[] = [['s' => 'รายรับตามประเภทบุญ'], ['s' => 'ยอดรวม (เยน)']];
foreach ($merit_rows as $r) {
    $rows[] = [['s' => $r['label']], ['n' => $r['total']]];
}
if (empty($merit_rows)) $rows[] = [['s' => 'ไม่มีข้อมูล']];
$rows[] = [['s' => 'รวมรายรับ'], ['n' => $total_income]];
$rows[] = [['s' => '']];

// รายจ่ายตามประเภท
$rows[] = [['s' => 'รายจ่ายตามประเภท'], ['s' => 'ยอดรวม (เยน)']];
foreach ($exp_rows as $r) {
    $rows[] = [['s' => $r['label']], ['n' => $r['total']]];
}
if (empty($exp_rows)) $rows[] = [['s' => 'ไม่มีข้อมูล']];
$rows[] = [['s' => 'รวมรายจ่าย'], ['n' => $total_expense]];

$sheet = build_sheet($rows);

$zip = new ZipArchive();
$tmp = tempnam(sys_get_temp_dir(), 'xlsx');
$zip->open($tmp, ZipArchive::CREATE);

$zip->addFromString('[Content_Types].xml',
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
    . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
    . '<Default Extension="xml" ContentType="application/xml"/>'
    . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
    . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
    . '</Types>');

$zip->addFromString('_rels/.rels',
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
    . '</Relationships>');

$zip->addFromString('xl/workbook.xml',
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
    . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
    . '<sheets><sheet name="สรุปการเงิน" sheetId="1" r:id="rId1"/></sheets>'
    . '</workbook>');

$zip->addFromString('xl/_rels/workbook.xml.rels',
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
    . '</Relationships>');

$zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
$zip->close();

$filename = 'balance_' . $mode . '_' . $start_date . '_to_' . $end_date . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
readfile($tmp);
unlink($tmp);
exit;
?>
