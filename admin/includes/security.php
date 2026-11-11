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

/**
 * Canonical map of granular menu permission keys to Indonesian display labels.
 */
function get_available_permissions() {
    return [
        'menu_users'             => 'Manajemen Pengguna',
        'menu_roles'             => 'Grup Hak Akses (Role)',
        'menu_settings_general'  => 'Pengaturan Umum',
        'menu_settings_theme'    => 'Tema & Warna',
        'menu_settings_header'   => 'Header & Hero Banner',
        'menu_settings_links'    => 'Tautan Terkait',
        'menu_content_bupati'    => 'Profil Bupati & Wakil',
        'menu_content_struktur'  => 'Struktur Organisasi',
        'menu_content_pegawai'   => 'Data Pegawai',
        'menu_content_pages'     => 'Halaman Profil & Visi',
        'menu_content_gallery'   => 'Manajemen Galeri',
        'menu_content_documents' => 'Dokumen Publik',
        'menu_content_agenda'    => 'Agenda Kegiatan',
        'menu_content_bantuan'   => 'Buku Panduan & Bantuan',
        'menu_content_services'  => 'Layanan Publik',
        'menu_content_news'      => 'Berita Terbaru',
        'menu_content_popup'     => 'Popup Informasi',
        'menu_report_pengaduan'  => 'Laporan Pengaduan',
        'menu_tax_target'        => 'Target Pajak',
        'menu_tax_realisasi'     => 'Realisasi Pajak',
    ];
}

/**
 * Legacy composite permission keys mapped to their granular menu_* permissions.
 * Keeps older DB role definitions working without manual SQL migration.
 */
function get_legacy_permission_aliases() {
    return [
        'manage_users'    => ['menu_users', 'menu_roles'],
        'manage_settings' => [
            'menu_settings_general',
            'menu_settings_theme',
            'menu_settings_header',
            'menu_settings_links',
        ],
        'manage_content'  => [
            'menu_content_bupati',
            'menu_content_struktur',
            'menu_content_pegawai',
            'menu_content_pages',
            'menu_content_gallery',
            'menu_content_documents',
            'menu_content_agenda',
            'menu_content_bantuan',
            'menu_content_services',
            'menu_content_news',
            'menu_content_popup',
        ],
        'manage_report'   => ['menu_report_pengaduan'],
        'manage_tax'      => ['menu_tax_target', 'menu_tax_realisasi'],
    ];
}

/**
 * Expands a comma-separated permission string ('all' or legacy keys) into granular menu_* keys.
 */
function expand_permissions($perm_string) {
    if (!is_string($perm_string) || trim($perm_string) === '') {
        return [];
    }

    $available = array_keys(get_available_permissions());
    $aliases = get_legacy_permission_aliases();
    $raw_perms = array_filter(array_map('trim', explode(',', $perm_string)), function ($p) {
        return $p !== '';
    });

    if (in_array('all', $raw_perms, true)) {
        return $available;
    }

    $expanded = [];
    foreach ($raw_perms as $perm) {
        if (isset($aliases[$perm])) {
            foreach ($aliases[$perm] as $mapped) {
                $expanded[$mapped] = true;
            }
        } else {
            $expanded[$perm] = true;
        }
    }

    return array_keys($expanded);
}

function users_has_is_active_column($conn) {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    if (!($conn instanceof mysqli) || $conn->connect_errno) {
        return false;
    }
    $res = @$conn->query("SHOW COLUMNS FROM users LIKE 'is_active'");
    $cached = ($res && $res->num_rows > 0);
    return $cached;
}

function force_admin_logout($reason = '') {
    if (session_status() === PHP_SESSION_ACTIVE) {
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
    }

    $target = ($reason === 'inactive') ? 'login.php?inactive=1' : 'login.php';
    if (!headers_sent()) {
        header("Location: " . $target);
    } else {
        echo "<script>window.location.href=" . json_encode($target) . ";</script>";
    }
    exit;
}

/**
 * Synchronizes current admin session with the database once per request so role edits,
 * permission changes, or account deactivations/deletions take effect immediately.
 */
function sync_current_user_session($conn = null, $force = false) {
    static $synced = false;

    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['admin_id'])) {
        return false;
    }

    if ($synced && !$force) {
        return true;
    }

    if ($conn === null) {
        $conn = $GLOBALS['conn'] ?? null;
    }
    if (!($conn instanceof mysqli) || $conn->connect_errno) {
        return true;
    }

    $admin_id = (int)$_SESSION['admin_id'];
    $has_is_active = users_has_is_active_column($conn);
    $active_select = $has_is_active ? ", u.is_active" : "";

    $stmt = $conn->prepare("SELECT u.id, u.username, u.full_name, u.role_id{$active_select}, r.permissions FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ? LIMIT 1");
    if (!$stmt) {
        return true;
    }

    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        force_admin_logout('deleted');
    }

    if ($has_is_active && isset($row['is_active']) && (int)$row['is_active'] !== 1) {
        force_admin_logout('inactive');
    }

    $role_id = (int)($row['role_id'] ?? 0);
    $_SESSION['username'] = $row['username'];
    $_SESSION['full_name'] = !empty($row['full_name']) ? $row['full_name'] : $row['username'];
    $_SESSION['admin_role_id'] = $role_id;
    $_SESSION['admin_permissions'] = ($role_id === 1) ? 'all' : (string)($row['permissions'] ?? '');

    $synced = true;
    return true;
}

/**
 * Ensures the visitor has an active authenticated admin session synchronized with DB.
 */
function require_admin_login($conn = null) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['admin_id'])) {
        header("Location: login.php");
        exit;
    }
    sync_current_user_session($conn);
}

function has_permission($required_perm) {
    if (empty($_SESSION['admin_id']) || !isset($_SESSION['admin_role_id'])) {
        return false;
    }

    sync_current_user_session($GLOBALS['conn'] ?? null);

    if (empty($_SESSION['admin_id']) || !isset($_SESSION['admin_role_id'])) {
        return false;
    }

    // Super Admin (Role ID 1) bypass
    if ((int)$_SESSION['admin_role_id'] === 1) {
        return true;
    }

    $perm_str = (string)($_SESSION['admin_permissions'] ?? '');
    $raw_perms = array_filter(array_map('trim', explode(',', $perm_str)), function ($p) {
        return $p !== '';
    });

    if (in_array('all', $raw_perms, true)) {
        return true;
    }

    $expanded = expand_permissions($perm_str);
    $effective = array_unique(array_merge($raw_perms, $expanded));

    if (is_array($required_perm)) {
        foreach ($required_perm as $p) {
            if (in_array($p, $effective, true)) {
                return true;
            }
        }
        return false;
    }

    return in_array($required_perm, $effective, true);
}

function require_permission($required_perm) {
    require_admin_login($GLOBALS['conn'] ?? null);
    if (!has_permission($required_perm)) {
        $_SESSION['flash_error'] = "Akses ditolak: Anda tidak memiliki izin untuk halaman tersebut.";
        header("Location: dashboard.php");
        exit;
    }
}
?>
