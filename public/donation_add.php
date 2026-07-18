<?php
include('../includes/auth.php');
check_login();
include('../config/db_connect.php');
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เพิ่มรายการบริจาค</title>

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

    <h3>ทำรายการบริจาคใหม่</h3>
    <hr>

    <form method="POST" action="donation_save.php">

        <!-- วันที่บริจาค -->
        <div class="mb-3">
            <label class="form-label">วันที่บริจาค</label>
            <input type="date" name="donation_date" class="form-control" required>
        </div>

        <!-- ผู้นำบุญ -->
        <div class="mb-3">
            <label class="form-label">ผู้นำบุญ</label>
            <div class="input-group">
                <input type="text" id="donor_search" class="form-control" placeholder="พิมพ์ 2–3 ตัวเพื่อค้นหา" autocomplete="off">
                <button type="button" class="btn btn-primary" onclick="openAddDonorModal()">+</button>
            </div>
            <input type="hidden" name="donor_id" id="donor_id">
            <div id="donor_list" class="list-group mt-1"></div>
        </div>

        <hr>

        <!-- รายการทำบุญ -->
        <h5>รายการทำบุญ</h5>

        <div id="items_area">

            <div class="item-row border p-3 mb-3">

                <div class="mb-2">
                    <label class="form-label">ชื่อบนใบโม</label>
                    <input type="text" name="items[0][receipt_name]" class="form-control" required autocomplete="off">
                </div>

                <div class="mb-2">
                    <label class="form-label">ชื่อบุญ</label>
                    <div class="input-group">
                       <select class="selectpicker form-control merit_select"
                                data-live-search="true"
                                title="เลือกบุญ..."
                                name="items[0][merit_id]">
                        </select>

                        <button type="button" class="btn btn-primary" onclick="openAddMeritModal()">+</button>
                    </div>
                </div>

                <div class="mb-2">
                    <label class="form-label">จำนวนเงิน</label>
                    <input type="number" name="items[0][amount]" class="form-control" required>
                </div>

                <div class="mb-2">
                    <label class="form-label">หมายเหตุ</label>
                    <textarea name="items[0][comment]" class="form-control" rows="2" placeholder="หมายเหตุเพิ่มเติม (ถ้ามี)"></textarea>
                </div>

                <button type="button" class="btn btn-danger btn-sm" onclick="removeItem(this)">ลบรายการนี้</button>

            </div>

        </div>

        <button type="button" class="btn btn-secondary" onclick="addItem()">เพิ่มรายการทำบุญ</button>

        <hr>

        <button type="submit" class="btn btn-success">บันทึก</button>
        <a href="donations.php" class="btn btn-secondary">กลับ</a>

    </form>
</div>

<!-- Modal เพิ่มผู้นำบุญ -->
<div class="modal fade" id="addDonorModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="addDonorForm" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">เพิ่มผู้นำบุญใหม่</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <label class="form-label">ชื่อผู้นำบุญ</label>
        <input type="text" name="full_name" class="form-control" required>
      </div>

      <div class="modal-footer">
        <button type="submit" class="btn btn-success">บันทึก</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal เพิ่มบุญ -->
<div class="modal fade" id="addMeritModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="addMeritForm" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">เพิ่มบุญใหม่</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <label class="form-label">ชื่อบุญ</label>
        <input type="text" name="merit_name" class="form-control" required>
      </div>

      <div class="modal-footer">
        <button type="submit" class="btn btn-success">บันทึก</button>
      </div>
    </form>
  </div>
</div>

<script>
let itemIndex = 1;
let meritsList = [];

// ดึงรายการบุญทั้งหมด
function loadMerits() {
    fetch("merit_list_ajax.php")
        .then(r => r.json())
        .then(data => {
            meritsList = data;
            populateMeritSelects();
        })
        .catch(err => console.error("Error loading merits:", err));
}

