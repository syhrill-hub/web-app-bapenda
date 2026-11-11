<?php
require_once __DIR__ . '/../includes/security_helpers.php';
bapenda_start_secure_session();
require_once '../config.php';
require_once 'includes/security.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}
require_permission('menu_roles'); // Hak akses spesifik untuk halaman ini

$page_title = 'Manajemen Grup & Hak Akses (Roles)';
$current_role_id = (int)($_SESSION['admin_role_id'] ?? 0);
$is_super_admin = ($current_role_id === 1);
$actor_perms = get_current_effective_permissions();

// Hapus Role (CSRF POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = "Token keamanan tidak valid.";
        header("Location: roles.php");
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        $_SESSION['flash_error'] = "ID role tidak valid.";
    } elseif ($id === 1) {
        $_SESSION['flash_error'] = "Role Super Admin tidak boleh dihapus!";
    } elseif (!$is_super_admin && $id === $current_role_id) {
        $_SESSION['flash_error'] = "Anda tidak dapat menghapus role yang sedang Anda gunakan sendiri!";
    } else {
        $stmt_role = $conn->prepare("SELECT permissions FROM roles WHERE id = ? LIMIT 1");
        $stmt_role->bind_param("i", $id);
        $stmt_role->execute();
        $target_role = $stmt_role->get_result()->fetch_assoc();
        $stmt_role->close();

        if (!$target_role) {
            $_SESSION['flash_error'] = "Role tidak ditemukan.";
        } elseif (!$is_super_admin && !actor_can_manage_permissions((string)($target_role['permissions'] ?? ''))) {
            $_SESSION['flash_error'] = "Akses ditolak: Anda tidak dapat menghapus role dengan hak akses di luar kewenangan Anda.";
        } else {
            // Cek apakah ada user yg pakai role ini
            $stmt_check = $conn->prepare("SELECT COUNT(*) as c FROM users WHERE role_id = ?");
            $stmt_check->bind_param("i", $id);
            $stmt_check->execute();
            $count = (int)$stmt_check->get_result()->fetch_assoc()['c'];
            $stmt_check->close();

            if ($count > 0) {
                $_SESSION['flash_error'] = "Gagal. Ada $count pengguna menggunakan role ini. Ubah dulu role pengguna tersebut.";
            } else {
                $stmt = $conn->prepare("DELETE FROM roles WHERE id = ?");
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    $_SESSION['flash_success'] = "Jabatan/Role berhasil dihapus!";
                } else {
                    $_SESSION['flash_error'] = "Terjadi kesalahan saat menghapus role.";
                }
                $stmt->close();
            }
        }
    }
    header("Location: roles.php");
    exit;
}

