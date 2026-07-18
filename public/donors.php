<?php

include('../includes/auth.php');
check_login();
require_role('admin', 'accountant');
include('../config/db_connect.php');

// helper: check if a column exists in current database
function has_column($conn, $table, $column) {
    $sql = "SELECT COUNT(*) AS cnt FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '$table' AND column_name = '$column'";
    $res = $conn->query($sql);
    if (!$res) return false;
    $r = $res->fetch_assoc();
    return (int)$r['cnt'] > 0;
}

function ensure_column($conn, $table, $column, $definition) {
    if (has_column($conn, $table, $column)) {
        return true;
    }
    $sql = "ALTER TABLE $table ADD COLUMN $column $definition";
    return (bool)$conn->query($sql);
}

$hasIsDeleted = ensure_column($conn, 'donors', 'is_deleted', 'TINYINT(1) NOT NULL DEFAULT 0');

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

$params = [];
$where = "WHERE 1";
if ($q !== '') {
    $where .= " AND full_name LIKE ?";
    $params[] = "%$q%";
}

$count_sql = '';
// count total (use escaped query to avoid prepare issues)
if ($q !== '') {
    $like = $conn->real_escape_string("%$q%");
    $count_sql = "SELECT COUNT(*) as cnt FROM donors WHERE full_name LIKE '$like'";
} else {
    $count_sql = "SELECT COUNT(*) as cnt FROM donors";
}
$resCnt = $conn->query($count_sql);
if (!$resCnt) { echo 'DB query failed (count): ' . htmlspecialchars($conn->error); exit; }
$rCnt = $resCnt->fetch_assoc();
$total = (int)$rCnt['cnt'];

$totalPages = max(1, (int)ceil($total / $perPage));

// list rows (include is_deleted if available, otherwise return 0 as is_deleted)
$isDeletedSelect = $hasIsDeleted ? 'is_deleted' : '0 AS is_deleted';
if ($q !== '') {
    $like = $conn->real_escape_string("%$q%");
    $list_sql = "SELECT id, full_name, birth_date, phone, line_id, $isDeletedSelect FROM donors WHERE full_name LIKE '$like' ORDER BY full_name ASC LIMIT $perPage OFFSET $offset";
} else {
    $list_sql = "SELECT id, full_name, birth_date, phone, line_id, $isDeletedSelect FROM donors ORDER BY full_name ASC LIMIT $perPage OFFSET $offset";
}

$res = $conn->query($list_sql);
if (!$res) { echo 'DB query failed (list): ' . htmlspecialchars($conn->error); exit; }

