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

$year     = isset($_GET['year'])     ? intval($_GET['year'])     : $now_year;
$month    = isset($_GET['month'])    ? intval($_GET['month'])    : $now_month;
$quarter  = isset($_GET['quarter'])  ? intval($_GET['quarter'])  : $now_quarter;
$merit_id = isset($_GET['merit_id']) ? intval($_GET['merit_id']) : 0;

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

// ชื่อบุญที่เลือก (ถ้ามี)
$sel_merit_name = 'ทุกบุญ';
if ($merit_id > 0) {
    $ms = $conn->prepare("SELECT merit_name FROM merits WHERE id = ?");
    $ms->bind_param("i", $merit_id);
    $ms->execute();
    $mr = $ms->get_result()->fetch_assoc();
    if ($mr) $sel_merit_name = $mr['merit_name'];
    $ms->close();
}

// ---------- รายละเอียดรายการ (แยกรายการ เรียงตามวันที่) ----------
if ($merit_id > 0) {
    $det = $conn->prepare("
        SELECT d.donation_date, dn.full_name, m.merit_name, d.receipt_name, d.amount, d.comment
        FROM donation_items d
        LEFT JOIN donors dn ON d.donor_id = dn.id
        LEFT JOIN merits m ON d.merit_id = m.id
        WHERE d.merit_id = ? AND d.donation_date BETWEEN ? AND ? AND d.is_deleted = 0
        ORDER BY d.donation_date ASC, d.id ASC
    ");
    $det->bind_param("iss", $merit_id, $start_date, $end_date);
} else {
    $det = $conn->prepare("
        SELECT d.donation_date, dn.full_name, m.merit_name, d.receipt_name, d.amount, d.comment
        FROM donation_items d
        LEFT JOIN donors dn ON d.donor_id = dn.id
        LEFT JOIN merits m ON d.merit_id = m.id
        WHERE d.donation_date BETWEEN ? AND ? AND d.is_deleted = 0
        ORDER BY d.donation_date ASC, d.id ASC
    ");
    $det->bind_param("ss", $start_date, $end_date);
}
$det->execute();
$details = $det->get_result()->fetch_all(MYSQLI_ASSOC);
$det->close();
$conn->close();

$grand_total = 0;
foreach ($details as $d) { $grand_total += (float)$d['amount']; }

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

// ===== Sheet เดียว: รายการแยก เรียงตามวันที่ =====
$rows = [];
$rows[] = [['s' => 'รายการบริจาคตามบุญ: ' . $sel_merit_name . ' — ' . $period_title]];
$rows[] = [['s' => 'ช่วงวันที่'], ['s' => $start_date . '  ถึง  ' . $end_date]];
$rows[] = [['s' => '']];
$rows[] = [
    ['s' => 'วันที่'], ['s' => 'ผู้นำบุญ'], ['s' => 'ประเภทบุญ'],
    ['s' => 'ชื่อบนใบโม'], ['s' => 'จำนวนเงิน (เยน)'], ['s' => 'หมายเหตุ']
];
foreach ($details as $d) {
    $rows[] = [
        ['s' => $d['donation_date']],
        ['s' => $d['full_name']],
        ['s' => $d['merit_name']],
        ['s' => $d['receipt_name']],
        ['n' => (float)$d['amount']],
        ['s' => $d['comment']]
    ];
}
if (empty($details)) {
    $rows[] = [['s' => 'ไม่มีข้อมูลในช่วงเวลานี้']];
} else {
    $rows[] = [['s' => 'รวมทั้งหมด'], ['s' => ''], ['s' => ''], ['s' => ''], ['n' => (float)$grand_total], ['s' => '']];
}

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
    . '<sheets><sheet name="รายการบริจาค" sheetId="1" r:id="rId1"/></sheets>'
    . '</workbook>');

$zip->addFromString('xl/_rels/workbook.xml.rels',
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
    . '</Relationships>');

$zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
$zip->close();

$suffix = $merit_id > 0 ? 'merit' . $merit_id : 'all';
$filename = 'donations_by_merit_' . $suffix . '_' . $start_date . '_to_' . $end_date . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
readfile($tmp);
unlink($tmp);
exit;
?>
