<?php
require_once __DIR__ . '/../includes/security_helpers.php';
bapenda_start_secure_session();
require_once '../config.php';
require_once 'includes/security.php';

// Cek autentikasi, sinkronisasi sesi live, & hak akses
require_permission('menu_users');

$page_title = 'Manajemen Pengguna';
$has_is_active = users_has_is_active_column($conn);
$current_admin_id = (int)($_SESSION['admin_id'] ?? 0);
$current_role_id = (int)($_SESSION['admin_role_id'] ?? 0);

// Proses Hapus User (CSRF POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = "Token keamanan tidak valid.";
        header("Location: users.php");
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        $_SESSION['flash_error'] = "ID pengguna tidak valid.";
    } elseif ($id === $current_admin_id) {
        $_SESSION['flash_error'] = "Anda tidak bisa menghapus akun Anda sendiri!";
    } elseif ($id === 1) {
        $_SESSION['flash_error'] = "Akun Super Admin utama tidak dapat dihapus!";
    } else {
        // Cegah non-Super-Admin menghapus akun dengan hak akses di luar kewenangannya
        $stmt_target = $conn->prepare("SELECT u.role_id, r.permissions AS role_permissions FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ? LIMIT 1");
        $stmt_target->bind_param("i", $id);
        $stmt_target->execute();
        $target_row = $stmt_target->get_result()->fetch_assoc();
        $stmt_target->close();

        if (!$target_row) {
            $_SESSION['flash_error'] = "Pengguna tidak ditemukan.";
        } elseif ($current_role_id !== 1 && ((int)$target_row['role_id'] === 1 || !actor_can_manage_permissions((string)($target_row['role_permissions'] ?? '')))) {
            $_SESSION['flash_error'] = "Akses ditolak: Anda tidak dapat menghapus pengguna dengan hak akses di luar kewenangan Anda.";
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = "Pengguna berhasil dihapus!";
            } else {
                $_SESSION['flash_error'] = "Gagal menghapus pengguna.";
            }
            $stmt->close();
        }
    }
    header("Location: users.php");
    exit;
}

// Proses Toggle Status Aktif/Nonaktif (CSRF POST)
if ($has_is_active && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_active') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = "Token keamanan tidak valid.";
        header("Location: users.php");
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        $_SESSION['flash_error'] = "ID pengguna tidak valid.";
    } elseif ($id === $current_admin_id) {
        $_SESSION['flash_error'] = "Anda tidak bisa menonaktifkan akun Anda sendiri!";
    } elseif ($id === 1) {
        $_SESSION['flash_error'] = "Akun Super Admin utama tidak dapat dinonaktifkan!";
    } else {
        $stmt_target = $conn->prepare("SELECT u.role_id, u.is_active, r.permissions AS role_permissions FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ? LIMIT 1");
        $stmt_target->bind_param("i", $id);
        $stmt_target->execute();
        $target_row = $stmt_target->get_result()->fetch_assoc();
        $stmt_target->close();

        if (!$target_row) {
            $_SESSION['flash_error'] = "Pengguna tidak ditemukan.";
        } elseif ($current_role_id !== 1 && ((int)$target_row['role_id'] === 1 || !actor_can_manage_permissions((string)($target_row['role_permissions'] ?? '')))) {
            $_SESSION['flash_error'] = "Akses ditolak: Anda tidak dapat mengubah status pengguna dengan hak akses di luar kewenangan Anda.";
        } else {
            $new_status = ((int)$target_row['is_active'] === 1) ? 0 : 1;
            $stmt_upd = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ?");
            $stmt_upd->bind_param("ii", $new_status, $id);
            if ($stmt_upd->execute()) {
                $_SESSION['flash_success'] = $new_status === 1
                    ? "Akun pengguna berhasil diaktifkan."
                    : "Akun pengguna berhasil dinonaktifkan.";
            } else {
                $_SESSION['flash_error'] = "Gagal mengubah status pengguna.";
            }
            $stmt_upd->close();
        }
    }
    header("Location: users.php");
    exit;
}

