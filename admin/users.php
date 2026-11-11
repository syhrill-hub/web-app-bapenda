<?php
session_start();
require_once '../config.php';
require_once 'includes/security.php';

// Cek autentikasi & Role
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}
require_permission('menu_users'); // Hanya user dengan izin kelola admin yang bisa membuka

$page_title = 'Manajemen Pengguna';

// Proses Hapus User
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = $_GET['delete'];
    if ($id == $_SESSION['admin_id']) {
        $_SESSION['flash_error'] = "Anda tidak bisa menghapus akun Anda sendiri!";
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $_SESSION['flash_success'] = "Pengguna berhasil dihapus!";
        } else {
            $_SESSION['flash_error'] = "Gagal menghapus pengguna.";
        }
    }
    header("Location: users.php");
    exit;
}

// Proses Tambah/Edit User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = "Token tidak valid.";
        header("Location: users.php");
        exit;
    }

    $id = $_POST['id'] ?? '';
    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $role_id = $_POST['role_id'];
    $password = $_POST['password'] ?? '';

    // Validasi dasar
    if (empty($username) || empty($full_name) || empty($role_id)) {
        $_SESSION['flash_error'] = "Username, Nama Lengkap, dan Role wajib diisi!";
        header("Location: users.php");
        exit;
    }

    if ($_POST['action'] === 'add') {
        if (empty($password)) {
            $_SESSION['flash_error'] = "Password wajib diisi untuk pengguna baru!";
            header("Location: users.php");
            exit;
        }

        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $_SESSION['flash_error'] = "Username sudah terdaftar!";
            header("Location: users.php");
            exit;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, full_name, email, password_hash, role_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $username, $full_name, $email, $hash, $role_id);
        
        if ($stmt->execute()) {
            $_SESSION['flash_success'] = "Pengguna baru berhasil ditambahkan.";
        } else {
            $_SESSION['flash_error'] = "Gagal menambahkan pengguna.";
        }

    } elseif ($_POST['action'] === 'edit' && !empty($id)) {
        // Cek username duplikat selain id ini
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->bind_param("si", $username, $id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $_SESSION['flash_error'] = "Username sudah dipakai pengguna lain!";
            header("Location: users.php");
            exit;
        }

        if (!empty($password)) {
            // Update dengan password
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET username=?, full_name=?, email=?, role_id=?, password_hash=? WHERE id=?");
            $stmt->bind_param("sssisi", $username, $full_name, $email, $role_id, $hash, $id);
        } else {
            // Update tanpa password
            $stmt = $conn->prepare("UPDATE users SET username=?, full_name=?, email=?, role_id=? WHERE id=?");
            $stmt->bind_param("sssii", $username, $full_name, $email, $role_id, $id);
        }

        if ($stmt->execute()) {
            $_SESSION['flash_success'] = "Data pengguna berhasil diperbarui.";
            // If editing self, we could update session but checking permissions dynamically is better handled at next login
            if ($id == $_SESSION['admin_id']) {
                // Fetch new permissions
                $stmt_perm = $conn->prepare("SELECT permissions FROM roles WHERE id=?");
                $stmt_perm->bind_param("i", $role_id);
                $stmt_perm->execute();
                $r_perm = $stmt_perm->get_result()->fetch_assoc();
                
                $_SESSION['admin_role_id'] = $role_id;
                $_SESSION['admin_permissions'] = $r_perm['permissions'] ?? '';
            }
        } else {
            $_SESSION['flash_error'] = "Gagal memperbarui pengguna.";
        }
    }
    header("Location: users.php");
    exit;
}

require_once 'layout_header.php';

// Ambil data users
$users = [];
$res = $conn->query("SELECT u.id, u.username, u.full_name, u.email, u.role_id, r.name as role_name, u.created_at FROM users u LEFT JOIN roles r ON u.role_id = r.id ORDER BY u.created_at DESC");
if ($res) {
    while($row = $res->fetch_assoc()) {
        $users[] = $row;
    }
}
?>