// สร้างตัวเลือกบุญใหม่
function buildMeritOptions() {
    if (!meritsList || meritsList.length === 0) {
        return `<option value="" disabled>ไม่มีบุญที่เปิดใช้งาน</option>`;
    }
    return meritsList.map(m => `<option value="${m.id}">${m.merit_name}</option>`).join('');
}

// เติมข้อมูลลงใน select ทั้งหมด
function populateMeritSelects() {
    document.querySelectorAll('.merit_select').forEach(sel => {
        let selectedValue = sel.value;
        
        // Destroy selectpicker เก่า
        $(sel).selectpicker('destroy');
        
        // Update options
        sel.innerHTML = buildMeritOptions();
        if (selectedValue) sel.value = selectedValue;
        
        // Initialize selectpicker ใหม่
        $(sel).selectpicker();
    });
}

// refresh selectpicker หลังโหลดหน้า
document.addEventListener("DOMContentLoaded", function() {
  $('.selectpicker').selectpicker();
  loadMerits();
});

// เพิ่มรายการทำบุญ
function addItem() {
  let html = `
  <div class="item-row border p-3 mb-3">
      <div class="mb-2">
          <label class="form-label">ชื่อบนใบโม</label>
          <input type="text" name="items[${itemIndex}][receipt_name]" class="form-control" required>
      </div>

      <div class="mb-2">
          <label class="form-label">ชื่อบุญ</label>
          <div class="input-group">
              <select class="selectpicker form-control merit_select"
                      data-live-search="true"
                      title="เลือกบุญ..."
                      name="items[${itemIndex}][merit_id]">
                  ${buildMeritOptions()}
              </select>
              <button type="button" class="btn btn-primary" onclick="openAddMeritModal()">+</button>
          </div>
      </div>

      <div class="mb-2">
          <label class="form-label">จำนวนเงิน</label>
          <input type="number" name="items[${itemIndex}][amount]" class="form-control" required>
      </div>

      <div class="mb-2">
          <label class="form-label">หมายเหตุ</label>
          <textarea name="items[${itemIndex}][comment]" class="form-control" rows="2" placeholder="หมายเหตุเพิ่มเติม (ถ้ามี)"></textarea>
      </div>

      <button type="button" class="btn btn-danger btn-sm" onclick="removeItem(this)">ลบรายการนี้</button>
  </div>
  `;

  document.getElementById('items_area').insertAdjacentHTML('beforeend', html);
  
  // Initialize selectpicker สำหรับ element ที่เพิ่มเข้ามา
  const newSelects = document.querySelectorAll('.merit_select');
  newSelects.forEach(sel => {
    if (!$(sel).data('bs.select')) {
      $(sel).selectpicker();
    }
  });
  
  itemIndex++;
}

// ลบรายการทำบุญ
function removeItem(btn) {
    btn.closest('.item-row').remove();
}

// เปิด Modal ผู้นำบุญ
function openAddDonorModal() {
    new bootstrap.Modal(document.getElementById('addDonorModal')).show();
}

// เปิด Modal บุญ
function openAddMeritModal() {
    new bootstrap.Modal(document.getElementById('addMeritModal')).show();
}

// ===== Autocomplete ช่อง "ชื่อบนใบโม" =====
// พิมพ์ในช่องชื่อบนใบโมแล้วค้นหาชื่อที่ผู้นำบุญคนนี้เคยใช้ (รองรับทุกแถว)
let receiptSearchTimer = null;

// สร้าง/หา กล่องแสดงรายการที่ผูกกับ input แต่ละช่อง
function ensureReceiptSuggestBox(input) {
    let box = input.parentNode.querySelector('.receipt_suggest');
    if (!box) {
        input.parentNode.style.position = 'relative';
        box = document.createElement('div');
        box.className = 'receipt_suggest list-group';
        box.style.position = 'absolute';
        box.style.zIndex = '1050';
        box.style.width = '100%';
        box.style.maxHeight = '220px';
        box.style.overflowY = 'auto';
        input.parentNode.appendChild(box);
    }
    return box;
}

