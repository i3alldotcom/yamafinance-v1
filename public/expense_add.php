<?php
include('../includes/auth.php');
check_login();
include('../config/db_connect.php');
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เพิ่มรายการรายจ่าย</title>

    <!-- jQuery ต้องมาก่อน Bootstrap Select -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Bootstrap + Bootstrap Select -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.14.0-beta3/dist/css/bootstrap-select.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.14.0-beta3/dist/js/bootstrap-select.min.js"></script>
</head>

<body class="bg-light">
<?php include('../includes/navbar.php'); ?>

<div class="container mt-4">

    <h3>บันทึกรายจ่ายใหม่</h3>
    <hr>

   <form method="POST" action="expense_save.php" enctype="multipart/form-data">

        <!-- วันที่รายจ่าย -->
        <div class="mb-3">
            <label class="form-label">วันที่รายจ่าย</label>
            <input type="date" name="expense_date" class="form-control" required>
        </div>

        <!-- ประเภทรายจ่าย -->
        <div class="mb-3">
            <label class="form-label">ประเภทรายจ่าย</label>
            <div class="input-group">
                <select class="selectpicker form-control category_select"
                        data-live-search="true"
                        title="เลือกประเภท..."
                        name="category_id"
                        required>
                </select>

                <button type="button" class="btn btn-primary" onclick="openAddCategoryModal()">+</button>
            </div>
        </div>

        <!-- ร้านค้า -->
        <div class="mb-3">
            <label class="form-label">บริษัท / ร้านค้า</label>
            <input type="text" id="vendor_search" name="vendor_search" class="form-control" placeholder="พิมพ์ 2–3 ตัวเพื่อค้นหาร้านค้า" autocomplete="off">
            <input type="hidden" name="vendor_id" id="vendor_id">
            <div id="vendor_list" class="list-group mt-1"></div>
        </div>

        <!-- รายละเอียด -->
        <div class="mb-3">
            <label class="form-label">รายละเอียด</label>
            <input type="text" name="detail" id="detail_input" class="form-control" required
                   autocomplete="off"
                   placeholder="พิมพ์เพื่อค้นหารายละเอียดที่เคยใช้ (ต้องเลือกประเภทก่อน)">
            <div id="detail_suggest" class="list-group mt-1"></div>
        </div>

        <!-- จำนวนเงิน -->
        <div class="mb-3">
            <label class="form-label">จำนวนเงิน (เยน)</label>
            <input type="number" name="amount" class="form-control" required>
        </div>

        <!-- วิธีชำระเงิน -->
        <div class="mb-3">
    <label class="form-label">วิธีชำระเงิน</label>
    <select name="payment_method" class="form-select" required>
        <option value="">-- เลือกวิธีชำระเงิน --</option>
        <option value="cash">เงินสด</option>
        <option value="transfer">โอนเงิน</option>
        <option value="card">บัตรเครดิต</option>
    </select>
</div>

    <!-- อัปโหลดใบเสร็จ -->
    <div class="mb-3">
        <label class="form-label">ใบเสร็จ (ถ้ามี)</label>
        <input type="file" name="receipt" class="form-control" accept="image/*">
        <small class="text-muted">รองรับไฟล์ JPG, PNG</small>
    </div>

        <hr>

        <button type="submit" class="btn btn-success">บันทึกรายจ่าย</button>
        <a href="expenses.php" class="btn btn-secondary">กลับ</a>

    </form>
</div>

<!-- Modal เพิ่มประเภทรายจ่าย -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="addCategoryForm" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">เพิ่มประเภทรายจ่ายใหม่</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <label class="form-label">ชื่อประเภท</label>
        <input type="text" name="name" class="form-control" required>

        <label class="form-label mt-2">รายละเอียด</label>
        <textarea name="description" class="form-control" rows="2"></textarea>
      </div>

      <div class="modal-footer">
        <button type="submit" class="btn btn-success">บันทึก</button>
      </div>
    </form>
  </div>
</div>

<script>
let categoriesList = [];

// โหลดประเภทรายจ่าย
function loadCategories() {
   fetch("expense_category_list_ajax.php")
        .then(r => r.json())
        .then(data => {
            categoriesList = data;
            populateCategorySelects();
        })
        .catch(err => console.error("Error loading categories:", err));
}

function buildCategoryOptions() {
    if (!categoriesList || categoriesList.length === 0) {
        return `<option value="" disabled>ไม่มีประเภทที่เปิดใช้งาน</option>`;
    }
    return categoriesList.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
}

function populateCategorySelects() {
    document.querySelectorAll('.category_select').forEach(sel => {
        let selectedValue = sel.value;

        $(sel).selectpicker('destroy');
        sel.innerHTML = buildCategoryOptions();
        if (selectedValue) sel.value = selectedValue;
        $(sel).selectpicker();

        // ผูก event change ใหม่หลัง recreate select (เพราะ destroy ทำลาย listener เก่า)
        sel.addEventListener('change', onCategoryChangeForDetail);
    });
}

// เก็บ category_id ไว้ใน global เมื่อ selectpicker เปลี่ยนค่า (กัน select บางเวอร์ชันไม่ sync .value)
let currentCategoryId = '';

function onCategoryChangeForDetail() {
    currentCategoryId = String($(this).val() || '');
    document.getElementById('detail_input').value = '';
    document.getElementById('detail_suggest').innerHTML = '';
}

document.addEventListener("DOMContentLoaded", function() {
    $('.selectpicker').selectpicker();
    loadCategories();
});

// เปิด modal เพิ่มประเภท
function openAddCategoryModal() {
    new bootstrap.Modal(document.getElementById('addCategoryModal')).show();
}

// บันทึกประเภทใหม่
document.getElementById('addCategoryForm').addEventListener('submit', function(e) {
    e.preventDefault();

    fetch("expense_category_add_ajax.php", {
        method: "POST",
        body: new FormData(this)
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === "OK") {
            const modal = bootstrap.Modal.getInstance(document.getElementById('addCategoryModal'));
            modal.hide();
            loadCategories();
            document.getElementById('addCategoryForm').reset();
        } else {
            alert(data.message);
        }
    });
});

// Autocomplete ร้านค้า
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

// ===== Autocomplete ช่อง "รายละเอียด" (ดึงจากตาราง expenses ตาม category_id) =====
let detailSearchTimer = null;

function getCategoryId() {
    // ใช้ค่าที่เก็บจาก event change แทน (กัน selectpicker บางเวอร์ชันไม่ sync .val())
    return currentCategoryId || '';
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

// ปิดรายการแนะนำเมื่อคลิกที่อื่น
document.addEventListener('click', function(e) {
    if (e.target.id !== 'detail_input') {
        document.getElementById('detail_suggest').innerHTML = '';
    }
});
</script>

</body>
</html>
