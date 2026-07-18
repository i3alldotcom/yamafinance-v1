<?php
include('../includes/auth.php');
check_login();
require_role('admin');
include('../includes/navbar.php');
include('../config/db_connect.php');

$sql = "SELECT id, full_name, username, role, is_active 
        FROM users 
        ORDER BY full_name ASC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการผู้ใช้</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container mt-4">

    <h3 class="mb-4">จัดการผู้ใช้</h3>

    <!-- ปุ่มเพิ่มผู้ใช้แบบ Popup -->
    <button class="btn btn-success mb-3" onclick="openAddModal()">
        <i class="bi bi-person-plus-fill"></i> เพิ่มผู้ใช้ใหม่
    </button>

    <div class="card shadow-sm">
        <div class="card-body">

            <table class="table table-bordered table-striped">
                <thead class="table-primary">
                    <tr>
                        <th>ชื่อ - นามสกุล</th>
                        <th>ชื่อผู้ใช้</th>
                        <th>ประเภทผู้ใช้</th>
                        <th>สถานะ</th>
                        <th>จัดการ</th>
                        <th>รีเซ็ตรหัสผ่าน</th>
                    </tr>
                </thead>
                <tbody>
                   <?php while ($row = $result->fetch_assoc()): ?>

    <?php if ($row['id'] == $_SESSION['user_id']) continue; ?>

    <tr>
        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
        <td><?php echo htmlspecialchars($row['username']); ?></td>
        <td>
            <?php
                if ($row['role'] == 'admin') echo "เจ้าอาวาส / ผู้ดูแลระบบ";
                elseif ($row['role'] == 'accountant') echo "เจ้าหน้าที่บัญชี";
                else echo "เจ้าหน้าที่คีย์ข้อมูล";
            ?>
        </td>

        <td class="text-center">
            <i class="bi <?php echo ($row['is_active'] ? 'bi-eye-fill text-success' : 'bi-eye-slash-fill text-danger'); ?>"
               style="font-size: 1.8rem; cursor:pointer;"
               onclick="toggleStatus(<?php echo $row['id']; ?>)">
            </i>
        </td>

        <td>
            <button class="btn btn-warning btn-sm"
                    onclick="openEditModal(
                        <?php echo $row['id']; ?>,
                        '<?php echo htmlspecialchars($row['full_name']); ?>',
                        '<?php echo htmlspecialchars($row['username']); ?>',
                        '<?php echo $row['role']; ?>'
                    )">
                <i class="bi bi-pencil-square"></i> แก้ไข
            </button>
        </td>
        <td>
            <button class="btn btn-primary btn-sm" onclick="resetPassword('<?php echo $row['id']; ?>')">
                <i class="bi bi-key"></i> รีเซ็ต
            </button>
        </td>
    </tr>

<?php endwhile; ?>

                </tbody>
            </table>

        </div>
    </div>

</div>

<!-- Modal เพิ่มผู้ใช้ -->
<div class="modal fade" id="addUserModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="addUserForm" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">เพิ่มผู้ใช้ใหม่</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <div class="mb-3">
            <label class="form-label">ชื่อ - นามสกุล</label>
            <input type="text" name="full_name" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">ชื่อผู้ใช้</label>
            <input type="text" name="username" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">รหัสผ่าน</label>
            <input type="password" name="password" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">ประเภทผู้ใช้</label>
            <select name="role" class="form-select" required>
                <option value="admin">เจ้าอาวาส / ผู้ดูแลระบบ</option>
                <option value="accountant">เจ้าหน้าที่บัญชี</option>
                <option value="data_entry">เจ้าหน้าที่คีย์ข้อมูล</option>
            </select>
        </div>

      </div>

      <div class="modal-footer">
        <button type="submit" class="btn btn-success">บันทึกผู้ใช้</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal แก้ไขข้อมูลผู้ใช้ -->
<div class="modal fade" id="editUserModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="user_edit_save.php" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">แก้ไขข้อมูลผู้ใช้</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <input type="hidden" name="id" id="edit_id">

        <div class="mb-3">
            <label class="form-label">ชื่อ - นามสกุล</label>
            <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">ชื่อผู้ใช้</label>
            <input type="text" name="username" id="edit_username" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">ประเภทผู้ใช้</label>
            <select name="role" id="edit_role" class="form-select" required>
                <option value="admin">เจ้าอาวาส / ผู้ดูแลระบบ</option>
                <option value="accountant">เจ้าหน้าที่บัญชี</option>
                <option value="data_entry">เจ้าหน้าที่คีย์ข้อมูล</option>
            </select>
        </div>

      </div>

      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// เปิด Popup เพิ่มผู้ใช้
function openAddModal() {
    var modal = new bootstrap.Modal(document.getElementById('addUserModal'));
    modal.show();
}

// บันทึกผู้ใช้ใหม่แบบ AJAX
document.getElementById('addUserForm').addEventListener('submit', function(e) {
    e.preventDefault();

    fetch("user_add_ajax.php", {
        method: "POST",
        body: new FormData(this)
    })
    .then(response => response.text())
    .then(data => {
        if (data === "OK") {
            location.reload();
        } else {
            alert(data);
        }
    });
});

// เปิด Popup แก้ไข
function openEditModal(id, full_name, username, role) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_full_name').value = full_name;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_role').value = role;

    var modal = new bootstrap.Modal(document.getElementById('editUserModal'));
    modal.show();
}

// สลับสถานะผู้ใช้แบบ AJAX
function toggleStatus(id) {
    fetch("user_toggle_status.php?id=" + id)
        .then(response => response.text())
        .then(data => {
            location.reload();
        });
}
</script>
<script>
function resetPassword(userId) {
  if (confirm("คุณต้องการรีเซ็ตรหัสผ่านของผู้ใช้นี้หรือไม่?")) {
    fetch('reset_password.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: 'id=' + userId
    })
    .then(response => response.text())
    .then(data => {
      alert("รีเซ็ตรหัสผ่านแล้ว กรุณาล็อคอินด้วยรหัส 1234 แล้วรีบเปลี่ยนทันที");
      location.reload();
    })
    .catch(error => alert("เกิดข้อผิดพลาด: " + error));
  }
}
</script>

</body>
</html>