// Proses Tambah/Edit User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['add', 'edit'], true)) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = "Token keamanan tidak valid.";
        header("Location: users.php");
        exit;
    }

    $action = $_POST['action'];
    $id = (int)($_POST['id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role_id = (int)($_POST['role_id'] ?? 0);
    $password = (string)($_POST['password'] ?? '');
    $is_active = isset($_POST['is_active']) && (string)$_POST['is_active'] === '0' ? 0 : 1;

    // Validasi dasar
    if ($username === '' || $full_name === '' || $role_id <= 0) {
        $_SESSION['flash_error'] = "Username, Nama Lengkap, dan Role wajib diisi!";
        header("Location: users.php");
        exit;
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['flash_error'] = "Format alamat email tidak valid!";
        header("Location: users.php");
        exit;
    }

    // Pastikan role_id ada di tabel roles
    $stmt_role = $conn->prepare("SELECT id, permissions FROM roles WHERE id = ? LIMIT 1");
    $stmt_role->bind_param("i", $role_id);
    $stmt_role->execute();
    $role_data = $stmt_role->get_result()->fetch_assoc();
    $stmt_role->close();

    if (!$role_data) {
        $_SESSION['flash_error'] = "Role yang dipilih tidak valid atau sudah dihapus.";
        header("Location: users.php");
        exit;
    }

    // Cegah non-Super-Admin mengangkat role menjadi Super Admin (id=1) atau role di atas hak aksesnya
    if ($current_role_id !== 1 && ($role_id === 1 || !actor_can_manage_permissions((string)($role_data['permissions'] ?? '')))) {
        $_SESSION['flash_error'] = "Akses ditolak: Anda tidak dapat menetapkan role dengan hak akses di luar kewenangan Anda.";
        header("Location: users.php");
        exit;
    }

    if ($action === 'add') {
        if ($password === '') {
            $_SESSION['flash_error'] = "Password wajib diisi untuk pengguna baru!";
            header("Location: users.php");
            exit;
        }
        if (strlen($password) < 8) {
            $_SESSION['flash_error'] = "Password minimal harus 8 karakter!";
            header("Location: users.php");
            exit;
        }

        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $stmt->close();
            $_SESSION['flash_error'] = "Username sudah terdaftar!";
            header("Location: users.php");
            exit;
        }
        $stmt->close();

        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($has_is_active) {
            $stmt = $conn->prepare("INSERT INTO users (username, full_name, email, password_hash, role_id, is_active) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssii", $username, $full_name, $email, $hash, $role_id, $is_active);
        } else {
            $stmt = $conn->prepare("INSERT INTO users (username, full_name, email, password_hash, role_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssi", $username, $full_name, $email, $hash, $role_id);
        }

        if ($stmt->execute()) {
            $_SESSION['flash_success'] = "Pengguna baru berhasil ditambahkan.";
        } else {
            $_SESSION['flash_error'] = "Gagal menambahkan pengguna.";
        }
        $stmt->close();
    } elseif ($action === 'edit' && $id > 0) {
        $stmt_existing = $conn->prepare("SELECT u.id, u.role_id, r.permissions AS role_permissions FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ? LIMIT 1");
        $stmt_existing->bind_param("i", $id);
        $stmt_existing->execute();
        $existing_user = $stmt_existing->get_result()->fetch_assoc();
        $stmt_existing->close();

        if (!$existing_user) {
            $_SESSION['flash_error'] = "Pengguna tidak ditemukan.";
            header("Location: users.php");
            exit;
        }

        if ($current_role_id !== 1 && ((int)$existing_user['role_id'] === 1 || !actor_can_manage_permissions((string)($existing_user['role_permissions'] ?? '')))) {
            $_SESSION['flash_error'] = "Akses ditolak: Anda tidak dapat mengedit pengguna dengan hak akses di luar kewenangan Anda.";
            header("Location: users.php");
            exit;
        }

        if ($current_role_id !== 1 && $id === $current_admin_id && $role_id !== (int)$existing_user['role_id']) {
            $_SESSION['flash_error'] = "Anda tidak dapat mengubah role akun Anda sendiri.";
            header("Location: users.php");
            exit;
        }

        // Kunci role Super Admin utama (id=1) tetap role_id=1 dan aktif
        if ($id === 1) {
            $role_id = 1;
            $is_active = 1;
        }
        // Tidak bisa menonaktifkan akun sendiri
        if ($id === $current_admin_id) {
            $is_active = 1;
        }

        if ($password !== '' && strlen($password) < 8) {
            $_SESSION['flash_error'] = "Password baru minimal harus 8 karakter!";
            header("Location: users.php");
            exit;
        }

        // Cek username duplikat selain id ini
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1");
        $stmt->bind_param("si", $username, $id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $stmt->close();
            $_SESSION['flash_error'] = "Username sudah dipakai pengguna lain!";
            header("Location: users.php");
            exit;
        }
        $stmt->close();

        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            if ($has_is_active) {
                $stmt = $conn->prepare("UPDATE users SET username=?, full_name=?, email=?, role_id=?, is_active=?, password_hash=? WHERE id=?");
                $stmt->bind_param("sssiisi", $username, $full_name, $email, $role_id, $is_active, $hash, $id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET username=?, full_name=?, email=?, role_id=?, password_hash=? WHERE id=?");
                $stmt->bind_param("sssisi", $username, $full_name, $email, $role_id, $hash, $id);
            }
        } else {
            if ($has_is_active) {
                $stmt = $conn->prepare("UPDATE users SET username=?, full_name=?, email=?, role_id=?, is_active=? WHERE id=?");
                $stmt->bind_param("sssiii", $username, $full_name, $email, $role_id, $is_active, $id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET username=?, full_name=?, email=?, role_id=? WHERE id=?");
                $stmt->bind_param("sssii", $username, $full_name, $email, $role_id, $id);
            }
        }

        if ($stmt->execute()) {
            $_SESSION['flash_success'] = "Data pengguna berhasil diperbarui.";
            if ($id === $current_admin_id) {
                if ($password !== '') {
                    session_regenerate_id(true);
                    rotate_csrf_token();
                }
                sync_current_user_session($conn, true);
            }
        } else {
            $_SESSION['flash_error'] = "Gagal memperbarui pengguna.";
        }
        $stmt->close();
    } else {
        $_SESSION['flash_error'] = "Data pengguna tidak valid.";
    }
    header("Location: users.php");
    exit;
}

require_once 'layout_header.php';

// Ambil data users
$users = [];
$active_col = $has_is_active ? ", u.is_active" : "";
$res = $conn->query("SELECT u.id, u.username, u.full_name, u.email, u.role_id{$active_col}, r.name as role_name, r.permissions as role_permissions, u.created_at FROM users u LEFT JOIN roles r ON u.role_id = r.id ORDER BY u.id ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $users[] = $row;
    }
}

