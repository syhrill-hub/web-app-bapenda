<?php
session_start();
require_once '../config.php';
require_once 'includes/security.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}
require_permission('menu_roles'); // Hak akses spesifik untuk halaman ini

$page_title = 'Manajemen Grup & Hak Akses (Roles)';

// Proses Hapus Role
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Jangan hapus Super Admin (id=1)
    if ($id == 1) {
        $_SESSION['flash_error'] = "Role Super Admin tidak boleh dihapus!";
    } else {
        // Cek apakah ada user yg pakai role ini
        $stmt_check = $conn->prepare("SELECT COUNT(*) as c FROM users WHERE role_id = ?");
        $stmt_check->bind_param("i", $id);
        $stmt_check->execute();
        $count = $stmt_check->get_result()->fetch_assoc()['c'];
        
        if ($count > 0) {
            $_SESSION['flash_error'] = "Gagal. Ada $count pengguna aktif menggunakan role ini. Ubah dulu role pengguna tersebut.";
        } else {
            $stmt = $conn->prepare("DELETE FROM roles WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = "Jabatan/Role berhasil dihapus!";
            } else {
                $_SESSION['flash_error'] = "Terjadi kesalahan saat menghapus.";
            }
        }
    }
    header("Location: roles.php");
    exit;
}

