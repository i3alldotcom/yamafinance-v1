<?php
include('../includes/auth.php');
check_login();
include('../config/db_connect.php');

$id = intval($_GET['id']);

$sql = "
    SELECT e.*, v.vendor_name
    FROM expenses e
    LEFT JOIN vendors v ON e.vendor_id = v.id
    WHERE e.id = $id
";
$res = $conn->query($sql);
$data = $res->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>แก้ไขรายจ่าย</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<?php include('../includes/navbar.php'); ?>

<body class="bg-light">
<div class="container mt-4">

    <h3 class="mb-3">แก้ไขรายจ่าย</h3>

    <form action="expense_update_save.php" method="post" enctype="multipart/form-data">

        <input type="hidden" name="id" value="<?php echo $data['id']; ?>">

        <div class="mb-3">
            <label class="form-label">วันที่</label>
            <input type="date" name="expense_date" class="form-control"
                   value="<?php echo $data['expense_date']; ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">ประเภท</label>
            <select name="category_id" id="category_id_select" class="form-select">
                <?php
                $cats = $conn->query("SELECT id, name FROM expense_categories WHERE disable = 0 ORDER BY name");
                while ($c = $cats->fetch_assoc()) {
                    $sel = ($c['id'] == $data['category_id']) ? "selected" : "";
                    echo "<option value='{$c['id']}' $sel>{$c['name']}</option>";
                }
                ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">ร้านค้า</label>
            <input type="text" id="vendor_search" name="vendor_name" class="form-control"
                value="<?php echo $data['vendor_name']; ?>" placeholder="พิมพ์ 2–3 ตัวเพื่อค้นหาร้านค้า">
            <input type="hidden" name="vendor_id" id="vendor_id" value="<?php echo $data['vendor_id']; ?>">
            <div id="vendor_list" class="list-group mt-1"></div>
        </div>


        <div class="mb-3">
            <label class="form-label">รายละเอียด</label>
            <input type="text" name="detail" id="detail_input" class="form-control"
                   autocomplete="off"
                   value="<?php echo htmlspecialchars($data['detail'], ENT_QUOTES); ?>"
                   placeholder="พิมพ์เพื่อค้นหารายละเอียดที่เคยใช้">
            <div id="detail_suggest" class="list-group mt-1"></div>
        </div>

        <div class="mb-3">
            <label class="form-label">จำนวนเงิน</label>
            <input type="number" name="amount" class="form-control"
                   value="<?php echo $data['amount']; ?>">
        </div>

        <div class="mb-3">
    <label class="form-label">วิธีชำระเงิน</label>
    <select name="payment_method" class="form-select" required>
        <option value="">-- เลือกวิธีชำระเงิน --</option>
        <option value="cash" <?php if($data['payment_method']=='cash') echo 'selected'; ?>>เงินสด</option>
        <option value="transfer" <?php if($data['payment_method']=='transfer') echo 'selected'; ?>>โอนเงิน</option>
        <option value="card" <?php if($data['payment_method']=='card') echo 'selected'; ?>>บัตรเครดิต</option>
    </select>
</div>
<!-- ใบเสร็จ -->
<div class="mb-3">
    <label class="form-label">ใบเสร็จ</label>

    <?php if ($data['uploaded'] !== '0' && !empty($data['uploaded'])): ?>
        <div class="mb-2">
            <button type="button" class="btn btn-success btn-sm" onclick="viewReceipt('<?php echo $data['id']; ?>', '<?php echo $data['uploaded']; ?>')">
                <i class="bi bi-file-earmark-image"></i> ดูใบเสร็จ
            </button>
        </div>
    <?php else: ?>
        <div class="mb-2">
            <span class="text-muted">ยังไม่มีใบเสร็จ</span>
        </div>
    <?php endif; ?>

    <input type="file" name="receipt" class="form-control" accept="image/*">
    <small class="text-muted">อัปโหลดใหม่จะทับไฟล์เดิม</small>
</div>


        <button type="submit" class="btn btn-success">บันทึกการแก้ไข</button>
        <a href="expenses.php" class="btn btn-secondary">กลับ</a>

    </form>

</div>
<script>
document.getElementById('vendor_search').addEventListener('keyup', function() {
    let q = this.value.trim();

    if (q.length < 2) {
        document.getElementById('vendor_list').innerHTML = "";
        return;
    }

    fetch("vendor_search_ajax.php?q=" + encodeURIComponent(q))
        .then(r => r.json())
        .then(data => {
            let list = document.getElementById('vendor_list');
            list.innerHTML = "";

            data.forEach(item => {
                let div = document.createElement('div');
                div.className = "list-group-item list-group-item-action";
                div.textContent = item.vendor_name;

                div.onclick = function() {
                    document.getElementById('vendor_search').value = item.vendor_name;
                    document.getElementById('vendor_id').value = item.id;
                    list.innerHTML = "";
                };

                list.appendChild(div);
            });
        });
});

// ถ้าผู้ใช้พิมพ์ชื่อร้านเอง → ล้าง vendor_id
document.getElementById('vendor_search').addEventListener('input', function() {
    document.getElementById('vendor_id').value = 0;
});

function viewReceipt(id, filename) {
    const imgPath = `../uploads/receipts/${filename}`;
    document.getElementById('receiptImage').src = imgPath;

    const modal = new bootstrap.Modal(document.getElementById('viewReceiptModal'));
    modal.show();
}

// ===== Autocomplete ช่อง "รายละเอียด" (ดึงจากตาราง expenses ตาม category_id) =====
let detailSearchTimer = null;

function getCategoryId() {
    const sel = document.getElementById('category_id_select');
    return sel ? String(sel.value || '') : '';
}

document.getElementById('detail_input').addEventListener('input', function() {
    const input = this;
    const box = document.getElementById('detail_suggest');
    const categoryId = getCategoryId();
    const q = input.value.trim();

    if (!categoryId) { box.innerHTML = ''; return; }   // ต้องเลือกประเภทก่อน
    if (q.length < 1) { box.innerHTML = ''; return; }

    clearTimeout(detailSearchTimer);
    detailSearchTimer = setTimeout(function() {
        const url = "expense_detail_search_ajax.php?category_id=" + encodeURIComponent(categoryId) +
                    "&q=" + encodeURIComponent(q);
        fetch(url)
            .then(r => r.json())
            .then(details => {
                box.innerHTML = '';
                if (!Array.isArray(details) || details.length === 0) return;
                details.forEach(detail => {
                    const div = document.createElement('div');
                    div.className = 'list-group-item list-group-item-action';
                    div.textContent = detail;
                    // ใช้ mousedown กัน blur ทำงานก่อน แล้วเลือกค่าลงช่อง
                    div.onmousedown = function(ev) {
                        ev.preventDefault();
                        input.value = detail;
                        box.innerHTML = '';
                    };
                    box.appendChild(div);
                });
            })
            .catch(err => console.error("[detail-search] error:", err));
    }, 250);
});

// เมื่อเปลี่ยนประเภท ให้ล้างค่ารายละเอียด + รายการแนะนำ
document.getElementById('category_id_select').addEventListener('change', function() {
    document.getElementById('detail_input').value = '';
    document.getElementById('detail_suggest').innerHTML = '';
});

// ปิดรายการแนะนำเมื่อคลิกที่อื่น
document.addEventListener('click', function(e) {
    if (e.target.id !== 'detail_input') {
        document.getElementById('detail_suggest').innerHTML = '';
    }
});

</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

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

</body>
</html>
