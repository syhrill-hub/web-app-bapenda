<?php
session_start();
require_once '../config.php';
require_once 'includes/security.php';

if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$success = '';
$username = '';

if (isset($_GET['logged_out']) && $_GET['logged_out'] === '1') {
    $success = "Anda telah berhasil keluar dari panel admin.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Sesi formulir tidak valid atau kedaluwarsa. Silakan coba lagi.";
    } elseif (empty($username) || empty($password)) {
        $error = "Username dan password wajib diisi!";
    } else {
        $stmt = $conn->prepare("
            SELECT u.*, r.permissions
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.username = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $is_active = isset($row['is_active']) ? (int)$row['is_active'] : 1;

            if ($is_active === 0) {
                $error = "Akun Anda sedang dinonaktifkan. Hubungi Super Admin.";
            } elseif (password_verify($password, $row['password_hash'])) {
                if (array_key_exists('failed_attempts', $row) && (int)$row['failed_attempts'] > 0) {
                    $reset_stmt = $conn->prepare("UPDATE users SET failed_attempts = 0, last_failed_at = NULL WHERE id = ?");
                    if ($reset_stmt) {
                        $reset_stmt->bind_param("i", $row['id']);
                        $reset_stmt->execute();
                        $reset_stmt->close();
                    }
                }

                // Regenerasi session ID untuk mencegah session fixation
                session_regenerate_id(true);

                $_SESSION['admin_id']          = $row['id'];
                $_SESSION['username']          = $row['username'];
                $_SESSION['full_name']         = !empty($row['full_name']) ? $row['full_name'] : $row['username'];
                $_SESSION['admin_role_id']     = $row['role_id'];
                $_SESSION['admin_permissions'] = $row['permissions'] ?? '';
                $_SESSION['login_time']        = time();

                header("Location: dashboard.php");
                exit;
            } else {
                $error = "Username atau password salah! Silakan periksa kembali.";
            }
        } else {
            $error = "Username atau password salah! Silakan periksa kembali.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - Bapenda</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .login-card {
            background: #fff;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.15);
            width: 100%;
            max-width: 400px;
        }
        .login-card h2 {
            text-align: center;
            color: #0ea5e9;
            margin-bottom: 30px;
        }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #334155;
            font-weight: 600;
            font-size: 14px;
        }
        .input-wrapper {
            position: relative;
        }
        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            transition: 0.3s;
        }
        .form-control.has-toggle {
            padding-right: 46px;
        }
        .form-control:focus {
            outline: none;
            border-color: #0ea5e9;
            box-shadow: 0 0 0 3px rgba(14,165,233,0.1);
        }
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #64748b;
            cursor: pointer;
            font-size: 16px;
            padding: 4px;
        }
        .toggle-password:hover { color: #0ea5e9; }
        .btn {
            width: 100%;
            padding: 14px;
            background: #0ea5e9;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: 0.3s;
        }
        .btn:hover { background: #0284c7; }
        .error-msg {
            background: #fef2f2;
            color: #dc2626;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            text-align: center;
            border: 1px solid #fecaca;
        }
        .success-msg {
            background: #f0fdf4;
            color: #16a34a;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            text-align: center;
            border: 1px solid #bbf7d0;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #64748b;
            text-decoration: none;
            font-size: 13px;
        }
        .back-link:hover { color: #0ea5e9; }
    </style>
</head>
<body>
    <div class="login-card">
        <h2><i class="fas fa-user-shield"></i> Admin Panel</h2>

        <?php if($error): ?>
        <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if($success): ?>
        <div class="success-msg"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <?php echo csrf_token_field(); ?>
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" class="form-control" placeholder="Ketik username" value="<?php echo htmlspecialchars($username); ?>" required autofocus>
            </div>

            <div class="form-group">
                <label>Password</label>
                <div class="input-wrapper">
                    <input type="password" name="password" id="passwordInput" class="form-control has-toggle" placeholder="Ketik password" required>
                    <button type="button" class="toggle-password" id="togglePassword" aria-label="Tampilkan atau sembunyikan password">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn">Masuk</button>
        </form>

        <a href="../index.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Kembali ke Website
        </a>
    </div>

    <script>
    (function() {
        var btn = document.getElementById('togglePassword');
        var input = document.getElementById('passwordInput');
        if (btn && input) {
            btn.addEventListener('click', function() {
                var isPwd = input.getAttribute('type') === 'password';
                input.setAttribute('type', isPwd ? 'text' : 'password');
                var icon = btn.querySelector('i');
                if (icon) {
                    icon.classList.toggle('fa-eye', !isPwd);
                    icon.classList.toggle('fa-eye-slash', isPwd);
                }
            });
        }
    })();
    </script>
</body>
</html>