// Proses Tambah/Edit Role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['add', 'edit'], true)) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = "Token keamanan tidak valid.";
        header("Location: roles.php");
        exit;
    }

    $action = $_POST['action'];
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');

    if ($action === 'edit' && $id === 1) {
        $_SESSION['flash_error'] = "Role Super Admin memiliki akses penuh permanen dan tidak dapat diubah.";
        header("Location: roles.php");
        exit;
    }

    if ($action === 'edit' && !$is_super_admin && $id === $current_role_id) {
        $_SESSION['flash_error'] = "Anda tidak dapat mengubah pengaturan role yang sedang Anda gunakan sendiri.";
        header("Location: roles.php");
        exit;
    }

    if ($action === 'edit' && $id > 0 && !$is_super_admin) {
        $stmt_existing = $conn->prepare("SELECT permissions FROM roles WHERE id = ? LIMIT 1");
        $stmt_existing->bind_param("i", $id);
        $stmt_existing->execute();
        $existing_role = $stmt_existing->get_result()->fetch_assoc();
        $stmt_existing->close();

        if (!$existing_role) {
            $_SESSION['flash_error'] = "Role tidak ditemukan.";
            header("Location: roles.php");
            exit;
        }
        if (!actor_can_manage_permissions((string)($existing_role['permissions'] ?? ''))) {
            $_SESSION['flash_error'] = "Akses ditolak: Role ini memiliki hak akses di luar kewenangan Anda.";
            header("Location: roles.php");
            exit;
        }
    }

    // Filter hanya permission keys yang valid agar tidak ada injeksi key sembarangan
    $valid_keys = array_keys(get_available_permissions());
    $raw_perms = isset($_POST['permissions']) && is_array($_POST['permissions']) ? $_POST['permissions'] : [];
    $perms_array = array_values(array_intersect(array_map('trim', $raw_perms), $valid_keys));

    if (!$is_super_admin && !empty(array_diff($perms_array, $actor_perms))) {
        $_SESSION['flash_error'] = "Akses ditolak: Anda tidak dapat memberikan hak akses yang tidak Anda miliki.";
        header("Location: roles.php");
        exit;
    }

    $permissions_str = implode(',', $perms_array);

    if ($name === '') {
        $_SESSION['flash_error'] = "Nama Jabatan/Role tidak boleh kosong.";
        header("Location: roles.php");
        exit;
    }

    if ($action === 'add') {
        $stmt_dup = $conn->prepare("SELECT id FROM roles WHERE name = ? LIMIT 1");
        $stmt_dup->bind_param("s", $name);
        $stmt_dup->execute();
        if ($stmt_dup->get_result()->num_rows > 0) {
            $stmt_dup->close();
            $_SESSION['flash_error'] = "Nama role '$name' sudah terdaftar!";
            header("Location: roles.php");
            exit;
        }
        $stmt_dup->close();

        $stmt = $conn->prepare("INSERT INTO roles (name, permissions) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $permissions_str);
        if ($stmt->execute()) {
            $_SESSION['flash_success'] = "Role baru berhasil ditambahkan.";
        } else {
            $_SESSION['flash_error'] = "Gagal menambah role.";
        }
        $stmt->close();
    } elseif ($action === 'edit' && $id > 0) {
        $stmt_dup = $conn->prepare("SELECT id FROM roles WHERE name = ? AND id != ? LIMIT 1");
        $stmt_dup->bind_param("si", $name, $id);
        $stmt_dup->execute();
        if ($stmt_dup->get_result()->num_rows > 0) {
            $stmt_dup->close();
            $_SESSION['flash_error'] = "Nama role '$name' sudah digunakan oleh role lain!";
            header("Location: roles.php");
            exit;
        }
        $stmt_dup->close();

        $stmt = $conn->prepare("UPDATE roles SET name=?, permissions=? WHERE id=?");
        $stmt->bind_param("ssi", $name, $permissions_str, $id);
        if ($stmt->execute()) {
            $_SESSION['flash_success'] = "Pengaturan role berhasil diperbarui.";
            if ($id === $current_role_id) {
                $_SESSION['admin_permissions'] = $permissions_str;
            }
        } else {
            $_SESSION['flash_error'] = "Gagal memperbarui role.";
        }
        $stmt->close();
    } else {
        $_SESSION['flash_error'] = "Data role tidak valid.";
    }
    header("Location: roles.php");
    exit;
}

require_once 'layout_header.php';

$roles = [];
$res = $conn->query("SELECT * FROM roles ORDER BY id ASC");
if ($res) {
    while($row = $res->fetch_assoc()) $roles[] = $row;
}

$available_permissions = get_available_permissions();
?>

