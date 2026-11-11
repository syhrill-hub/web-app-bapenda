<?php
session_start();
require_once '../config.php';

if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$max_attempts = 5;
$lockout_time = 900;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = "Username dan password wajib diisi!";
    } else {
            $stmt = $conn->prepare("SELECT u.id, u.password_hash, u.role_id, r.permissions, u.failed_attempts, u.last_failed_at FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $last_failed = $row['last_failed_at'];
                
                if ($row['failed_attempts'] >= $max_attempts && $last_failed && (time() - strtotime($last_failed)) < $lockout_time) {
                    $remaining = $lockout_time - (time() - strtotime($last_failed));
                    $error = "Akun terkunci sementara karena terlalu banyak percobaan gagal. Coba lagi dalam " . ceil($remaining / 60) . " menit.";
                } else {
                    if (password_verify($password, $row['password_hash'])) {
                        $reset_stmt = $conn->prepare("UPDATE users SET failed_attempts = 0, last_failed_at = NULL WHERE id = ?");
                        $reset_stmt->bind_param("i", $row['id']);
                        $reset_stmt->execute();
                        
                        $_SESSION['admin_id'] = $row['id'];
                        $_SESSION['username'] = $username;
                        $_SESSION['admin_role_id'] = $row['role_id']; 
                        $_SESSION['admin_permissions'] = $row['permissions'] ?? ''; 
                        $_SESSION['login_time'] = time();
                        
                        header("Location: dashboard.php");
                        exit;
                } else {
                    $failed = (int)$row['failed_attempts'] + 1;
                    $update_stmt = $conn->prepare("UPDATE users SET failed_attempts = ?, last_failed_at = NOW() WHERE id = ?");
                    $update_stmt->bind_param("ii", $failed, $row['id']);
                    $update_stmt->execute();
                    
                    if ($failed >= $max_attempts) {
                        $error = "Terlalu banyak percobaan gagal. Akun terkunci 15 menit.";
                    } else {
                        $remaining = $max_attempts - $failed;
                        $error = "Password salah! Sisa percobaan: $remaining";
                    }
                }
            }
        } else {
            $error = "Username tidak ditemukan!";
        }
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
            background: #0ea5e9; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
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
        .form-control { 
            width: 100%; 
            padding: 14px 16px; 
            border: 2px solid #e2e8f0; 
            border-radius: 10px; 
            font-size: 14px; 
            transition: 0.3s;
        }
        .form-control:focus { 
            outline: none; 
            border-color: #0ea5e9; 
            box-shadow: 0 0 0 3px rgba(14,165,233,0.1); 
        }
        .btn { 
            width: 100%; 
            padding: 14px; 
            background: #0ea5e9; 
            color: #fff; 
            border: none; 
            border-radius: 10px; 
            font-size: 16px; 
            font-weight: 600; 
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
        
        <form method="post" autocomplete="off">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" class="form-control" placeholder="Ketik username" required autofocus>
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-control" placeholder="Ketik password" required>
            </div>
            
            <button type="submit" class="btn">Masuk</button>
        </form>
        
        <a href="../index.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Kembali ke Website
        </a>
    </div>
</body>
</html>
