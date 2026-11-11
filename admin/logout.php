<?php
require_once __DIR__ . '/../includes/security_helpers.php';
bapenda_start_secure_session();
require_once 'includes/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    if (!empty($_SESSION['admin_id'])) {
        $_SESSION['flash_error'] = "Permintaan keluar harus melalui tombol Keluar Panel yang valid.";
        header("Location: dashboard.php");
        exit;
    }
    header("Location: login.php");
    exit;
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();
header("Location: login.php?logged_out=1");
exit;
?>
