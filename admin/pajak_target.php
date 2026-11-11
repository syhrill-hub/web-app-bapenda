<?php
require_once __DIR__ . '/../includes/security_helpers.php';
bapenda_start_secure_session();
require_once '../config.php';
require_once 'includes/security.php';

if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit; }
require_permission('menu_tax_target');

$current_year = (int)date('Y');
$selected_year = isset($_GET['tahun']) ? (int)$_GET['tahun'] : $current_year;

// Gather years
$years = [];
for ($y = $current_year + 2; $y >= 2015; $y--) $years[] = $y;

// Handle POST: save all targets for selected year
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = "Token keamanan tidak valid.";
        header("Location: pajak_target.php?tahun={$selected_year}"); exit;
    }
    $save_year = (int)($_POST['tahun'] ?? $selected_year);
    $targets   = $_POST['target'] ?? [];
    $saved = 0;
    foreach ($targets as $jenis_id => $amount) {
        $jenis_id = (int)$jenis_id;
        $amount   = (float)str_replace(['.', ','], ['', '.'], $amount);
        $stmt = $conn->prepare("INSERT INTO pajak_target (jenis_id, tahun, target_amount)
                                VALUES (?, ?, ?)
                                ON DUPLICATE KEY UPDATE target_amount = VALUES(target_amount)");
        if ($stmt) {
            $stmt->bind_param('iid', $jenis_id, $save_year, $amount);
            $stmt->execute();
            $stmt->close();
            $saved++;
        }
    }
    $_SESSION['flash_success'] = "Target pajak tahun {$save_year} berhasil disimpan ({$saved} jenis pajak).";
    header("Location: pajak_target.php?tahun={$save_year}"); exit;
}

// Flash messages
$flash_msg = ''; $flash_type = '';
if (isset($_SESSION['flash_success'])) { $flash_msg = $_SESSION['flash_success']; $flash_type = 'success'; unset($_SESSION['flash_success']); }
elseif (isset($_SESSION['flash_error'])) { $flash_msg = $_SESSION['flash_error']; $flash_type = 'error'; unset($_SESSION['flash_error']); }

// Fetch all jenis pajak
$jenis_list = [];
$res = $conn->query("SELECT * FROM pajak_jenis ORDER BY urutan ASC");
if ($res) while ($row = $res->fetch_assoc()) $jenis_list[] = $row;

// Fetch existing targets for selected year
$existing = [];
$stmt = $conn->prepare("SELECT jenis_id, target_amount FROM pajak_target WHERE tahun = ?");
if ($stmt) { $stmt->bind_param('i', $selected_year); $stmt->execute(); $r = $stmt->get_result(); while ($row = $r->fetch_assoc()) $existing[$row['jenis_id']] = $row['target_amount']; $stmt->close(); }

$page_title = 'Target Pajak Daerah';
require_once 'layout_header.php';
?>

