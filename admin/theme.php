<?php
require_once __DIR__ . '/../includes/security_helpers.php';
bapenda_start_secure_session();
require_once '../config.php';
require_once 'includes/security.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}
require_permission('menu_settings_theme');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = "Token keamanan tidak valid. Silakan coba lagi.";
    header("Location: theme.php");
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw_radius = (string)($_POST['theme_border_radius'] ?? '24');
    $safe_radius = in_array($raw_radius, ['4', '12', '24'], true) ? $raw_radius : '24';

    $settings = [
        'theme_primary_color' => bapenda_safe_css_color($_POST['theme_primary_color'] ?? '#0ea5e9', '#0ea5e9'),
        'theme_font_family' => bapenda_safe_css_font($_POST['theme_font_family'] ?? 'Poppins', ['Poppins', 'Inter', 'Roboto', 'Montserrat'], 'Poppins'),
        'theme_border_radius' => $safe_radius,
    ];

    foreach ($settings as $key => $val) {
        safe_settings_update($conn, $key, $val);
    }
    $_SESSION['flash_success'] = "Kustomisasi Tema berhasil disimpan!";
    header("Location: theme.php");
    exit;
}

$message = '';
if (isset($_SESSION['flash_success'])) {
    $message = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

// Fetch current
$settings = [];
$result = $conn->query("SELECT * FROM settings");
if ($result) {
    while($row = $result->fetch_assoc()){
        $settings[$row['key_name']] = $row['value'];
    }
}

$page_title = "Kustomisasi Tema Visual";
require_once 'layout_header.php'; 
?>

<?php if($message): ?>
    <div class="alert" style="background:#d1fae5; color:#065f46; padding:15px; border-radius:12px; margin-bottom:20px;">
        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<form method="post">
    <?php echo csrf_token_field(); ?>
    <div class="card">
        <div class="card-header">
            <h2>Pembuat Antarmuka (Theme Builder)</h2>
            <div>
                <button type="submit" class="btn"><i class="fas fa-save"></i> Terapkan Tema</button>
            </div>
        </div>
        
        <div class="form-group">
            <label>Warna Utama (Primary Color)</label>
            <input type="color" name="theme_primary_color" class="form-control" style="height: 50px; padding: 5px;" value="<?php echo htmlspecialchars($settings['theme_primary_color'] ?? '#0ea5e9'); ?>">
            <small style="color: #64748b; font-size: 13px; margin-top: 8px; display: inline-block;">Warna dasar untuk tombol utama, sorotan kartu, dan teks tautan.</small>
        </div>

        <div class="form-group">
            <label>Keluarga Huruf (Font Family)</label>
            <select name="theme_font_family" class="form-control">
                <option value="Poppins" <?php echo (isset($settings['theme_font_family']) && $settings['theme_font_family'] == 'Poppins') ? 'selected' : ''; ?>>Poppins (Bawaan Bapenda - Dinamis & Modern)</option>
                <option value="Inter" <?php echo (isset($settings['theme_font_family']) && $settings['theme_font_family'] == 'Inter') ? 'selected' : ''; ?>>Inter (Sangat Profesional & Mudah Dibaca)</option>
                <option value="Roboto" <?php echo (isset($settings['theme_font_family']) && $settings['theme_font_family'] == 'Roboto') ? 'selected' : ''; ?>>Roboto (Kaku, Resmi & Bersih)</option>
                <option value="Montserrat" <?php echo (isset($settings['theme_font_family']) && $settings['theme_font_family'] == 'Montserrat') ? 'selected' : ''; ?>>Montserrat (Lebesar & Tegas - Cocok untuk Banner)</option>
            </select>
        </div>

        <div class="form-group">
            <label>Gaya Kelengkungan (Border Radius)</label>
            <select name="theme_border_radius" class="form-control">
                <option value="4" <?php echo (isset($settings['theme_border_radius']) && $settings['theme_border_radius'] == '4') ? 'selected' : ''; ?>>Kaku & Tegas (Radius 4px)</option>
                <option value="12" <?php echo (isset($settings['theme_border_radius']) && $settings['theme_border_radius'] == '12') ? 'selected' : ''; ?>>Lunak (Radius 12px)</option>
                <option value="24" <?php echo (!isset($settings['theme_border_radius']) || $settings['theme_border_radius'] == '24') ? 'selected' : ''; ?>>Modern Kapsul (Radius 24px - Bawaan Bapenda)</option>
            </select>
            <small style="color: #64748b; font-size: 13px; margin-top: 8px; display: inline-block;">Ubah bentuk desain komponen kartu: Kaku untuk kesan formal instansi, Melengkung besar untuk kesan masa depan yang ceria.</small>
        </div>
    </div>
</form>

<?php require_once 'layout_footer.php'; ?>
