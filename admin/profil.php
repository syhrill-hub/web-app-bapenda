<?php
require_once __DIR__ . '/../includes/security_helpers.php';
bapenda_start_secure_session();
require_once '../config.php';
require_once 'includes/security.php';

// Cek autentikasi & sinkronisasi status/role user
require_admin_login($conn);


$message = '';
$message_type = '';
$admin_id = $_SESSION['admin_id'];

// Get current username
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$res = $stmt->get_result();
$user_data = $res->fetch_assoc();
$current_username = $user_data['username'] ?? '';
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = "Token keamanan tidak valid.";
        $message_type = "danger";
    } else {
        $new_username = trim($_POST['username'] ?? '');
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($new_username) || empty($current_password)) {
            $message = "Username dan Password Saat Ini wajib diisi.";
            $message_type = "danger";
        } else {
            // Verify current password
            $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->bind_param("i", $admin_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                if (password_verify($current_password, $row['password_hash'])) {
                    
                    // Check if changing password
                    $update_pass = false;
                    $hash = $row['password_hash'];
                    if (!empty($new_password)) {
                        if ($new_password !== $confirm_password) {
                            $message = "Password baru dan konfirmasi password tidak cocok.";
                            $message_type = "danger";
                        } elseif (strlen($new_password) < 8) {
                            $message = "Password baru minimal 8 karakter.";
                            $message_type = "danger";
                        } else {
                            $hash = password_hash($new_password, PASSWORD_DEFAULT);
                            $update_pass = true;
                        }
                    }

                    if (empty($message)) {
                        // Check if username already taken by another admin
                        $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                        $check_stmt->bind_param("si", $new_username, $admin_id);
                        $check_stmt->execute();
                        if ($check_stmt->get_result()->num_rows > 0) {
                            $message = "Username sudah digunakan oleh akun lain.";
                            $message_type = "danger";
                        } else {
                            // Update
                            $upd_stmt = $conn->prepare("UPDATE users SET username = ?, password_hash = ? WHERE id = ?");
                            $upd_stmt->bind_param("ssi", $new_username, $hash, $admin_id);
                            if ($upd_stmt->execute()) {
                                if ($update_pass) {
                                    session_regenerate_id(true);
                                    rotate_csrf_token();
                                }
                                $message = "Profil berhasil diperbarui!" . ($update_pass ? " (Password telah diubah)" : "");
                                $message_type = "success";
                                $_SESSION['username'] = $new_username;
                                $current_username = $new_username;
                            } else {
                                $message = "Terjadi kesalahan saat menyimpan data.";
                                $message_type = "danger";
                            }
                            $upd_stmt->close();
                        }
                        $check_stmt->close();
                    }
                } else {
                    $message = "Password saat ini salah.";
                    $message_type = "danger";
                }
            }
            $stmt->close();
        }
    }
}

$page_title = 'Profil Admin';
require_once 'layout_header.php';
?>

<style>
.profile-container {
    max-width: 650px;
    margin: 0 auto;
}
.profile-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03);
    padding: 30px;
}
.form-group {
    margin-bottom: 20px;
}
.form-label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-muted);
    margin-bottom: 8px;
}
.form-control {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 15px;
    color: var(--text-dark);
    box-sizing: border-box;
    transition: all 0.2s;
}
.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15);
}
.form-text {
    font-size: 12px;
    color: #94a3b8;
    margin-top: 5px;
    display: block;
}
.alert {
    padding: 15px;
    margin-bottom: 20px;
    border: 1px solid transparent;
    border-radius: 12px;
}
.alert-success {
    color: #0f5132;
    background-color: #d1e7dd;
    border-color: #badbcc;
}
.alert-danger {
    color: #842029;
    background-color: #f8d7da;
    border-color: #f5c2c7;
}
.btn-submit {
    background: var(--primary);
    color: #fff;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s;
}
.btn-submit:hover {
    filter: brightness(0.9);
}
.profile-divider {
    border-bottom: 2px solid #f1f5f9;
    padding-bottom: 15px;
    margin-bottom: 25px;
    margin-top: 30px;
    color: #1e293b;
    font-weight: 700;
}
.password-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}
.password-wrapper .form-control {
    padding-right: 45px;
}
.password-toggle {
    position: absolute;
    right: 15px;
    background: none;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    font-size: 16px;
    padding: 0;
    transition: 0.2s;
    outline: none;
}
.password-toggle:hover {
    color: var(--primary);
}
</style>

<div class="content">
    <div class="dashboard-header mb-4" style="margin-bottom:20px;">
        <h2><i class="fas fa-user-shield" style="color:var(--primary);margin-right:10px;"></i> Pengaturan Profil</h2>
        <p class="text-muted" style="color:#64748b;">Ganti username dan password akses login Admin Anda.</p>
    </div>

    <div class="profile-container">
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="profile-card">
            <form action="profil.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <h4 class="profile-divider" style="margin-top:0;">Informasi Akun</h4>
                <div class="form-group">
                    <label class="form-label">Username Saat Ini</label>
                    <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($current_username); ?>" required>
                    <small class="form-text">Username ini akan digunakan untuk login selanjutnya.</small>
                </div>

                <h4 class="profile-divider">Ubah Password</h4>
                <div class="form-group" style="padding:15px; background:#f8fafc; border-radius:10px; border:1px solid #e2e8f0;">
                    <label class="form-label" style="color:#1e293b;">Password Saat Ini <span style="color:#ef4444;">*</span></label>
                    <div class="password-wrapper">
                        <input type="password" id="current_password" name="current_password" class="form-control" placeholder="Masukan password saat ini..." required style="background:#fff;">
                        <button type="button" class="password-toggle" onclick="togglePassword('current_password', this)"><i class="fas fa-eye"></i></button>
                    </div>
                    <small class="form-text">Wajib diisi sebagai konfirmasi keamanan sebelum melakukan perubahan apapun.</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Password Baru</label>
                    <div class="password-wrapper">
                        <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Kosongkan jika tidak ingin mengubah password">
                        <button type="button" class="password-toggle" onclick="togglePassword('new_password', this)"><i class="fas fa-eye"></i></button>
                    </div>
                    <small class="form-text">Bila diisi, minimal harus 8 karakter.</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Konfirmasi Password Baru</label>
                    <div class="password-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Ulangi password baru Anda">
                        <button type="button" class="password-toggle" onclick="togglePassword('confirm_password', this)"><i class="fas fa-eye"></i></button>
                    </div>
                </div>

                <div style="text-align: right; margin-top: 30px;">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save" style="margin-right:8px;"></i> Simpan Profil
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function togglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>

<?php require_once 'layout_footer.php'; ?>