// Ambil daftar role untuk dropdown
$roles_list = [];
$roles_res = $conn->query("SELECT id, name, permissions FROM roles ORDER BY id ASC");
if ($roles_res) {
    while ($rd = $roles_res->fetch_assoc()) {
        $roles_list[] = $rd;
    }
}
?>

<div class="content">
    <?php if (isset($_SESSION['flash_success'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle" style="margin-right:8px;"></i> <?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
    </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle" style="margin-right:8px;"></i> <?php echo htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
    </div>
    <?php endif; ?>

    <div class="d-flex" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h2><i class="fas fa-users-cog" style="color:var(--primary);margin-right:10px;"></i> Manajemen Pengguna</h2>
        <button type="button" class="btn" onclick="openModal('add')"><i class="fas fa-plus"></i> Tambah Pengguna</button>
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
                            <?php if ($has_is_active): ?>
                            <th>Status</th>
                            <?php endif; ?>
                            <th>Bergabung Pada</th>
                            <th style="text-align:right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $idx => $u):
                            $uid = (int)$u['id'];
                            $u_role_id = (int)$u['role_id'];
                            $u_active = $has_is_active ? ((int)($u['is_active'] ?? 1) === 1) : true;
                            $can_manage_target = ($current_role_id === 1) || ($u_role_id !== 1 && actor_can_manage_permissions((string)($u['role_permissions'] ?? '')));
                            $user_payload = [
                                'id' => $uid,
                                'full_name' => (string)($u['full_name'] ?? ''),
                                'email' => (string)($u['email'] ?? ''),
                                'username' => (string)($u['username'] ?? ''),
                                'role_id' => $u_role_id,
                                'is_active' => $u_active ? 1 : 0,
                            ];
                            $user_json = htmlspecialchars(bapenda_json_encode($user_payload), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr>
                            <td><?php echo $idx + 1; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars((string)($u['full_name'] ?? '')); ?></strong><br>
                                <small style="color:#64748b;"><?php echo htmlspecialchars((string)($u['email'] ?? '')); ?></small>
                            </td>
                            <td>
                                <?php echo htmlspecialchars((string)($u['username'] ?? '')); ?>
                                <?php if ($uid === $current_admin_id): ?>
                                    <span style="background:#f1f5f9; color:#475569; padding:2px 6px; border-radius:4px; font-size:11px; margin-left:4px;">Anda</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u_role_id === 1): ?>
                                    <span style="background:#dcfce7; color:#16a34a; padding:4px 10px; border-radius:50px; font-size:12px; font-weight:600;"><i class="fas fa-crown"></i> <?php echo htmlspecialchars((string)($u['role_name'] ?? 'Super Admin')); ?></span>
                                <?php else: ?>
                                    <span style="background:#e0f2fe; color:#0ea5e9; padding:4px 10px; border-radius:50px; font-size:12px; font-weight:600;"><i class="fas fa-user-tag"></i> <?php echo htmlspecialchars((string)($u['role_name'] ?? 'Role Dihapus')); ?></span>
                                <?php endif; ?>
                            </td>
                            <?php if ($has_is_active): ?>
                            <td>
                                <?php if ($u_active): ?>
                                    <span style="background:#dcfce7; color:#15803d; padding:4px 10px; border-radius:50px; font-size:12px; font-weight:600;"><i class="fas fa-check-circle"></i> Aktif</span>
                                <?php else: ?>
                                    <span style="background:#fee2e2; color:#b91c1c; padding:4px 10px; border-radius:50px; font-size:12px; font-weight:600;"><i class="fas fa-ban"></i> Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                            <td><?php echo !empty($u['created_at']) ? date('d M Y, H:i', strtotime($u['created_at'])) : '-'; ?></td>
                            <td style="text-align:right; white-space:nowrap;">
                                <?php if ($can_manage_target): ?>
                                    <button type="button" class="btn-action edit" onclick="editUser(<?php echo $user_json; ?>)"><i class="fas fa-edit"></i> Edit</button>
                                    <?php if ($has_is_active && $uid !== $current_admin_id && $uid !== 1): ?>
                                        <button type="button" class="btn-action" style="background:<?php echo $u_active ? '#fef3c7; color:#b45309;' : '#dcfce7; color:#15803d;'; ?>" title="<?php echo $u_active ? 'Nonaktifkan Akun' : 'Aktifkan Akun'; ?>" onclick="toggleUserActive(<?php echo $uid; ?>, <?php echo htmlspecialchars(bapenda_json_encode($user_payload['username']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo $u_active ? 'false' : 'true'; ?>)">
                                            <i class="fas <?php echo $u_active ? 'fa-user-slash' : 'fa-user-check'; ?>"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($uid !== $current_admin_id && $uid !== 1): ?>
                                        <button type="button" class="btn-action delete" onclick="confirmDelete(<?php echo $uid; ?>, <?php echo htmlspecialchars(bapenda_json_encode($user_payload['username']), ENT_QUOTES, 'UTF-8'); ?>)"><i class="fas fa-trash"></i></button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color:#94a3b8; font-size:12px;"><i class="fas fa-lock"></i> Terkunci</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($users)): ?>
                        <tr><td colspan="<?php echo $has_is_active ? 7 : 6; ?>" style="text-align:center;">Tidak ada pengguna.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Form Aksi Baris Tabel (CSRF POST) -->
<form id="userActionForm" method="post" action="users.php" style="display:none;">
    <input type="hidden" name="action" id="userRowAction" value="">
    <input type="hidden" name="id" id="userRowId" value="">
    <?php echo csrf_token_field(); ?>
</form>

<!-- Modal Dialog Tambah/Edit -->
<div id="userModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:999; align-items:center; justify-content:center; padding: 20px;">
    <div style="background:#fff; width:100%; max-width:500px; border-radius:12px; overflow:hidden; max-height:92vh; overflow-y:auto;">
        <div style="padding:20px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; background:#f8fafc;">
            <h3 id="modalTitle" style="margin:0; font-size:18px;">Form Pengguna</h3>
            <button type="button" onclick="document.getElementById('userModal').style.display='none'" style="background:none; border:none; cursor:pointer; font-size:18px; color:#64748b;"><i class="fas fa-times"></i></button>
        </div>
        <form method="post" action="users.php" style="padding:20px;">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="formId" value="">
            <?php echo csrf_token_field(); ?>
            
            <div class="form-group">
                <label>Nama Lengkap <span style="color:red;">*</span></label>
                <input type="text" name="full_name" id="formFullName" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" id="formEmail" class="form-control" placeholder="opsional@bapenda.go.id">
            </div>
            <div class="form-group">
                <label>Username <span style="color:red;">*</span></label>
                <input type="text" name="username" id="formUsername" class="form-control" required autocomplete="off">
            </div>
            <div class="form-group">
                <label>Pilih Role <span style="color:red;">*</span></label>
                <select name="role_id" id="formRole" class="form-control" required>
                    <option value="">-- Pilih Role --</option>
                    <?php foreach ($roles_list as $rd):
                        $rd_id = (int)$rd['id'];
                        if ($current_role_id !== 1 && ($rd_id === 1 || !actor_can_manage_permissions((string)($rd['permissions'] ?? '')))) continue;
                    ?>
                        <option value="<?php echo $rd_id; ?>"><?php echo htmlspecialchars($rd['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <small id="roleLockNote" style="display:none; color:#ea580c; font-weight:600; margin-top:4px;"><i class="fas fa-lock"></i> <span id="roleLockNoteText">Role akun Super Admin utama tidak dapat diubah.</span></small>
            </div>
            <?php if ($has_is_active): ?>
            <div class="form-group" id="statusGroup">
                <label>Status Akun</label>
                <select name="is_active" id="formIsActive" class="form-control">
                    <option value="1">Aktif (Dapat Login)</option>
                    <option value="0">Nonaktif (Diblokir)</option>
                </select>
            </div>
            <?php endif; ?>
            <div class="form-group">
                <label>Password <small style="font-weight:normal;" id="passHelp">(Kosongkan jika tidak ingin mengubah)</small></label>
                <div style="position:relative;">
                    <input type="password" name="password" id="formPassword" class="form-control" minlength="8" autocomplete="new-password" style="padding-right:42px;">
                    <button type="button" onclick="toggleFormPassword()" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:#64748b;" title="Lihat/Sembunyikan Password">
                        <i class="fas fa-eye" id="formPasswordEye"></i>
                    </button>
                </div>
            </div>
            <div style="text-align:right; margin-top:20px;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('userModal').style.display='none'" style="margin-right:10px;">Batal</button>
                <button type="submit" class="btn"><i class="fas fa-save"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
const CURRENT_ADMIN_ID = <?php echo $current_admin_id; ?>;
const CURRENT_IS_SUPER_ADMIN = <?php echo ($current_role_id === 1) ? 'true' : 'false'; ?>;
const HAS_IS_ACTIVE = <?php echo $has_is_active ? 'true' : 'false'; ?>;

function resetPasswordVisibility() {
    const passInput = document.getElementById('formPassword');
    const eyeIcon = document.getElementById('formPasswordEye');
    passInput.type = 'password';
    passInput.value = '';
    if (eyeIcon) {
        eyeIcon.classList.remove('fa-eye-slash');
        eyeIcon.classList.add('fa-eye');
    }
}

function toggleFormPassword() {
    const passInput = document.getElementById('formPassword');
    const eyeIcon = document.getElementById('formPasswordEye');
    if (passInput.type === 'password') {
        passInput.type = 'text';
        eyeIcon.classList.remove('fa-eye');
        eyeIcon.classList.add('fa-eye-slash');
    } else {
        passInput.type = 'password';
        eyeIcon.classList.remove('fa-eye-slash');
        eyeIcon.classList.add('fa-eye');
    }
}

function openModal(type) {
    document.getElementById('modalTitle').innerText = 'Tambah Pengguna Baru';
    document.getElementById('formAction').value = 'add';
    document.getElementById('formId').value = '';
    document.getElementById('formFullName').value = '';
    document.getElementById('formEmail').value = '';
    document.getElementById('formUsername').value = '';

    const roleSelect = document.getElementById('formRole');
    roleSelect.value = '';
    roleSelect.disabled = false;
    document.getElementById('roleLockNote').style.display = 'none';

    if (HAS_IS_ACTIVE) {
        const activeSelect = document.getElementById('formIsActive');
        activeSelect.value = '1';
        activeSelect.disabled = false;
    }

    resetPasswordVisibility();
    document.getElementById('formPassword').required = true;
    document.getElementById('passHelp').innerText = '(Wajib diisi, minimal 8 karakter)';
    document.getElementById('userModal').style.display = 'flex';
}

function editUser(data) {
    const targetId = Number(data.id);
    const isPrimarySuperAdmin = (targetId === 1);
    const isSelf = (targetId === CURRENT_ADMIN_ID);
    const lockRole = isPrimarySuperAdmin || (isSelf && !CURRENT_IS_SUPER_ADMIN);

    document.getElementById('modalTitle').innerText = 'Edit Pengguna';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('formId').value = data.id;
    document.getElementById('formFullName').value = data.full_name || '';
    document.getElementById('formEmail').value = data.email || '';
    document.getElementById('formUsername').value = data.username || '';

    const roleSelect = document.getElementById('formRole');
    roleSelect.value = String(data.role_id || '');
    roleSelect.disabled = lockRole;
    document.getElementById('roleLockNoteText').textContent = isPrimarySuperAdmin
        ? 'Role akun Super Admin utama tidak dapat diubah.'
        : 'Anda tidak dapat mengubah role akun Anda sendiri.';
    document.getElementById('roleLockNote').style.display = lockRole ? 'block' : 'none';

    if (HAS_IS_ACTIVE) {
        const activeSelect = document.getElementById('formIsActive');
        activeSelect.value = String(data.is_active !== undefined ? data.is_active : 1);
        activeSelect.disabled = (isPrimarySuperAdmin || isSelf);
    }

    resetPasswordVisibility();
    document.getElementById('formPassword').required = false;
    document.getElementById('passHelp').innerText = '(Kosongkan jika tidak ingin mengubah, minimal 8 karakter)';
    document.getElementById('userModal').style.display = 'flex';
}

// Pastikan select yang disabled tetap mengirim nilainya saat submit jika perlu
document.querySelector('#userModal form').addEventListener('submit', function() {
    document.getElementById('formRole').disabled = false;
    if (HAS_IS_ACTIVE) {
        document.getElementById('formIsActive').disabled = false;
    }
});

function toggleUserActive(id, username, activate) {
    const label = activate ? 'mengaktifkan' : 'menonaktifkan';
    if (confirm('Yakin ingin ' + label + ' akun "' + username + '"?')) {
        document.getElementById('userRowAction').value = 'toggle_active';
        document.getElementById('userRowId').value = id;
        document.getElementById('userActionForm').submit();
    }
}

function confirmDelete(id, username) {
    if (confirm('Yakin ingin menghapus pengguna "' + username + '" secara permanen?')) {
        document.getElementById('userRowAction').value = 'delete';
        document.getElementById('userRowId').value = id;
        document.getElementById('userActionForm').submit();
    }
}
</script>

<?php require_once 'layout_footer.php'; ?>