<div class="content">
    <?php if (isset($_SESSION['flash_success'])): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
    <?php endif; ?>

    <div class="d-flex" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h2><i class="fas fa-id-badge" style="color:var(--primary);margin-right:10px;"></i> Master Role Hak Akses</h2>
        <button class="btn" onclick="openRoleModal('add')"><i class="fas fa-plus"></i> Tambah Role Tingkat Baru</button>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead style="background:#f8fafc;">
                        <tr>
                            <th>Nama Jabatan / Role</th>
                            <th>Hak Akses (Centang)</th>
                            <th style="min-width:140px; text-align:right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roles as $r):
                            $rid = (int)$r['id'];
                            $expanded_perms = expand_permissions((string)($r['permissions'] ?? ''));
                            $is_role_editable = ($rid !== 1) && ($is_super_admin || ($rid !== $current_role_id && actor_can_manage_permissions((string)($r['permissions'] ?? ''))));
                            $lock_reason = '';
                            if ($rid === 1) {
                                $lock_reason = 'Role ini adalah Super Admin. Hak aksesnya permanen (All-Access) dan tidak bisa dicabut atau dibatasi.';
                            } elseif ($rid === $current_role_id && !$is_super_admin) {
                                $lock_reason = 'Anda tidak dapat mengubah pengaturan role yang sedang Anda gunakan sendiri.';
                            } elseif (!$is_role_editable) {
                                $lock_reason = 'Role ini memiliki hak akses di atas kewenangan akun Anda sehingga terkunci.';
                            }
                            $role_payload = [
                                'id' => $rid,
                                'name' => $r['name'],
                                'permissions' => (string)($r['permissions'] ?? ''),
                                'expanded_permissions' => $expanded_perms,
                                'editable' => $is_role_editable,
                                'lock_reason' => $lock_reason,
                            ];
                            $role_json = htmlspecialchars(bapenda_json_encode($role_payload), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($r['name']); ?></strong></td>
                            <td>
                                <?php if ($rid === 1): ?>
                                    <span style="font-size:12px; color:#16a34a; font-weight:700;"><i class="fas fa-shield-alt"></i> Akses Penuh ke Semua Menu</span>
                                <?php elseif (empty($expanded_perms)): ?>
                                    <span style="color:#94a3b8; font-size:12px; font-style:italic;">Hanya melihat Dashboard</span>
                                <?php else: ?>
                                    <div style="display:flex; flex-wrap:wrap; gap:5px;">
                                        <?php foreach ($expanded_perms as $p):
                                            $desc = $available_permissions[$p] ?? $p;
                                        ?>
                                            <span style="background:#f1f5f9; color:#475569; padding:3px 8px; border-radius:4px; font-size:11px; border:1px solid #e2e8f0;"><i class="fas fa-check" style="color:#0ea5e9;"></i> <?php echo htmlspecialchars($desc); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right;">
                                <button type="button" class="btn-action edit" onclick="openRoleModal('edit', <?php echo $role_json; ?>)">
                                    <?php if ($is_role_editable): ?>
                                        <i class="fas fa-edit"></i> Edit
                                    <?php else: ?>
                                        <i class="fas fa-lock"></i> Detail
                                    <?php endif; ?>
                                </button>
                                <?php if ($is_role_editable): ?>
                                <button type="button" class="btn-action delete" onclick="confirmDelete(<?php echo $rid; ?>, <?php echo htmlspecialchars(bapenda_json_encode($r['name']), ENT_QUOTES, 'UTF-8'); ?>)"><i class="fas fa-trash"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Form Hapus Role (CSRF POST) -->
<form id="deleteRoleForm" method="post" action="roles.php" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteRoleId" value="">
    <?php echo csrf_token_field(); ?>
</form>

<!-- Modal Dialog Tambah/Edit -->
<div id="roleModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:999; align-items:center; justify-content:center; padding: 20px;">
    <div style="background:#fff; width:100%; max-width:800px; border-radius:12px; display:flex; flex-direction:column; max-height: 90vh;">
        <div style="padding:20px 25px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; background:#f8fafc;">
            <h3 id="modalTitle" style="margin:0; font-size:18px; color:var(--text-dark);">Pembuatan Hak Akses</h3>
            <button type="button" onclick="document.getElementById('roleModal').style.display='none'" style="background:none; border:none; cursor:pointer; font-size:18px; color:#64748b;"><i class="fas fa-times"></i></button>
        </div>
        <form id="roleForm" method="post" action="roles.php" style="padding:25px; overflow-y: auto; flex: 1;">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="formId" value="">
            <?php echo csrf_token_field(); ?>
            
            <div class="form-group" style="margin-bottom:25px;">
                <label style="display:block; margin-bottom:8px; font-weight:600; font-size:14px;">Nama Role / Jabatan <span style="color:red;">*</span></label>
                <input type="text" name="name" id="formName" class="form-control" placeholder="Contoh: Manajer IT, Staf KPPN..." required style="font-size:15px; padding:12px;">
            </div>
            
            <div class="form-group">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; border-bottom:1px solid #e2e8f0; padding-bottom:10px;">
                    <label style="margin:0; font-weight:600; font-size:14px;">Berikan Centang Pada Akses Menu Berikut:</label>
                    <div id="permBulkActions" style="display:flex; gap:8px;">
                        <button type="button" onclick="toggleAllPerms(true)" style="background:#e0f2fe; color:#0369a1; border:none; padding:4px 10px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer;">Pilih Semua</button>
                        <button type="button" onclick="toggleAllPerms(false)" style="background:#f1f5f9; color:#475569; border:none; padding:4px 10px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer;">Kosongkan</button>
                    </div>
                </div>
                <div id="permissionsCheckboxList" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap:12px; background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <?php foreach($available_permissions as $key => $label):
                        $can_grant_perm = ($is_super_admin || in_array($key, $actor_perms, true));
                    ?>
                    <label style="display:flex; align-items:flex-start; cursor:<?php echo $can_grant_perm ? 'pointer' : 'not-allowed'; ?>; font-size:14px; color:<?php echo $can_grant_perm ? '#334155' : '#94a3b8'; ?>; padding: 5px; border-radius: 4px; transition: background 0.2s;">
                        <input type="checkbox" name="permissions[]" value="<?php echo htmlspecialchars($key); ?>" data-grantable="<?php echo $can_grant_perm ? '1' : '0'; ?>" class="perm-checkbox" style="margin-top:2px; margin-right:10px; width:16px; height:16px; cursor:<?php echo $can_grant_perm ? 'pointer' : 'not-allowed'; ?>;">
                        <div>
                            <strong><?php echo htmlspecialchars($label); ?></strong>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div id="superAdminWarning" style="display:none; color:#ea580c; background:#ffedd5; padding:10px; border-radius:6px; margin-top:15px; font-size:13px; font-weight:600;"><i class="fas fa-lock"></i> <span id="superAdminWarningText"></span></div>
            </div>
            
            <div style="text-align:right; margin-top:30px; border-top:1px solid #e2e8f0; padding-top:20px;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('roleModal').style.display='none'" style="margin-right:10px; padding:12px 25px; border-radius:8px;">Tutup</button>
                <button type="submit" id="saveRoleBtn" class="btn" style="padding:12px 25px; border-radius:8px;"><i class="fas fa-save"></i> Terapkan Hak Akses</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleAllPerms(checked) {
    document.querySelectorAll('.perm-checkbox').forEach(cb => {
        if (!cb.disabled) {
            cb.checked = checked;
        }
    });
}

function openRoleModal(action, data = null) {
    document.getElementById('formAction').value = action;
    const checkboxes = document.querySelectorAll('.perm-checkbox');
    const warning = document.getElementById('superAdminWarning');
    const warningText = document.getElementById('superAdminWarningText');
    const bulkActions = document.getElementById('permBulkActions');
    const saveBtn = document.getElementById('saveRoleBtn');
    const nameInput = document.getElementById('formName');

    if (action === 'add') {
        document.getElementById('modalTitle').innerText = 'Buat Hak Akses Baru';
        document.getElementById('formId').value = '';
        nameInput.value = '';
        nameInput.readOnly = false;
        checkboxes.forEach(cb => {
            cb.checked = false;
            cb.disabled = (cb.dataset.grantable !== '1');
        });
        warning.style.display = 'none';
        bulkActions.style.display = 'flex';
        saveBtn.style.display = 'inline-block';
    } else if (action === 'edit' && data) {
        const isSuperAdmin = Number(data.id) === 1;
        const isEditable = Boolean(data.editable) && !isSuperAdmin;
        document.getElementById('modalTitle').innerText = isEditable ? 'Edit Wewenang Akses' : 'Detail Role (Terkunci)';
        document.getElementById('formId').value = data.id;
        nameInput.value = data.name || '';
        nameInput.readOnly = !isEditable;

        const currentPerms = Array.isArray(data.expanded_permissions)
            ? data.expanded_permissions
            : String(data.permissions || '').split(',').map(p => p.trim()).filter(Boolean);

        checkboxes.forEach(cb => {
            if (isSuperAdmin) {
                cb.checked = true;
                cb.disabled = true;
            } else {
                cb.checked = currentPerms.includes(cb.value);
                cb.disabled = !isEditable || (cb.dataset.grantable !== '1');
            }
        });

        warningText.textContent = data.lock_reason || '';
        warning.style.display = !isEditable ? 'block' : 'none';
        bulkActions.style.display = isEditable ? 'flex' : 'none';
        saveBtn.style.display = isEditable ? 'inline-block' : 'none';
    }

    document.getElementById('roleModal').style.display = 'flex';
}

function confirmDelete(id, name) {
    if (confirm('Yakin ingin menghapus role "' + name + '" secara permanen?')) {
        document.getElementById('deleteRoleId').value = id;
        document.getElementById('deleteRoleForm').submit();
    }
}
</script>

<?php require_once 'layout_footer.php'; ?>