// ใช้ event delegation ที่ items_area เพื่อรองรับแถวที่เพิ่มแบบ dynamic
document.getElementById('items_area').addEventListener('input', function(e) {
    const input = e.target;
    if (!input.matches('input[name^="items"][name$="[receipt_name]"]')) return;

    const box = ensureReceiptSuggestBox(input);
    const donorId = document.getElementById('donor_id').value;
    const q = input.value.trim();

    if (!donorId) { box.innerHTML = ''; return; }   // ต้องเลือกผู้นำบุญก่อน
    if (q.length < 1) { box.innerHTML = ''; return; }

    clearTimeout(receiptSearchTimer);
    receiptSearchTimer = setTimeout(function() {
        fetch("donor_receipt_search_ajax.php?donor_id=" + encodeURIComponent(donorId) +
              "&q=" + encodeURIComponent(q))
            .then(r => r.json())
            .then(names => {
                box.innerHTML = '';
                if (!Array.isArray(names)) return;
                names.forEach(name => {
                    const div = document.createElement('div');
                    div.className = 'list-group-item list-group-item-action';
                    div.textContent = name;
                    // ใช้ mousedown กัน blur ทำงานก่อน แล้วเลือกค่าลงช่อง
                    div.onmousedown = function(ev) {
                        ev.preventDefault();
                        input.value = name;
                        box.innerHTML = '';
                    };
                    box.appendChild(div);
                });
            })
            .catch(err => console.error("Error searching receipt names:", err));
    }, 250);
});

// ปิดรายการแนะนำเมื่อคลิกที่อื่น
document.addEventListener('click', function(e) {
    if (!e.target.matches('input[name^="items"][name$="[receipt_name]"]')) {
        document.querySelectorAll('.receipt_suggest').forEach(b => b.innerHTML = '');
    }
});


// ค้นหาผู้นำบุญแบบ autocomplete
document.getElementById('donor_search').addEventListener('keyup', function() {
    let q = this.value.trim();

    if (q.length < 2) {
        document.getElementById('donor_list').innerHTML = "";
        return;
    }

    fetch("donor_search_ajax.php?q=" + encodeURIComponent(q))
        .then(r => r.json())
        .then(data => {

            let list = document.getElementById('donor_list');
            list.innerHTML = "";

            data.forEach(item => {
                let div = document.createElement('div');
                div.className = "list-group-item list-group-item-action";
                div.textContent = item.full_name;

                div.onclick = function() {
                    document.getElementById('donor_search').value = item.full_name;
                    document.getElementById('donor_id').value = item.id;
                    list.innerHTML = "";
                };


                list.appendChild(div);
            });
        });
});

// บันทึกผู้นำบุญใหม่ผ่าน AJAX (JSON)
document.getElementById('addDonorForm').addEventListener('submit', function(e) {
    e.preventDefault();

    fetch("donor_add_ajax.php", {
        method: "POST",
        body: new FormData(this)
    })
    .then(r => r.json())
    .then(data => {

        if (data.status === "OK") {

            const modal = bootstrap.Modal.getInstance(document.getElementById('addDonorModal'));
            modal.hide();

            document.getElementById('donor_search').value = data.full_name;
            document.getElementById('donor_id').value = data.id;
            document.getElementById('addDonorForm').reset();

        } else {
            alert(data.message);
        }
    })
    .catch(err => console.error("JSON parse error (donor):", err));
});

// บันทึกบุญใหม่ผ่าน AJAX (JSON)
document.getElementById('addMeritForm').addEventListener('submit', function(e) {
    e.preventDefault();

    fetch("merit_save_ajax.php", {
        method: "POST",
        body: new FormData(this)
    })
    .then(r => r.json())
    .then(data => {

        if (data.status === "OK") {

            const modal = bootstrap.Modal.getInstance(document.getElementById('addMeritModal'));
            modal.hide();

            // โหลดรายการบุญใหม่และอัปเดตทุก dropdown
            loadMerits();
            document.getElementById('addMeritForm').reset();

        } else {
            alert(data.message);
        }
    })
    .catch(err => console.error("JSON parse error (merit):", err));
});

</script>
</body>
</html>