$rows = [];
while ($r = $res->fetch_assoc()) {
    $rows[] = $r;
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการผู้บริจาค</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body class="bg-light">
    <?php include('../includes/navbar.php'); ?>
<div class="container mt-4">
    <h3 class="mb-3">จัดการผู้บริจาค</h3>

    <div class="d-flex justify-content-between mb-3">
        <div>
            <button class="btn btn-success" onclick="openAddModal()"><i class="bi bi-plus-lg"></i> เพิ่มผู้บริจาค</button>
        </div>

        <form class="d-flex" method="GET" action="donors.php">
            <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" class="form-control me-2" placeholder="ค้นหาชื่อผู้บริจาค...">
            <button class="btn btn-outline-primary" type="submit">ค้นหา</button>
        </form>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <table class="table table-striped table-bordered">
                <thead class="table-primary">
                    <tr>
                        <th>ชื่อ</th>
                        <th>โทรศัพท์</th>
                        <th>Line ID</th>
                        <th>วันเกิด</th>
                        <th width="100">แก้ไข</th>
                        <th width="100">ลบ</th>
                        <th width="120">เปิด/ปิด</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['phone']); ?></td>
                        <td><?php echo htmlspecialchars($row['line_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['birth_date']); ?></td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-warning" onclick="openEditModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['full_name'])); ?>', '<?php echo htmlspecialchars(addslashes($row['phone'])); ?>', '<?php echo htmlspecialchars(addslashes($row['line_id'])); ?>', '<?php echo htmlspecialchars(addslashes($row['birth_date'])); ?>')">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-danger" onclick="deleteDonor(<?php echo $row['id']; ?>)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm" onclick="toggleDonor(<?php echo $row['id']; ?>)" title="สลับการมองเห็น">
                                <?php if ($row['is_deleted'] == 0): ?>
                                    <i class="bi bi-eye-fill text-success" style="font-size:1.1rem"></i>
                                <?php else: ?>
                                    <i class="bi bi-eye-slash-fill text-danger" style="font-size:1.1rem"></i>
                                <?php endif; ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <nav>
                <ul class="pagination">
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item <?php echo $p == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $p; ?>&q=<?php echo urlencode($q); ?>"><?php echo $p; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>

        </div>
    </div>

</div>

<!-- Add Modal -->
<div class="modal fade" id="addDonorModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="addDonorForm" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">เพิ่มผู้บริจาคใหม่</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label class="form-label">ชื่อ</label>
        <input type="text" name="full_name" class="form-control" required>
        <label class="form-label mt-2">โทรศัพท์</label>
        <input type="text" name="phone" class="form-control">
        <label class="form-label mt-2">Line ID</label>
        <input type="text" name="line_id" class="form-control">
        <label class="form-label mt-2">วันเกิด</label>
        <input type="date" name="birth_date" class="form-control">
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-success">บันทึก</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editDonorModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="editDonorForm" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">แก้ไขผู้บริจาค</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" id="edit_id">
        <label class="form-label">ชื่อ</label>
        <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
        <label class="form-label mt-2">โทรศัพท์</label>
        <input type="text" name="phone" id="edit_phone" class="form-control">
        <label class="form-label mt-2">Line ID</label>
        <input type="text" name="line_id" id="edit_line_id" class="form-control">
        <label class="form-label mt-2">วันเกิด</label>
        <input type="date" name="birth_date" id="edit_birth_date" class="form-control">
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
      </div>
    </form>
  </div>
</div>

<script>
function openAddModal(){ new bootstrap.Modal(document.getElementById('addDonorModal')).show(); }

document.getElementById('addDonorForm').addEventListener('submit', function(e){
    e.preventDefault();
    fetch('donor_add_ajax.php', { method: 'POST', body: new FormData(this) })
        .then(r=>r.json()).then(data=>{ if (data.status==='OK') location.reload(); else alert(data.message||'เกิดข้อผิดพลาด'); }).catch(()=>alert('Network error'));
});

function openEditModal(id, name, phone, line_id, birth_date){
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_full_name').value = name;
    document.getElementById('edit_phone').value = phone;
    document.getElementById('edit_line_id').value = line_id;
    document.getElementById('edit_birth_date').value = birth_date;
    new bootstrap.Modal(document.getElementById('editDonorModal')).show();
}

document.getElementById('editDonorForm').addEventListener('submit', function(e){
    e.preventDefault();
    fetch('donor_update_ajax.php', { method: 'POST', body: new FormData(this) })
        .then(r=>r.json()).then(data=>{ if (data.status==='OK') location.reload(); else alert(data.message||'เกิดข้อผิดพลาด'); }).catch(()=>alert('Network error'));
});

function deleteDonor(id){
    if (!confirm('ลบผู้บริจาคนี้จริงหรือไม่?')) return;
    fetch('donor_delete_ajax.php?id='+id).then(r=>r.json()).then(data=>{
        if (data.status==='OK'){
            if (data.action === 'disabled') alert(data.message || 'ผู้บริจาคถูกตั้งเป็นปิดการมองเห็น เนื่องจากมีการใช้งานอยู่');
            else if (data.action === 'deleted') alert(data.message || 'ลบผู้บริจาคเรียบร้อยแล้ว');
            location.reload();
        } else alert(data.message || 'เกิดข้อผิดพลาด');
    }).catch(()=>alert('Network error'));
}

function toggleDonor(id){ fetch('donor_toggle.php?id='+id).then(r=>r.json()).then(data=>{ if (data.status==='OK') location.reload(); else alert(data.message||'เกิดข้อผิดพลาด'); }).catch(()=>alert('Network error')); }
</script>

</body>
</html>