<?php if ($flash_msg): ?>
<div class="alert <?php echo $flash_type === 'error' ? 'alert-danger' : ''; ?>" style="margin-bottom:20px;">
    <?php echo htmlspecialchars($flash_msg); ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <h2><i class="fas fa-bullseye" style="margin-right:8px;"></i>Pengaturan Target Pajak Daerah</h2>
        <form method="GET" style="display:flex;align-items:center;gap:10px;">
            <label style="font-weight:600;font-size:14px;">Tahun:</label>
            <select name="tahun" style="padding:8px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;font-family:inherit;">
                <?php foreach ($years as $y): ?>
                <option value="<?php echo $y; ?>" <?php echo $y == $selected_year ? 'selected' : ''; ?>><?php echo $y; ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn" style="padding:8px 18px;font-size:14px;">Pilih</button>
        </form>
    </div>

    <p style="color:#64748b;font-size:14px;margin:0 0 20px;">
        Isi nilai target anggaran untuk masing-masing jenis pajak pada tahun <strong><?php echo $selected_year; ?></strong>.
        <span style="color:#0ea5e9;"><i class="fas fa-info-circle"></i> Ketik angka, titik pemisah ribuan akan otomatis ditambahkan.</span>
    </p>

    <form method="POST" action="pajak_target.php">
        <?php echo csrf_token_field(); ?>
        <input type="hidden" name="tahun" value="<?php echo $selected_year; ?>">

        <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr>
                    <th style="text-align:left;padding:12px 14px;background:#f8fafc;color:#64748b;font-size:12px;text-transform:uppercase;letter-spacing:.8px;border-bottom:2px solid #e2e8f0;width:40px;">#</th>
                    <th style="text-align:left;padding:12px 14px;background:#f8fafc;color:#64748b;font-size:12px;text-transform:uppercase;letter-spacing:.8px;border-bottom:2px solid #e2e8f0;white-space:nowrap;">Kode</th>
                    <th style="text-align:left;padding:12px 14px;background:#f8fafc;color:#64748b;font-size:12px;text-transform:uppercase;letter-spacing:.8px;border-bottom:2px solid #e2e8f0;">Nama Jenis Pajak</th>
                    <th style="text-align:left;padding:12px 14px;background:#f8fafc;color:#64748b;font-size:12px;text-transform:uppercase;letter-spacing:.8px;border-bottom:2px solid #e2e8f0;min-width:220px;">Target Tahun <?php echo $selected_year; ?> (Rp)</th>
                </tr>
            </thead>
            <tbody>
            <?php $no = 1; foreach ($jenis_list as $j):
                $val = $existing[$j['id']] ?? '';
            ?>
            <tr style="border-bottom:1px solid #f1f5f9;">
                <td style="padding:12px 14px;font-size:14px;color:#94a3b8;"><?php echo $no++; ?></td>
                <td style="padding:12px 14px;">
                    <span style="background:#e0f2fe;color:#0369a1;padding:3px 9px;border-radius:6px;font-size:11.5px;font-weight:600;white-space:nowrap;">
                        <?php echo htmlspecialchars($j['kode']); ?>
                    </span>
                </td>
                <td style="padding:12px 14px;font-size:14px;color:#1e293b;"><?php echo htmlspecialchars($j['nama']); ?></td>
                <td style="padding:12px 14px;">
                    <div style="display:flex;align-items:center;gap:6px;max-width:260px;">
                        <span style="color:#64748b;font-weight:600;font-size:14px;white-space:nowrap;">Rp</span>
                        <input
                            type="text"
                            name="target[<?php echo $j['id']; ?>]"
                            class="form-control rp-input"
                            value="<?php echo $val !== '' ? number_format((float)$val, 0, ',', '.') : ''; ?>"
                            placeholder="0"
                            inputmode="numeric"
                            autocomplete="off"
                            style="text-align:right;font-weight:600;">
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <div style="margin-top:28px;display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
            <button type="submit" class="btn">
                <i class="fas fa-save" style="margin-right:7px;"></i>Simpan Target Tahun <?php echo $selected_year; ?>
            </button>
            <a href="../pajak_dashboard.php?tahun=<?php echo $selected_year; ?>" target="_blank" rel="noopener noreferrer" class="btn-secondary" style="text-decoration:none;padding:13px 22px;">
                <i class="fas fa-external-link-alt" style="margin-right:6px;"></i>Lihat Dashboard Publik
            </a>
        </div>
    </form>
</div>

<script>
(function() {
    function formatRp(str) {
        var digits = str.replace(/\D/g, '');
        if (digits === '') return '';
        return parseInt(digits, 10).toLocaleString('id-ID');
    }

    function attachRpFormat(input) {
        // Format on load if has value
        if (input.value !== '') input.value = formatRp(input.value);

        input.addEventListener('input', function() {
            var pos = this.selectionStart;
            var oldLen = this.value.length;
            this.value = formatRp(this.value);
            // Restore cursor roughly
            var newLen = this.value.length;
            this.setSelectionRange(pos + (newLen - oldLen), pos + (newLen - oldLen));
        });

        input.addEventListener('focus', function() {
            this.style.borderColor = 'var(--primary)';
        });
        input.addEventListener('blur', function() {
            this.style.borderColor = '';
            if (this.value !== '') this.value = formatRp(this.value);
        });
    }

    // Attach to all rp-input fields
    document.querySelectorAll('.rp-input').forEach(attachRpFormat);

    // Strip dots before submit so PHP gets plain numbers
    document.querySelectorAll('form').forEach(function(form) {
        form.addEventListener('submit', function() {
            form.querySelectorAll('.rp-input').forEach(function(input) {
                input.value = input.value.replace(/\./g, '');
            });
        });
    });
})();
</script>

<?php require_once 'layout_footer.php'; ?>
