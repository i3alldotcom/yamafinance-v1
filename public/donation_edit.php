<?php
include('../includes/auth.php');
check_login();
include('../config/db_connect.php');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) die("Invalid ID");

// ดึงข้อมูล donation_items
$sql = "
    SELECT di.*, d.full_name, m.merit_name
    FROM donation_items di
    LEFT JOIN donors d ON di.donor_id = d.id
    LEFT JOIN merits m ON di.merit_id = m.id
    WHERE di.id = $id AND di.is_deleted = 0
";
$result = $conn->query($sql);
if (!$result || $result->num_rows === 0) die("Record not found");
$row = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แก้ไขรายการบริจาค</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.14.0-beta3/dist/css/bootstrap-select.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.14.0-beta3/dist/js/bootstrap-select.min.js"></script>
</head>

<body class="bg-light">
<?php include('../includes/navbar.php'); ?>

<div class="container mt-4">
    <h3>แก้ไขรายการบริจาค</h3>
    <hr>

    <form method="POST" action="donation_update_save.php">
        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">

        <!-- วันที่บริจาค -->
        <div class="mb-3">
            <label class="form-label">วันที่บริจาค</label>
            <input type="date" name="donation_date" class="form-control"
                   value="<?php echo htmlspecialchars($row['donation_date']); ?>" required>
        </div>

        <!-- ผู้นำบุญ -->
        <div class="mb-3">
            <label class="form-label">ผู้นำบุญ</label>
            <div class="input-group">
                <input type="text" id="donor_search" class="form-control"
                       placeholder="พิมพ์เพื่อค้นหา"
                       value="<?php echo htmlspecialchars($row['full_name']); ?>">
                <button type="button" class="btn btn-outline-secondary" onclick="clearDonor()">ล้าง</button>
            </div>
            <input type="hidden" name="donor_id" id="donor_id"
                   value="<?php echo htmlspecialchars($row['donor_id']); ?>" required>
            <div id="donor_list" class="list-group mt-1" style="max-height:200px;overflow-y:auto;"></div>
        </div>

        <!-- ชื่อบนใบโม -->
        <div class="mb-3">
            <label class="form-label">ชื่อบนใบโม</label>
            <input type="text" name="receipt_name" class="form-control"
                   value="<?php echo htmlspecialchars($row['receipt_name']); ?>" required>
        </div>

        <!-- ชื่อบุญ -->
        <div class="mb-3">
            <label class="form-label">ชื่อบุญ</label>
            <select id="merit_id" name="merit_id" class="form-control" required></select>
        </div>

        <!-- จำนวนเงิน -->
        <div class="mb-3">
            <label class="form-label">จำนวนเงิน</label>
            <input type="number" name="amount" class="form-control" step="0.01"
                   value="<?php echo htmlspecialchars($row['amount']); ?>" required>
        </div>

        <!-- Item Code -->
        <div class="mb-3">
            <label class="form-label">เลขอ้างอิง (อัปเดตอัตโนมัติ)</label>
            <input type="text" id="item_code" name="item_code" class="form-control"
                   value="<?php echo htmlspecialchars($row['item_code']); ?>" readonly>
        </div>

        <!-- หมายเหตุ -->
        <div class="mb-3">
            <label class="form-label">หมายเหตุ</label>
            <textarea name="comment" class="form-control" rows="2"><?php echo htmlspecialchars($row['comment']); ?></textarea>
        </div>

        <button type="submit" class="btn btn-success">บันทึก</button>
        <a href="donations.php" class="btn btn-secondary">ยกเลิก</a>
    </form>
</div>

<script>
let meritsList = [];

// โหลดรายการบุญ
function loadMerits(selectedId) {
    fetch("merit_list_ajax.php")
        .then(r => r.json())
        .then(data => {
            meritsList = data;
            const $select = $('#merit_id');
            $select.html(data.map(m => {
                const sel = (String(m.id) === String(selectedId)) ? 'selected' : '';
                return `<option value="${m.id}" ${sel}>${m.merit_name}</option>`;
            }).join(''));
        });
}

// ค้นหา donor
document.getElementById('donor_search').addEventListener('keyup', function() {
    const q = this.value.trim();
    const list = document.getElementById('donor_list');
    if (q.length < 2) { list.innerHTML = ''; return; }

    fetch("donor_search_ajax.php?q=" + encodeURIComponent(q))
        .then(r => r.json())
        .then(data => {
            list.innerHTML = data.map(d => `
                <button type="button" class="list-group-item list-group-item-action"
                        onclick="selectDonor(${d.id}, '${d.full_name.replace(/'/g, "\\'")}')">
                    ${d.full_name}
                </button>
            `).join('');
        });
});

function selectDonor(id, name) {
    document.getElementById('donor_id').value = id;
    document.getElementById('donor_search').value = name;
    document.getElementById('donor_list').innerHTML = '';
    updateItemCode();
}

function clearDonor() {
    document.getElementById('donor_id').value = '';
    document.getElementById('donor_search').value = '';
    document.getElementById('donor_list').innerHTML = '';
    updateItemCode();
}

// อัปเดต item_code
function updateItemCode() {
    const donorId = document.getElementById('donor_id').value;
    const meritId = document.getElementById('merit_id').value;
    const oldCode = '<?php echo $row['item_code']; ?>';
    const suffix = oldCode.split('-')[1] || '01';
    if (donorId && meritId) {
        document.getElementById('item_code').value = donorId + '/' + meritId + '-' + suffix;
    }
}

// เมื่อเปลี่ยนชื่อบุญ
document.getElementById('merit_id').addEventListener('change', updateItemCode);

// โหลดเมื่อเริ่มต้น
document.addEventListener('DOMContentLoaded', function() {
    loadMerits('<?php echo $row['merit_id']; ?>');
});
</script>
</body>
</html>