// Proses Tambah/Edit Role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = "Token keamanan tidak valid.";
        header("Location: roles.php");
        exit;
    }

    $id = $_POST['id'] ?? '';
    $name = trim($_POST['name'] ?? '');
    
    // permissions is an array of checkboxes
    $perms_array = isset($_POST['permissions']) && is_array($_POST['permissions']) ? $_POST['permissions'] : [];
    $permissions_str = implode(',', $perms_array);

    if (empty($name)) {
        $_SESSION['flash_error'] = "Nama Jabatan/Role tidak boleh kosong.";
        header("Location: roles.php");
        exit;
    }

    if ($_POST['action'] === 'add') {
        $stmt = $conn->prepare("INSERT INTO roles (name, permissions) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $permissions_str);
        if ($stmt->execute()) {
            $_SESSION['flash_success'] = "Role baru berhasil ditambahkan.";
        } else {
            $_SESSION['flash_error'] = "Gagal menambah role.";
        }
    } elseif ($_POST['action'] === 'edit' && !empty($id)) {
        // Update
        $stmt = $conn->prepare("UPDATE roles SET name=?, permissions=? WHERE id=?");
        $stmt->bind_param("ssi", $name, $permissions_str, $id);
        if ($stmt->execute()) {
            $_SESSION['flash_success'] = "Pengaturan role berhasil diperbarui.";
            // Update session if editing own role
            if ($id == $_SESSION['admin_role_id']) {
                $_SESSION['admin_permissions'] = $permissions_str;
            }
        } else {
            $_SESSION['flash_error'] = "Gagal memperbarui role.";
        }
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

$available_permissions = [
    'menu_users' => 'Manajemen Pengguna',
    'menu_roles' => 'Grup Hak Akses (Roles)',
    'menu_settings_general' => 'Pengaturan Umum',
    'menu_settings_theme' => 'Kustomisasi Tema',
    'menu_settings_header' => 'Header & Navigasi',
    'menu_settings_links' => 'Link Terkait',
    'menu_content_bupati' => 'Foto Pimpinan',
    'menu_content_struktur' => 'Struktur Organisasi',
    'menu_content_pegawai' => 'Data Pegawai',
    'menu_content_pages' => 'Halaman Statis',
    'menu_content_gallery' => 'Manajemen Galeri',
    'menu_content_documents' => 'Dokumen PPID',
    'menu_content_agenda' => 'Agenda Kegiatan',
    'menu_content_bantuan' => 'Pusat Bantuan',
    'menu_content_services' => 'Layanan Utama',
    'menu_content_news' => 'Berita Terbaru',
    'menu_content_popup' => 'Popup Info',
    'menu_report_pengaduan' => 'Laporan Pengaduan',
    'menu_tax_target' => 'Target Pajak',
    'menu_tax_realisasi' => 'Realisasi Pajak'
];
?>

<div class="content">
    <?php if (isset($_SESSION['flash_success'])): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?></div>
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
                        <?php foreach($roles as $r): 
                            $raw_perms = explode(',', $r['permissions']);
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($r['name']); ?></strong></td>
                            <td>
                                <?php if($r['id'] == 1): ?>
                                    <span style="font-size:12px; color:#16a34a; font-weight:700;"><i class="fas fa-shield-alt"></i> Akses Penuh ke Semua Menu</span>
                                <?php else: ?>
                                    <div style="display:flex; flex-wrap:wrap; gap:5px;">
                                        <?php 
                                        foreach($raw_perms as $p) {
                                            $p = trim($p);
                                            if(empty($p)) continue;
                                            $desc = $available_permissions[$p] ?? $p;
                                            echo '<span style="background:#f1f5f9; color:#475569; padding:3px 8px; border-radius:4px; font-size:11px border:1px solid #e2e8f0;"><i class="fas fa-check" style="color:#0ea5e9;"></i> '.htmlspecialchars($desc).'</span> ';
                                        }
                                        if(empty($r['permissions'])) echo '<span style="color:#94a3b8; font-size:12px; font-style:italic;">Hanya melihat Dashboard</span>';
                                        ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right;">
                                <button class="btn-action edit" onclick='openRoleModal("edit", <?php echo json_encode($r); ?>)'><i class="fas fa-edit"></i> Edit</button>
                                <?php if($r['id'] != 1): ?>
                                <button class="btn-action delete" onclick="confirmDelete(<?php echo $r['id']; ?>)"><i class="fas fa-trash"></i></button>
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

<!-- Modal Dialog Tambah/Edit -->
<div id="roleModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:999; align-items:center; justify-content:center; padding: 20px;">
    <div style="background:#fff; width:100%; max-width:800px; border-radius:12px; display:flex; flex-direction:column; max-height: 90vh;">
        <div style="padding:20px 25px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; background:#f8fafc;">
            <h3 id="modalTitle" style="margin:0; font-size:18px; color:var(--text-dark);">Pembuatan Hak Akses</h3>
            <button onclick="document.getElementById('roleModal').style.display='none'" style="background:none; border:none; cursor:pointer; font-size:18px; color:#64748b;"><i class="fas fa-times"></i></button>
        </div>
        <form method="post" action="roles.php" style="padding:25px; overflow-y: auto; flex: 1;">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="formId" value="">
            <?php echo csrf_token_field(); ?>
            
            <div class="form-group" style="margin-bottom:25px;">
                <label style="display:block; margin-bottom:8px; font-weight:600; font-size:14px;">Nama Role / Jabatan <span style="color:red;">*</span></label>
                <input type="text" name="name" id="formName" class="form-control" placeholder="Contoh: Manajer IT, Staf KPPN..." required style="font-size:15px; padding:12px;">
            </div>
            
            <div class="form-group">
                <label style="display:block; margin-bottom:12px; font-weight:600; font-size:14px; border-bottom:1px solid #e2e8f0; padding-bottom:10px;">Berikan Centang Pada Akses Menu Berikut:</label>
                <div id="permissionsCheckboxList" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap:12px; background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <?php foreach($available_permissions as $key => $label): ?>
                    <label style="display:flex; align-items:flex-start; cursor:pointer; font-size:14px; color:#334155; padding: 5px; border-radius: 4px; transition: background 0.2s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='transparent'">
                        <input type="checkbox" name="permissions[]" value="<?php echo htmlspecialchars($key); ?>" class="perm-checkbox" style="margin-top:2px; margin-right:10px; width:16px; height:16px; cursor:pointer;">
                        <div>
                            <strong><?php echo htmlspecialchars($label); ?></strong>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div id="superAdminWarning" style="display:none; color:#ea580c; background:#ffedd5; padding:10px; border-radius:6px; margin-top:15px; font-size:13px; font-weight:600;"><i class="fas fa-lock"></i> Role ini adalah Super Admin. Hak aksesnya tidak bisa dicabut/dibatasi (Akan selalu All-Access).</div>
            </div>
            
            <div style="text-align:right; margin-top:30px; border-top:1px solid #e2e8f0; padding-top:20px;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('roleModal').style.display='none'" style="margin-right:10px; padding:12px 25px; border-radius:8px;">Batal</button>
                <button type="submit" class="btn" style="padding:12px 25px; border-radius:8px;"><i class="fas fa-save"></i> Terapkan Hak Akses</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRoleModal(action, data = null) {
    document.getElementById('formAction').value = action;
    const checkboxes = document.querySelectorAll('.perm-checkbox');
    const warning = document.getElementById('superAdminWarning');
    
    if (action === 'add') {
        document.getElementById('modalTitle').innerText = 'Buat Hak Akses Baru';
        document.getElementById('formId').value = '';
        document.getElementById('formName').value = '';
        checkboxes.forEach(cb => { cb.checked = false; cb.disabled = false; });
        warning.style.display = 'none';
        document.getElementById('formName').readOnly = false;
    } else if (action === 'edit') {
        document.getElementById('modalTitle').innerText = 'Edit Wewenang Akses';
        document.getElementById('formId').value = data.id;
        document.getElementById('formName').value = data.name;
        
        const currentPerms = data.permissions.split(',').map(p => p.trim());
        
        checkboxes.forEach(cb => {
            cb.checked = currentPerms.includes(cb.value);
            // Kunci Super Admin (ID 1)
            if (data.id == 1) {
                cb.checked = true;
                cb.disabled = true; // Supaya centangnya tidak bisa diilangin
            } else {
                cb.disabled = false;
            }
        });
        
        if (data.id == 1) {
            warning.style.display = 'block';
            document.getElementById('formName').readOnly = true;
        } else {
            warning.style.display = 'none';
            document.getElementById('formName').readOnly = false;
        }
    }
    
    document.getElementById('roleModal').style.display = 'flex';
}

function confirmDelete(id) {
    if(confirm('Yakin ingin menghapus role jabatan ini secara permanen? Akun yang menggunakan role ini akan ikut terhapus atau kehilangan akses jika tidak diubah!')) {
        window.location.href = 'roles.php?delete=' + id;
    }
}
// Before submit, re-enable checkboxes so they send value for super admin
document.querySelector('form').addEventListener('submit', function() {
    document.querySelectorAll('.perm-checkbox').forEach(cb => cb.disabled = false);
});
</script>

<?php require_once 'layout_footer.php'; ?>
