<?php
include('../includes/auth.php');
check_login();
require_role('admin', 'accountant');
include('../includes/navbar.php');
include('../config/db_connect.php');

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$allowed_limits = [10, 30, 50, 100];
$perPage = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
if (!in_array($perPage, $allowed_limits)) $perPage = 10;
$offset = ($page - 1) * $perPage;

$params = [];
$where = "WHERE 1";
if ($q !== '') {
    $where .= " AND vendor_name LIKE ?";
    $params[] = "%$q%";
}

// count total
$count_sql = "SELECT COUNT(*) as cnt FROM vendors $where";
$stmt = $conn->prepare($count_sql);
if ($q !== '') $stmt->bind_param('s', $params[0]);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$total = (int)$res['cnt'];
$stmt->close();

$totalPages = max(1, (int)ceil($total / $perPage));

$list_sql = "SELECT id, vendor_name, description, disable 
             FROM vendors $where 
             ORDER BY vendor_name ASC 
             LIMIT ? OFFSET ?";
$stmt = $conn->prepare($list_sql);
if ($q !== '') {
    $stmt->bind_param('sii', $params[0], $perPage, $offset);
} else {
    $stmt->bind_param('ii', $perPage, $offset);
}
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการข้อมูลบริษัท / ร้านค้า</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body class="bg-light">

<div class="container mt-4">
    <h3 class="mb-3">จัดการข้อมูลบริษัท / ร้านค้า</h3>

    <div class="d-flex justify-content-between mb-3">
        <button class="btn btn-success" onclick="openAddModal()">
            <i class="bi bi-plus-lg"></i> เพิ่มบริษัท / ร้านค้า
        </button>

        <form class="d-flex" method="GET" action="vendors.php">
            <select name="limit" class="form-select me-2" style="width:auto" onchange="this.form.submit()">
                <?php foreach ($allowed_limits as $opt): ?>
                <option value="<?php echo $opt; ?>" <?php echo $perPage===$opt?'selected':''; ?>><?php echo $opt; ?> รายการ</option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>"
                   class="form-control me-2" placeholder="ค้นหาชื่อบริษัท / ร้านค้า...">
            <button class="btn btn-outline-primary" type="submit">ค้นหา</button>
        </form>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <table class="table table-striped table-bordered">
                <thead class="table-primary">
                    <tr>
                        <th>ชื่อบริษัท / ร้านค้า</th>
                        <th>รายละเอียด</th>
                        <th width="100">แก้ไข</th>
                        <th width="100">ลบ</th>
                        <th width="120">เปิด/ปิด</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['vendor_name']); ?></td>
                        <td><?php echo nl2br(htmlspecialchars($row['description'])); ?></td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-warning"
                                onclick="openEditModal(
                                    <?php echo $row['id']; ?>,
                                    '<?php echo htmlspecialchars(addslashes($row['vendor_name'])); ?>',
                                    '<?php echo htmlspecialchars(addslashes($row['description'])); ?>'
                                )">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-danger"
                                onclick="deleteVendor(<?php echo $row['id']; ?>)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm" onclick="toggleVendor(<?php echo $row['id']; ?>)">
                                <?php if ($row['disable'] == 0): ?>
                                    <i class="bi bi-eye-fill text-success" style="font-size:1.1rem"></i>
                                <?php else: ?>
                                    <i class="bi bi-eye-slash-fill text-danger" style="font-size:1.1rem"></i>
                                <?php endif; ?>
                            </button>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>

            <nav>
                <ul class="pagination">
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item <?php echo $p == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $p; ?>&q=<?php echo urlencode($q); ?>&limit=<?php echo $perPage; ?>">
                                <?php echo $p; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>

        </div>
    </div>

</div>

<!-- Add Modal -->
<div class="modal fade" id="addVendorModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="addVendorForm" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">เพิ่มบริษัท / ร้านค้าใหม่</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label class="form-label">ชื่อบริษัท / ร้านค้า</label>
        <input type="text" name="vendor_name" class="form-control" required>

        <label class="form-label mt-2">รายละเอียด</label>
        <textarea name="description" class="form-control" rows="3"></textarea>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-success">บันทึก</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editVendorModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="editVendorForm" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">แก้ไขข้อมูลบริษัท / ร้านค้า</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" id="edit_id">

        <label class="form-label">ชื่อบริษัท / ร้านค้า</label>
        <input type="text" name="vendor_name" id="edit_vendor_name" class="form-control" required>

        <label class="form-label mt-2">รายละเอียด</label>
        <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
      </div>
    </form>
  </div>
</div>

<script>
function openAddModal(){
    new bootstrap.Modal(document.getElementById('addVendorModal')).show();
}

document.getElementById('addVendorForm').addEventListener('submit', function(e){
    e.preventDefault();
    fetch('vendor_add_ajax.php', { method: 'POST', body: new FormData(this) })
        .then(r => r.json())
        .then(data => { if (data.status === 'OK') location.reload(); else alert(data.message || 'เกิดข้อผิดพลาด'); })
        .catch(()=>alert('Network error'));
});

function openEditModal(id, name, desc){
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_vendor_name').value = name;
    document.getElementById('edit_description').value = desc;
    new bootstrap.Modal(document.getElementById('editVendorModal')).show();
}

document.getElementById('editVendorForm').addEventListener('submit', function(e){
    e.preventDefault();
    fetch('vendor_update_ajax.php', { method: 'POST', body: new FormData(this) })
        .then(r=>r.json()).then(data=>{ if (data.status==='OK') location.reload(); else alert(data.message||'เกิดข้อผิดพลาด'); })
        .catch(()=>alert('Network error'));
});

function deleteVendor(id){
    if (!confirm('ลบบริษัทนี้จริงหรือไม่?')) return;
    fetch('vendor_delete_ajax.php?id='+id)
        .then(r=>r.json())
        .then(data=>{
            if (data.status === 'OK') {
                if (data.action === 'disabled') {
                    alert(data.message || 'บริษัทนี้ถูกตั้งเป็นปิดใช้งาน เนื่องจากมีการใช้งานอยู่');
                } else if (data.action === 'deleted') {
                    alert(data.message || 'ลบเรียบร้อยแล้ว');
                }
                location.reload();
            } else {
                alert(data.message || 'เกิดข้อผิดพลาด');
            }
        }).catch(()=>alert('Network error'));
}

function toggleVendor(id){
    fetch('vendor_toggle.php?id='+id)
        .then(r=>r.json())
        .then(data=>{ if (data.status==='OK') location.reload(); else alert(data.message||'เกิดข้อผิดพลาด'); })
        .catch(()=>alert('Network error'));
}
</script>

</body>
</html>
