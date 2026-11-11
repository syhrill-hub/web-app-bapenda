<?php
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_token_field() {
    return '<input type="hidden" name="csrf_token" value="' . generate_csrf_token() . '">';
}

function safe_settings_update($conn, $key, $value) {
    $stmt = $conn->prepare("SELECT key_name FROM settings WHERE key_name = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE key_name = ?");
        $stmt->bind_param("ss", $value, $key);
    } else {
        $stmt = $conn->prepare("INSERT INTO settings (key_name, value) VALUES (?, ?)");
        $stmt->bind_param("ss", $key, $value);
    }
    return $stmt->execute();
}

function has_permission($required_perm) {
    if (!isset($_SESSION['admin_permissions']) || !isset($_SESSION['admin_role_id'])) return false;
    
    // Super Admin (ID 1) bypass
    if ($_SESSION['admin_role_id'] == 1) return true;
    
    $perms = array_map('trim', explode(',', $_SESSION['admin_permissions']));
    
    if (is_array($required_perm)) {
        foreach($required_perm as $p) {
            if (in_array($p, $perms)) return true;
        }
        return false;
    }
    
    return in_array($required_perm, $perms);
}

function require_permission($required_perm) {
    if (!has_permission($required_perm)) {
        $_SESSION['flash_error'] = "Akses ditolak: Anda tidak memiliki izin untuk halaman tersebut.";
        header("Location: dashboard.php");
        exit;
    }
}
?>