<div class="content">
    <?php if (isset($_SESSION['flash_success'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle" style="margin-right:8px;"></i> <?php echo $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?>
    </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle" style="margin-right:8px;"></i> <?php echo $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?>
    </div>
    <?php endif; ?>

    <div class="d-flex" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h2><i class="fas fa-users-cog" style="color:var(--primary);margin-right:10px;"></i> Manajemen Pengguna</h2>
        <button class="btn" onclick="openModal('addModal')"><i class="fas fa-plus"></i> Tambah Pengguna</button>
    </div>

    <!-- Tabel Pengguna -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead style="background:#f8fafc;">
                        <tr>
                            <th>No</th>
                            <th>Nama Lengkap</th>
                            <th>Username</th>
                            <th>Role / Hak Akses</th>
                            <th>Bergabung Pada</th>
                            <th style="text-align:right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $idx => $u): ?>
                        <tr>
                            <td><?php echo $idx + 1; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($u['full_name']); ?></strong><br>
                                <small style="color:#64748b;"><?php echo htmlspecialchars($u['email']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($u['username']); ?></td>
                            <td>
                                <?php if($u['role_id'] == 1): ?>
                                    <span style="background:#dcfce7; color:#16a34a; padding:4px 10px; border-radius:50px; font-size:12px; font-weight:600;"><i class="fas fa-crown"></i> <?php echo htmlspecialchars($u['role_name']); ?></span>
                                <?php else: ?>
                                    <span style="background:#e0f2fe; color:#0ea5e9; padding:4px 10px; border-radius:50px; font-size:12px; font-weight:600;"><i class="fas fa-user-tag"></i> <?php echo htmlspecialchars($u['role_name'] ?? 'Role Dihapus'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d M Y, H:i', strtotime($u['created_at'])); ?></td>
                            <td style="text-align:right;">
                                <button class="btn-action edit" onclick="editUser(<?php echo htmlspecialchars(json_encode($u)); ?>)"><i class="fas fa-edit"></i> Edit</button>
                                <?php if($u['id'] != $_SESSION['admin_id']): ?>
                                <button class="btn-action delete" onclick="confirmDelete(<?php echo $u['id']; ?>)"><i class="fas fa-trash"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($users)): ?>
                        <tr><td colspan="6" style="text-align:center;">Tidak ada pengguna.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Dialog Tambah/Edit -->
<div id="userModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:999; align-items:center; justify-content:center;">
    <div style="background:#fff; width:100%; max-width:500px; border-radius:12px; overflow:hidden;">
        <div style="padding:20px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
            <h3 id="modalTitle" style="margin:0; font-size:18px;">Form Pengguna</h3>
            <button onclick="document.getElementById('userModal').style.display='none'" style="background:none; border:none; cursor:pointer; font-size:18px;"><i class="fas fa-times"></i></button>
        </div>
        <form method="post" action="users.php" style="padding:20px;">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="formId" value="">
            <?php echo csrf_token_field(); ?>
            
            <div class="form-group">
                <label>Nama Lengkap</label>
                <input type="text" name="full_name" id="formFullName" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" id="formEmail" class="form-control">
            </div>
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" id="formUsername" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Pilih Role</label>
                <select name="role_id" id="formRole" class="form-control" required>
                    <?php 
                    $roles_dropdown = $conn->query("SELECT id, name FROM roles ORDER BY id ASC");
                    while($rd = $roles_dropdown->fetch_assoc()) {
                        echo '<option value="'.$rd['id'].'">'.htmlspecialchars($rd['name']).'</option>';
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label>Password <small style="font-weight:normal;" id="passHelp">(Kosongkan jika tidak ingin mengubah)</small></label>
                <input type="password" name="password" id="formPassword" class="form-control">
            </div>
            <div style="text-align:right;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('userModal').style.display='none'" style="margin-right:10px;">Batal</button>
                <button type="submit" class="btn"><i class="fas fa-save"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(type) {
    document.getElementById('modalTitle').innerText = 'Tambah Pengguna Baru';
    document.getElementById('formAction').value = 'add';
    document.getElementById('formId').value = '';
    document.getElementById('formFullName').value = '';
    document.getElementById('formEmail').value = '';
    document.getElementById('formUsername').value = '';
    document.getElementById('formRole').selectedIndex = 0;
    document.getElementById('formPassword').required = true;
    document.getElementById('passHelp').innerText = '(Wajib diisi)';
    
    document.getElementById('userModal').style.display = 'flex';
}

function editUser(data) {
    document.getElementById('modalTitle').innerText = 'Edit Pengguna';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('formId').value = data.id;
    document.getElementById('formFullName').value = data.full_name;
    document.getElementById('formEmail').value = data.email;
    document.getElementById('formUsername').value = data.username;
    document.getElementById('formRole').value = data.role_id;
    document.getElementById('formPassword').required = false;
    document.getElementById('passHelp').innerText = '(Kosongkan jika tidak ingin mengubah)';
    
    document.getElementById('userModal').style.display = 'flex';
}

function confirmDelete(id) {
    if(confirm('Yakin ingin menghapus pengguna ini secara permanen?')) {
        window.location.href = 'users.php?delete=' + id;
    }
}
</script>

<?php require_once 'layout_footer.php'; ?>
