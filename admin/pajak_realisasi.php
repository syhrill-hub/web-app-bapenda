<?php
session_start();
require_once '../config.php';
require_once 'includes/security.php';

if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit; }
require_permission('menu_tax_realisasi');

$current_year  = (int)date('Y');
$current_month = (int)date('n');
$selected_year  = isset($_GET['tahun']) ? (int)$_GET['tahun']  : $current_year;
$selected_month = isset($_GET['bulan']) ? (int)$_GET['bulan']  : $current_month;
if ($selected_month < 1 || $selected_month > 12) $selected_month = $current_month;

$month_names = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$years = [];
for ($y = $current_year + 2; $y >= 2015; $y--) $years[] = $y;

// Handle DELETE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { $_SESSION['flash_error'] = "Token tidak valid."; header("Location: pajak_realisasi.php?tahun={$selected_year}&bulan={$selected_month}"); exit; }
    $del_id = (int)($_POST['del_id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM pajak_realisasi WHERE id = ?");
    if ($stmt) { $stmt->bind_param('i', $del_id); $stmt->execute(); $stmt->close(); }
    $_SESSION['flash_success'] = "Data realisasi berhasil dihapus.";
    header("Location: pajak_realisasi.php?tahun={$selected_year}&bulan={$selected_month}"); exit;
}

// Handle SAVE (upsert all jenis for month+year)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { $_SESSION['flash_error'] = "Token tidak valid."; header("Location: pajak_realisasi.php?tahun={$selected_year}&bulan={$selected_month}"); exit; }
    $save_year  = (int)($_POST['tahun'] ?? $selected_year);
    $save_month = (int)($_POST['bulan'] ?? $selected_month);
    $realisasi  = $_POST['realisasi'] ?? [];
    $saved = 0;
    foreach ($realisasi as $jenis_id => $amount) {
        $jenis_id = (int)$jenis_id;
        $amount   = (float)str_replace(['.', ','], ['', '.'], $amount);
        $stmt = $conn->prepare("INSERT INTO pajak_realisasi (jenis_id, tahun, bulan, realisasi_amount)
                                VALUES (?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE realisasi_amount = VALUES(realisasi_amount)");
        if ($stmt) { $stmt->bind_param('iiid', $jenis_id, $save_year, $save_month, $amount); $stmt->execute(); $stmt->close(); $saved++; }
    }
    $_SESSION['flash_success'] = "Realisasi bulan {$month_names[$save_month]} {$save_year} berhasil disimpan ({$saved} jenis pajak).";
    header("Location: pajak_realisasi.php?tahun={$save_year}&bulan={$save_month}"); exit;
}

// Flash
$flash_msg = ''; $flash_type = '';
if (isset($_SESSION['flash_success'])) { $flash_msg = $_SESSION['flash_success']; $flash_type = 'success'; unset($_SESSION['flash_success']); }
elseif (isset($_SESSION['flash_error'])) { $flash_msg = $_SESSION['flash_error']; $flash_type = 'error'; unset($_SESSION['flash_error']); }

// Fetch jenis pajak
$jenis_list = [];
$res = $conn->query("SELECT * FROM pajak_jenis ORDER BY urutan ASC");
if ($res) while ($row = $res->fetch_assoc()) $jenis_list[] = $row;

// Fetch realisasi for selected month & year
$existing = [];
$stmt = $conn->prepare("SELECT id, jenis_id, realisasi_amount FROM pajak_realisasi WHERE tahun = ? AND bulan = ?");
if ($stmt) { $stmt->bind_param('ii', $selected_year, $selected_month); $stmt->execute(); $r = $stmt->get_result(); while ($row = $r->fetch_assoc()) { $existing[$row['jenis_id']] = ['id' => $row['id'], 'amount' => $row['realisasi_amount']]; } $stmt->close(); }

// Fetch YTD totals (for summary column)
$ytd = [];
$stmt = $conn->prepare("SELECT jenis_id, SUM(realisasi_amount) AS total FROM pajak_realisasi WHERE tahun = ? GROUP BY jenis_id");
if ($stmt) { $stmt->bind_param('i', $selected_year); $stmt->execute(); $r = $stmt->get_result(); while ($row = $r->fetch_assoc()) $ytd[$row['jenis_id']] = (float)$row['total']; $stmt->close(); }

// Fetch targets
$targets = [];
$stmt = $conn->prepare("SELECT jenis_id, target_amount FROM pajak_target WHERE tahun = ?");
if ($stmt) { $stmt->bind_param('i', $selected_year); $stmt->execute(); $r = $stmt->get_result(); while ($row = $r->fetch_assoc()) $targets[$row['jenis_id']] = (float)$row['target_amount']; $stmt->close(); }

$page_title = 'Realisasi Pajak Daerah';
require_once 'layout_header.php';

function fmt_rp($n) { return 'Rp ' . number_format($n, 0, ',', '.'); }
?>

<?php if ($flash_msg): ?>
<div class="alert <?php echo $flash_type === 'error' ? 'alert-danger' : ''; ?>" style="margin-bottom:20px;">
    <?php echo htmlspecialchars($flash_msg); ?>
</div>
<?php endif; ?>

<!-- Filter -->
<div class="card" style="margin-bottom:24px;padding:20px 28px;">
    <form method="GET" action="pajak_realisasi.php" style="display:flex;align-items:center;flex-wrap:wrap;gap:14px;">
        <label style="font-weight:600;font-size:14px;"><i class="fas fa-filter" style="color:#0ea5e9;margin-right:6px;"></i>Filter:</label>
        <div style="display:flex;align-items:center;gap:8px;">
            <label style="font-size:13px;color:#64748b;">Tahun</label>
            <select name="tahun" style="padding:8px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;font-family:inherit;">
                <?php foreach ($years as $y): ?>
                <option value="<?php echo $y; ?>" <?php echo $y == $selected_year ? 'selected' : ''; ?>><?php echo $y; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display:flex;align-items:center;gap:8px;">
            <label style="font-size:13px;color:#64748b;">Bulan</label>
            <select name="bulan" style="padding:8px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;font-family:inherit;">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?php echo $m; ?>" <?php echo $m == $selected_month ? 'selected' : ''; ?>><?php echo $month_names[$m]; ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <button type="submit" class="btn" style="padding:9px 20px;font-size:14px;">Tampilkan</button>
        <a href="../pajak_dashboard.php?tahun=<?php echo $selected_year; ?>" target="_blank" class="btn-secondary" style="text-decoration:none;padding:9px 16px;font-size:13px;">
            <i class="fas fa-external-link-alt" style="margin-right:5px;"></i>Dashboard Publik
        </a>
    </form>
</div>

<!-- Table / Form -->
<div class="card">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
        <h2>
            <i class="fas fa-chart-bar" style="margin-right:8px;"></i>
            Realisasi Pajak &mdash; <?php echo $month_names[$selected_month]; ?> <?php echo $selected_year; ?>
        </h2>
        <span style="font-size:13px;color:#64748b;">
            Isi nilai realisasi penerimaan per jenis pajak pada bulan ini.
            <span style="color:#0ea5e9;"><i class="fas fa-info-circle"></i> Ketik angka, titik pemisah ribuan otomatis ditambahkan.</span>
        </span>
    </div>

    <form method="POST" action="pajak_realisasi.php?tahun=<?php echo $selected_year; ?>&bulan=<?php echo $selected_month; ?>">
        <?php echo csrf_token_field(); ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="tahun" value="<?php echo $selected_year; ?>">
        <input type="hidden" name="bulan" value="<?php echo $selected_month; ?>">

        <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr>
                    <th style="text-align:left;padding:12px 14px;background:#f8fafc;color:#64748b;font-size:12px;text-transform:uppercase;letter-spacing:.8px;border-bottom:2px solid #e2e8f0;width:36px;">#</th>
                    <th style="text-align:left;padding:12px 14px;background:#f8fafc;color:#64748b;font-size:12px;text-transform:uppercase;letter-spacing:.8px;border-bottom:2px solid #e2e8f0;white-space:nowrap;">Kode</th>
                    <th style="text-align:left;padding:12px 14px;background:#f8fafc;color:#64748b;font-size:12px;text-transform:uppercase;letter-spacing:.8px;border-bottom:2px solid #e2e8f0;">Nama Jenis Pajak</th>
                    <th style="text-align:left;padding:12px 14px;background:#f8fafc;color:#64748b;font-size:12px;text-transform:uppercase;letter-spacing:.8px;border-bottom:2px solid #e2e8f0;min-width:200px;">Realisasi Bulan Ini (Rp)</th>
                    <th style="text-align:right;padding:12px 14px;background:#f8fafc;color:#64748b;font-size:12px;text-transform:uppercase;letter-spacing:.8px;border-bottom:2px solid #e2e8f0;white-space:nowrap;">YTD <?php echo $selected_year; ?></th>
                    <th style="text-align:right;padding:12px 14px;background:#f8fafc;color:#64748b;font-size:12px;text-transform:uppercase;letter-spacing:.8px;border-bottom:2px solid #e2e8f0;white-space:nowrap;">Target</th>
                    <th style="text-align:right;padding:12px 14px;background:#f8fafc;color:#64748b;font-size:12px;text-transform:uppercase;letter-spacing:.8px;border-bottom:2px solid #e2e8f0;white-space:nowrap;">% Capaian</th>
                </tr>
            </thead>
            <tbody>
            <?php $no = 1; foreach ($jenis_list as $j):
                $ex_amount = $existing[$j['id']]['amount'] ?? 0;
                $ytd_val   = $ytd[$j['id']] ?? 0;
                $target_val = $targets[$j['id']] ?? 0;
                $pct = $target_val > 0 ? round($ytd_val / $target_val * 100, 2) : 0;
            ?>
            <tr style="border-bottom:1px solid #f1f5f9;">
                <td style="padding:12px 14px;font-size:14px;color:#94a3b8;"><?php echo $no++; ?></td>
                <td style="padding:12px 14px;">
                    <span style="background:#e0f2fe;color:#0369a1;padding:3px 9px;border-radius:6px;font-size:11px;font-weight:600;white-space:nowrap;">
                        <?php echo htmlspecialchars($j['kode']); ?>
                    </span>
                </td>
                <td style="padding:12px 14px;font-size:13.5px;color:#1e293b;"><?php echo htmlspecialchars($j['nama']); ?></td>
                <td style="padding:12px 14px;">
                    <div style="display:flex;align-items:center;gap:6px;max-width:240px;">
                        <span style="color:#64748b;font-weight:600;font-size:14px;white-space:nowrap;">Rp</span>
                        <input
                            type="text"
                            name="realisasi[<?php echo $j['id']; ?>]"
                            class="form-control rp-input"
                            value="<?php echo $ex_amount != 0 ? number_format((float)$ex_amount, 0, ',', '.') : ''; ?>"
                            placeholder="0"
                            inputmode="numeric"
                            autocomplete="off"
                            style="text-align:right;font-weight:600;">
                    </div>
                </td>
                <td style="padding:12px 14px;text-align:right;font-size:13px;font-weight:600;color:#1e293b;">
                    <?php echo fmt_rp($ytd_val); ?>
                </td>
                <td style="padding:12px 14px;text-align:right;font-size:13px;color:#64748b;">
                    <?php echo fmt_rp($target_val); ?>
                </td>
                <td style="padding:12px 14px;text-align:right;">
                    <?php if ($target_val > 0): ?>
                    <span style="font-weight:700;font-size:13px;color:<?php echo $pct >= 100 ? '#16a34a' : ($pct >= 70 ? '#0ea5e9' : '#ea580c'); ?>;">
                        <?php echo number_format($pct, 1, ',', '.'); ?>%
                    </span>
                    <?php else: ?>
                    <span style="color:#cbd5e1;font-size:12px;">-</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:#f8fafc;border-top:2px solid #e2e8f0;">
                    <td colspan="4" style="padding:13px 14px;font-weight:700;font-size:14px;"><i class="fas fa-sigma" style="margin-right:6px;color:#0ea5e9;"></i>TOTAL</td>
                    <td style="padding:13px 14px;text-align:right;font-weight:800;font-size:13px;color:#1e293b;"><?php echo fmt_rp(array_sum(array_column($ytd, null)) ?: array_sum($ytd)); ?></td>
                    <td style="padding:13px 14px;text-align:right;font-weight:700;font-size:13px;color:#64748b;"><?php echo fmt_rp(array_sum($targets)); ?></td>
                    <td style="padding:13px 14px;text-align:right;font-weight:700;font-size:13px;">
                        <?php
                        $t_tot = array_sum($targets);
                        $r_tot = array_sum($ytd);
                        $p_tot = $t_tot > 0 ? round($r_tot / $t_tot * 100, 1) : 0;
                        echo $t_tot > 0 ? "<span style='color:" . ($p_tot >= 100 ? '#16a34a' : '#0ea5e9') . ";'>{$p_tot}%</span>" : '-';
                        ?>
                    </td>
                </tr>
            </tfoot>
        </table>
        </div>

        <div style="margin-top:24px;display:flex;gap:12px;flex-wrap:wrap;">
            <button type="submit" class="btn">
                <i class="fas fa-save" style="margin-right:7px;"></i>Simpan Realisasi <?php echo $month_names[$selected_month]; ?> <?php echo $selected_year; ?>
            </button>
        </div>
    </form>
</div>

<!-- Monthly breakdown summary per jenis -->
<div class="card" style="margin-top:24px;">
    <div class="card-header">
        <h2><i class="fas fa-calendar-check" style="margin-right:8px;color:#0ea5e9;"></i>Rekap Bulanan Tahun <?php echo $selected_year; ?></h2>
    </div>
    <?php
    // Get all monthly realisasi for current year
    $monthly = [];
    $stmt = $conn->prepare("SELECT jenis_id, bulan, realisasi_amount FROM pajak_realisasi WHERE tahun = ? ORDER BY bulan ASC");
    if ($stmt) {
        $stmt->bind_param('i', $selected_year);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) {
            $monthly[$row['jenis_id']][$row['bulan']] = (float)$row['realisasi_amount'];
        }
        $stmt->close();
    }
    ?>
    <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;min-width:900px;">
        <thead>
            <tr>
                <th style="text-align:left;padding:10px 12px;background:#f8fafc;color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.7px;border-bottom:2px solid #e2e8f0;">Jenis Pajak</th>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                <th style="text-align:right;padding:10px 8px;background:#f8fafc;color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.7px;border-bottom:2px solid #e2e8f0;white-space:nowrap;
                    <?php echo $m == $selected_month ? 'color:#0ea5e9;font-weight:800;' : ''; ?>">
                    <?php echo substr($month_names[$m], 0, 3); ?>
                </th>
                <?php endfor; ?>
                <th style="text-align:right;padding:10px 12px;background:#f8fafc;color:#0ea5e9;font-size:11px;text-transform:uppercase;letter-spacing:.7px;border-bottom:2px solid #e2e8f0;white-space:nowrap;font-weight:800;">Total YTD</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($jenis_list as $j): $total_ytd = 0; ?>
        <tr style="border-bottom:1px solid #f1f5f9;">
            <td style="padding:10px 12px;font-size:12px;color:#1e293b;max-width:180px;">
                <span style="display:block;font-weight:600;font-size:11px;color:#0369a1;"><?php echo htmlspecialchars($j['kode']); ?></span>
                <span style="font-size:11.5px;"><?php echo htmlspecialchars($j['nama']); ?></span>
            </td>
            <?php for ($m = 1; $m <= 12; $m++):
                $amt = $monthly[$j['id']][$m] ?? 0;
                $total_ytd += $amt;
            ?>
            <td style="padding:10px 8px;text-align:right;font-size:12px;color:<?php echo $amt > 0 ? '#1e293b' : '#cbd5e1'; ?>;<?php echo $m == $selected_month ? 'background:#f0f9ff;font-weight:700;' : ''; ?>">
                <?php echo $amt > 0 ? number_format($amt / 1e6, 2, ',', '.') : '-'; ?>
            </td>
            <?php endfor; ?>
            <td style="padding:10px 12px;text-align:right;font-size:12px;font-weight:800;color:#0ea5e9;">
                <?php echo $total_ytd > 0 ? number_format($total_ytd / 1e6, 2, ',', '.') : '-'; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p style="font-size:11px;color:#94a3b8;margin-top:8px;padding:0 4px;">Nilai dalam Juta Rupiah (Jt). Kolom <?php echo $month_names[$selected_month]; ?> di-highlight sesuai filter aktif.</p>
    </div>
</div>

<script>
(function() {
    function formatRp(str) {
        var digits = str.replace(/\D/g, '');
        if (digits === '') return '';
        return parseInt(digits, 10).toLocaleString('id-ID');
    }

    function attachRpFormat(input) {
        if (input.value !== '') input.value = formatRp(input.value);

        input.addEventListener('input', function() {
            var pos = this.selectionStart;
            var oldLen = this.value.length;
            this.value = formatRp(this.value);
            var newLen = this.value.length;
            this.setSelectionRange(pos + (newLen - oldLen), pos + (newLen - oldLen));
        });

        input.addEventListener('focus', function() { this.style.borderColor = 'var(--primary)'; });
        input.addEventListener('blur', function() {
            this.style.borderColor = '';
            if (this.value !== '') this.value = formatRp(this.value);
        });
    }

    document.querySelectorAll('.rp-input').forEach(attachRpFormat);

    // Strip thousand-separator dots before form submit
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
