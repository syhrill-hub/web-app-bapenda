<?php
require_once 'includes/header.php';
$page_title = 'Dashboard Target & Realisasi Pajak Daerah';

// Get filter year
$current_year = (int) date('Y');
$selected_year = isset($_GET['tahun']) ? (int) $_GET['tahun'] : $current_year;
$selected_bulan = isset($_GET['bulan']) ? $_GET['bulan'] : 'all';

$months_array = [
    1 => 'Januari',
    2 => 'Februari',
    3 => 'Maret',
    4 => 'April',
    5 => 'Mei',
    6 => 'Juni',
    7 => 'Juli',
    8 => 'Agustus',
    9 => 'September',
    10 => 'Oktober',
    11 => 'November',
    12 => 'Desember'
];
if ($selected_bulan !== 'all' && (!ctype_digit((string) $selected_bulan) || !isset($months_array[(int) $selected_bulan]))) {
    $selected_bulan = 'all';
}
$bulan_text = ($selected_bulan !== 'all' && isset($months_array[(int) $selected_bulan])) ? ' s/d ' . $months_array[(int) $selected_bulan] : '';

// Get available years dynamically from DB (all years with any data)
require_once __DIR__ . '/includes/sinpelaja.php';
$data_source = 'local';
$sinpelaja_fetched_at = null;
$sinpelaja_bundle = null;

$sinpelaja_pdo = sinpelaja_get_pdo();
if ($sinpelaja_pdo instanceof PDO) {
    try {
        $sinpelaja_bundle = sinpelaja_fetch_dashboard_bundle($sinpelaja_pdo, $selected_year, $selected_bulan);
    } catch (Throwable $e) {
        $sinpelaja_bundle = null;
    }
}

if (is_array($sinpelaja_bundle)) {
    $data_source          = 'sinpelaja';
    $sinpelaja_fetched_at = $sinpelaja_bundle['fetched_at'] ?? null;
    $years                = $sinpelaja_bundle['years'];
    $selected_year        = $sinpelaja_bundle['selected_year'];
    $jenis_list           = $sinpelaja_bundle['jenis_list'];
    $targets              = $sinpelaja_bundle['targets'];
    $realisasi            = $sinpelaja_bundle['realisasi'];
    $multi_years          = $sinpelaja_bundle['multi_years'];
    $my_labels            = $sinpelaja_bundle['my_labels'];
    $my_targets           = $sinpelaja_bundle['my_targets'];
    $my_real              = $sinpelaja_bundle['my_real'];
    $my_pct               = $sinpelaja_bundle['my_pct'];
}

if ($data_source !== 'sinpelaja') {
$years = [];
$res_yf = $conn->query(
    "SELECT DISTINCT tahun FROM pajak_target
     UNION
     SELECT DISTINCT tahun FROM pajak_realisasi
     ORDER BY tahun DESC"
);
if ($res_yf)
    while ($row = $res_yf->fetch_assoc())
        $years[] = (int) $row['tahun'];
// Always include current year even if no data yet
if (!in_array($current_year, $years))
    array_unshift($years, $current_year);
sort($years);
$years = array_reverse($years); // descending

// Fetch all jenis pajak
$jenis_list = [];
$res = $conn->query("SELECT * FROM pajak_jenis ORDER BY urutan ASC");
if ($res)
    while ($row = $res->fetch_assoc())
        $jenis_list[] = $row;

// Fetch targets for selected year
$targets = [];
$stmt = $conn->prepare("SELECT jenis_id, target_amount FROM pajak_target WHERE tahun = ?");
if ($stmt) {
    $stmt->bind_param('i', $selected_year);
    $stmt->execute();
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc())
        $targets[$row['jenis_id']] = (float) $row['target_amount'];
    $stmt->close();
}

// Fetch YTD realisasi (sum per jenis) for selected year
$realisasi = [];
if ($selected_bulan !== 'all') {
    $m = (int) $selected_bulan;
    $stmt = $conn->prepare("SELECT jenis_id, SUM(realisasi_amount) AS total FROM pajak_realisasi WHERE tahun = ? AND bulan <= ? GROUP BY jenis_id");
    if ($stmt) {
        $stmt->bind_param('ii', $selected_year, $m);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc())
            $realisasi[$row['jenis_id']] = (float) $row['total'];
        $stmt->close();
    }
} else {
    $stmt = $conn->prepare("SELECT jenis_id, SUM(realisasi_amount) AS total FROM pajak_realisasi WHERE tahun = ? GROUP BY jenis_id");
    if ($stmt) {
        $stmt->bind_param('i', $selected_year);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc())
            $realisasi[$row['jenis_id']] = (float) $row['total'];
        $stmt->close();
    }
}

// Compute totals
    // Deduplikasi jenis pajak jika tabel MySQL lokal memiliki entri kode ganda
    $dedup_jenis = [];
    $kode_canon = [];
    $new_targets = [];
    $new_realisasi = [];
    foreach ($jenis_list as $jrow) {
        $kode = trim((string) ($jrow['kode'] ?? ''));
        $jid = $jrow['id'];
        if (!isset($kode_canon[$kode])) {
            $kode_canon[$kode] = $jid;
            $dedup_jenis[] = $jrow;
            $new_targets[$jid] = (float) ($targets[$jid] ?? 0);
            $new_realisasi[$jid] = (float) ($realisasi[$jid] ?? 0);
        } else {
            $cid = $kode_canon[$kode];
            $new_targets[$cid] += (float) ($targets[$jid] ?? 0);
            $new_realisasi[$cid] += (float) ($realisasi[$jid] ?? 0);
        }
    }
    $jenis_list = $dedup_jenis;
    $targets = $new_targets;
    $realisasi = $new_realisasi;
}

$total_target = array_sum($targets);
$total_realisasi = array_sum($realisasi);
$pct_total = $total_target > 0 ? round($total_realisasi / $total_target * 100, 2) : 0;

// Prepare chart data
$chart_labels = [];
$chart_target = [];
$chart_real = [];
foreach ($jenis_list as $j) {
    // Short label (take last word(s) from nama)
    $parts = explode('-', $j['nama']);
    $short = trim(end($parts));
    if (strlen($short) > 20)
        $short = substr($short, 0, 18) . '…';
    $chart_labels[] = $j['kode'];
    $chart_target[] = (float) ($targets[$j['id']] ?? 0) / 1e6; // in Juta
    $chart_real[] = (float) ($realisasi[$j['id']] ?? 0) / 1e6;
}

function fmt_rp($n)
{
    return 'Rp ' . number_format($n, 0, ',', '.');
}

// ── Multi-year comparison data ─────────────────────────────────────────
// Fetch all years that have either target or realisasi data
if ($data_source !== 'sinpelaja') {
$multi_years = [];
$res_y = $conn->query(
    "SELECT DISTINCT tahun FROM pajak_target
     UNION
     SELECT DISTINCT tahun FROM pajak_realisasi
     ORDER BY tahun ASC"
);
if ($res_y)
    while ($r = $res_y->fetch_assoc())
        $multi_years[] = (int) $r['tahun'];

// If no data yet, show a default range of last 3 years
if (empty($multi_years)) {
    for ($y = $current_year - 2; $y <= $current_year; $y++)
        $multi_years[] = $y;
}

$my_labels = [];  // year labels
$my_targets = [];  // total target per year (Miliar Rp)
$my_real = [];  // total realisasi per year (Miliar Rp)
$my_pct = [];  // % capaian per year

foreach ($multi_years as $yr) {
    // Total target
    $stmt = $conn->prepare("SELECT COALESCE(SUM(target_amount),0) AS tot FROM pajak_target WHERE tahun = ?");
    $tot_t = 0;
    if ($stmt) {
        $stmt->bind_param('i', $yr);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $tot_t = (float) $r['tot'];
        $stmt->close();
    }

    // Total realisasi
    $stmt = $conn->prepare("SELECT COALESCE(SUM(realisasi_amount),0) AS tot FROM pajak_realisasi WHERE tahun = ?");
    $tot_r = 0;
    if ($stmt) {
        $stmt->bind_param('i', $yr);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $tot_r = (float) $r['tot'];
        $stmt->close();
    }

    $pct_yr = $tot_t > 0 ? round($tot_r / $tot_t * 100, 2) : 0;

    $my_labels[] = (string) $yr;
    $my_targets[] = round($tot_t / 1e9, 3); // Miliar Rp
    $my_real[] = round($tot_r / 1e9, 3);
    $my_pct[] = $pct_yr;
}
}
?>

<style>
    .pajak-hero {
        background: linear-gradient(135deg, #0369a1 0%, #0ea5e9 60%, #38bdf8 100%);
        color: #fff;
        padding: 60px 0 80px;
        text-align: center;
        position: relative;
        overflow: hidden;
    }

    .pajak-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    }

    .pajak-hero h1 {
        font-size: 2rem;
        margin: 0 0 8px;
        font-weight: 800;
    }

    .pajak-hero p {
        font-size: 1rem;
        opacity: .85;
        margin: 0;
    }

    .pajak-section {
        padding: 50px 0;
    }

    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
    }

    /* Filter bar */
    .filter-bar {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 36px;
    }

    .filter-bar label {
        font-weight: 600;
        color: #374151;
    }

    .filter-bar select {
        padding: 10px 18px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-size: 15px;
        background: #fff;
        cursor: pointer;
        font-family: inherit;
        color: #1e293b;
        box-shadow: 0 2px 6px rgba(0, 0, 0, .04);
    }

    .filter-bar button {
        background: #0ea5e9;
        color: #fff;
        border: none;
        padding: 10px 22px;
        border-radius: 10px;
        cursor: pointer;
        font-size: 15px;
        font-weight: 600;
        font-family: inherit;
        transition: .2s;
    }

    .filter-bar button:hover {
        background: #0284c7;
    }

    /* Summary cards */
    .summary-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 40px;
    }

    @media (max-width: 768px) {
        .summary-grid {
            grid-template-columns: 1fr;
        }
    }

    .summary-card {
        background: #fff;
        border-radius: 18px;
        padding: 28px 24px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, .05);
        border: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        gap: 18px;
        transition: .25s;
    }

    .summary-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(0, 0, 0, .09);
    }

    .summary-icon {
        width: 56px;
        height: 56px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        flex-shrink: 0;
    }

    .summary-icon.blue {
        background: #e0f2fe;
        color: #0284c7;
    }

    .summary-icon.green {
        background: #dcfce7;
        color: #16a34a;
    }

    .summary-icon.orange {
        background: #fff7ed;
        color: #ea580c;
    }

    .summary-label {
        font-size: 13px;
        color: #64748b;
        font-weight: 500;
        margin-bottom: 4px;
    }

    .summary-value {
        font-size: 1.3rem;
        font-weight: 800;
        color: #0f172a;
        line-height: 1;
    }

    .summary-sub {
        font-size: 12px;
        color: #94a3b8;
        margin-top: 4px;
    }

    /* Chart area */
    .chart-card {
        background: #fff;
        border-radius: 18px;
        padding: 28px 24px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, .05);
        border: 1px solid #f1f5f9;
        margin-bottom: 36px;
    }

    .chart-card h3 {
        margin: 0 0 20px;
        font-size: 17px;
        color: #1e293b;
        font-weight: 700;
    }

    .chart-wrapper {
        position: relative;
        height: 320px;
    }

    /* Table */
    .table-card {
        background: #fff;
        border-radius: 18px;
        padding: 28px 24px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, .05);
        border: 1px solid #f1f5f9;
        overflow-x: auto;
    }

    .table-card h3 {
        margin: 0 0 20px;
        font-size: 17px;
        color: #1e293b;
        font-weight: 700;
    }

    .pajak-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 800px;
    }

    .pajak-table th {
        background: #f8fafc;
        color: #64748b;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .8px;
        font-weight: 700;
        padding: 13px 14px;
        border-bottom: 2px solid #e2e8f0;
        text-align: left;
        white-space: nowrap;
    }

    .pajak-table th.right,
    .pajak-table td.right {
        text-align: right;
    }

    .pajak-table td {
        padding: 14px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 14px;
        color: #1e293b;
        vertical-align: middle;
    }

    .pajak-table tbody tr:hover {
        background: #f8fafc;
    }

    .pajak-table tbody tr:last-child td {
        border-bottom: none;
    }

    .kode-badge {
        display: inline-block;
        background: #e0f2fe;
        color: #0369a1;
        padding: 3px 9px;
        border-radius: 6px;
        font-size: 11.5px;
        font-weight: 600;
        white-space: nowrap;
    }

    /* Progress bar */
    .progress-wrap {
        background: #f1f5f9;
        border-radius: 99px;
        height: 8px;
        min-width: 80px;
    }

    .progress-fill {
        height: 8px;
        border-radius: 99px;
        background: linear-gradient(90deg, #0ea5e9, #38bdf8);
        transition: width .4s;
    }

    .progress-fill.over {
        background: linear-gradient(90deg, #16a34a, #4ade80);
    }

    .pct-label {
        font-size: 13px;
        font-weight: 700;
        color: #0ea5e9;
        white-space: nowrap;
    }

    .pct-label.over {
        color: #16a34a;
    }

    .pct-label.warn {
        color: #ea580c;
    }

    /* Footer total row */
    .total-row td {
        font-weight: 700;
        background: #f8fafc;
        border-top: 2px solid #e2e8f0;
        font-size: 14px;
    }

    /* ── Multi-year section ── */
    .section-divider {
        display: flex;
        align-items: center;
        gap: 14px;
        margin: 50px 0 28px;
    }

    .section-divider::before,
    .section-divider::after {
        content: '';
        flex: 1;
        height: 1px;
        background: linear-gradient(90deg, transparent, #e2e8f0, transparent);
    }

    .section-divider-label {
        background: linear-gradient(135deg, #0369a1, #0ea5e9);
        color: #fff;
        padding: 8px 22px;
        border-radius: 99px;
        font-size: 14px;
        font-weight: 700;
        white-space: nowrap;
        letter-spacing: .3px;
        box-shadow: 0 4px 14px rgba(14, 165, 233, .3);
    }

    .chart-wrapper-tall {
        position: relative;
        height: 380px;
    }

    /* Year comparison table */
    .year-table {
        width: 100%;
        border-collapse: collapse;
    }

    .year-table th {
        background: #f8fafc;
        color: #64748b;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .8px;
        font-weight: 700;
        padding: 12px 16px;
        border-bottom: 2px solid #e2e8f0;
        text-align: right;
    }

    .year-table th:first-child {
        text-align: center;
    }

    .year-table td {
        padding: 14px 16px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 14px;
        color: #1e293b;
        text-align: right;
    }

    .year-table td:first-child {
        text-align: center;
        font-weight: 700;
    }

    .year-table tbody tr:hover {
        background: #f8fafc;
    }

    .year-table tbody tr:last-child td {
        border: none;
    }

    .year-badge {
        display: inline-block;
        background: #e0f2fe;
        color: #0369a1;
        padding: 4px 14px;
        border-radius: 99px;
        font-size: 13px;
        font-weight: 700;
    }

    .year-badge.active {
        background: var(--primary, #0ea5e9);
        color: #fff;
    }
    .source-status-row {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 14px;
    }
    .source-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 14px;
        border-radius: 999px;
        font-size: 12.5px;
        font-weight: 600;
        letter-spacing: .2px;
        box-shadow: 0 2px 8px rgba(15, 23, 42, .05);
    }
    .source-pill.realtime {
        background: #ecfdf5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }
    .source-pill.local {
        background: #f8fafc;
        color: #475569;
        border: 1px solid #cbd5e1;
    }
    .source-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
    }
    .source-pill.realtime .source-dot {
        background: #10b981;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, .22);
    }
    .source-pill.local .source-dot {
        background: #64748b;
    }
</style>

<!-- Hero -->
<div class="pajak-hero">
    <div class="container">
        <h1><i class="fas fa-chart-bar" style="margin-right:10px;"></i>Dashboard Target &amp; Realisasi Pajak Daerah
        </h1>
        <p>Badan Pendapatan Daerah Kabupaten Kotawaringin Barat &mdash; Tahun <?php echo $selected_year; ?></p>
    </div>
</div>

<section class="pajak-section">
    <div class="container">

        <!-- Filter -->
        <div class="source-status-row">
            <?php if ($data_source === 'sinpelaja'): ?>
                <span class="source-pill realtime" title="Data target dan realisasi ditarik langsung dari database SINPELAJA">
                    <span class="source-dot"></span>
                    Realtime SINPELAJA &bull; Diperbarui <?php echo htmlspecialchars(substr((string) ($sinpelaja_fetched_at ?? date('H:i:s')), 11, 5)); ?> WIB
                </span>
            <?php else: ?>
                <span class="source-pill local" title="Menampilkan data tersimpan pada database portal website">
                    <span class="source-dot"></span>
                    Data Portal Lokal
                </span>
            <?php endif; ?>
        </div>
        <form method="GET" action="pajak_dashboard.php">
            <div class="filter-bar">
                <label for="tahun"><i class="fas fa-calendar-alt" style="margin-right:6px;color:#0ea5e9;"></i>Pilih
                    Tahun:</label>
                <select name="tahun" id="tahun">
                    <?php foreach ($years as $y): ?>
                        <option value="<?php echo $y; ?>" <?php echo $y == $selected_year ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label for="bulan" style="margin-left:8px;"><i class="fas fa-clock"
                        style="margin-right:6px;color:#0ea5e9;"></i>Bulan:</label>
                <select name="bulan" id="bulan">
                    <option value="all" <?php echo $selected_bulan === 'all' ? 'selected' : ''; ?>>Semua Bulan (Kumulatif
                        1 Thn)</option>
                    <?php foreach ($months_array as $num => $name): ?>
                        <option value="<?php echo $num; ?>" <?php echo ($selected_bulan !== 'all' && (int) $selected_bulan === $num) ? 'selected' : ''; ?>><?php echo $name; ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit"><i class="fas fa-filter" style="margin-right:6px;"></i>Tampilkan</button>
            </div>
        </form>

        <!-- Summary Cards -->
        <div class="summary-grid">
            <div class="summary-card">
                <div class="summary-icon blue"><i class="fas fa-bullseye"></i></div>
                <div>
                    <div class="summary-label">Total Target <?php echo $selected_year; ?></div>
                    <div class="summary-value"><?php echo fmt_rp($total_target); ?></div>
                </div>
            </div>
            <div class="summary-card">
                <div class="summary-icon green"><i class="fas fa-chart-line"></i></div>
                <div>
                    <div class="summary-label">Total Realisasi
                        <?php echo ($selected_bulan !== 'all' && isset($months_array[(int) $selected_bulan])) ? 's/d ' . $months_array[(int) $selected_bulan] : 'YTD'; ?>
                    </div>
                    <div class="summary-value"><?php echo fmt_rp($total_realisasi); ?></div>
                </div>
            </div>
            <div class="summary-card">
                <div class="summary-icon <?php echo $pct_total >= 100 ? 'green' : 'orange'; ?>">
                    <i class="fas fa-percentage"></i>
                </div>
                <div>
                    <div class="summary-label">Persentase Capaian</div>
                    <div class="summary-value"
                        style="color:<?php echo $pct_total >= 100 ? '#16a34a' : ($pct_total >= 70 ? '#0ea5e9' : '#ea580c'); ?>">
                        <?php echo number_format($pct_total, 2, ',', '.'); ?>%
                    </div>
                    <div class="summary-sub">dari total anggaran target</div>
                </div>
            </div>
        </div>

        <!-- Chart -->
        <div class="chart-card">
            <h3><i class="fas fa-chart-bar" style="color:#0ea5e9;margin-right:8px;"></i>Perbandingan Target vs Realisasi
                per Jenis Pajak (dalam Juta Rp)</h3>
            <div class="chart-wrapper">
                <canvas id="pajakChart"></canvas>
            </div>
        </div>

        <!-- Table -->
        <div class="table-card">
            <h3><i class="fas fa-table" style="color:#0ea5e9;margin-right:8px;"></i>Detail Target &amp; Realisasi Tahun
                <?php echo $selected_year . $bulan_text; ?>
            </h3>
            <div style="overflow-x:auto;">
                <table class="pajak-table">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Kode</th>
                            <th>Nama Jenis Pajak</th>
                            <th class="right">Target (Rp)</th>
                            <th class="right">Realisasi
                                <?php echo ($selected_bulan !== 'all' && isset($months_array[(int) $selected_bulan])) ? 's/d ' . $months_array[(int) $selected_bulan] : 'YTD'; ?>
                                (Rp)
                            </th>
                            <th class="right">% Capaian</th>
                            <th style="min-width:120px;">Progress</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1;
                        foreach ($jenis_list as $j):
                            $t = $targets[$j['id']] ?? 0;
                            $r = $realisasi[$j['id']] ?? 0;
                            $pct = $t > 0 ? min(round($r / $t * 100, 2), 999) : 0;
                            $pct_display = number_format($pct, 2, ',', '.');
                            $bar_width = min($pct, 100);
                            $is_over = $pct >= 100;
                            ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td><span class="kode-badge"><?php echo htmlspecialchars($j['kode']); ?></span></td>
                                <td><?php echo htmlspecialchars($j['nama']); ?></td>
                                <td class="right"><?php echo fmt_rp($t); ?></td>
                                <td class="right"><?php echo fmt_rp($r); ?></td>
                                <td class="right">
                                    <span
                                        class="pct-label <?php echo $is_over ? 'over' : ($pct < 50 && $t > 0 ? 'warn' : ''); ?>">
                                        <?php echo $pct_display; ?>%
                                    </span>
                                </td>
                                <td>
                                    <div class="progress-wrap">
                                        <div class="progress-fill <?php echo $is_over ? 'over' : ''; ?>"
                                            style="width:<?php echo $bar_width; ?>%;"></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="3"><i class="fas fa-sigma" style="margin-right:6px;color:#0ea5e9;"></i>TOTAL
                            </td>
                            <td class="right"><?php echo fmt_rp($total_target); ?></td>
                            <td class="right"><?php echo fmt_rp($total_realisasi); ?></td>
                            <td class="right" colspan="2">
                                <span class="pct-label <?php echo $pct_total >= 100 ? 'over' : ''; ?>">
                                    <?php echo number_format($pct_total, 2, ',', '.'); ?>%
                                </span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

    </div>
</section>

<!-- ══ SECTION: Perbandingan Antar Tahun ══ -->
<section style="padding: 0 0 60px;">
    <div class="container">

        <!-- Divider label -->
        <div class="section-divider">
            <span class="section-divider-label">
                <i class="fas fa-calendar-alt" style="margin-right:7px;"></i>Perbandingan Antar Tahun
            </span>
        </div>

        <?php if (count($multi_years) < 2): ?>
            <div class="chart-card" style="text-align:center;padding:40px;">
                <i class="fas fa-info-circle" style="font-size:2.5rem;color:#cbd5e1;margin-bottom:12px;"></i>
                <p style="color:#64748b;font-size:15px;">Data perbandingan antar tahun baru tersedia setelah minimal
                    <strong>2 tahun</strong> data diinput.<br>Silakan isi data Target &amp; Realisasi untuk beberapa tahun
                    melalui panel admin.
                </p>
            </div>
        <?php else: ?>

            <!-- Combo Chart -->
            <div class="chart-card">
                <div
                    style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:20px;">
                    <h3 style="margin:0;"><i class="fas fa-chart-mixed" style="color:#0ea5e9;margin-right:8px;"></i>Tren
                        Target &amp; Realisasi (Miliar Rp) + % Capaian</h3>
                    <span style="font-size:12px;color:#94a3b8;">Batang: Nilai &bull; Garis: % Capaian</span>
                </div>
                <div class="chart-wrapper-tall">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>

            <!-- Year comparison table -->
            <div class="table-card">
                <h3 style="margin:0 0 20px;"><i class="fas fa-table" style="color:#0ea5e9;margin-right:8px;"></i>Ringkasan
                    Capaian Per Tahun</h3>
                <div style="overflow-x:auto;">
                    <table class="year-table">
                        <thead>
                            <tr>
                                <th>Tahun</th>
                                <th>Total Target</th>
                                <th>Total Realisasi</th>
                                <th>Selisih</th>
                                <th>% Capaian</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($multi_years as $idx => $yr):
                                $t_yr = round($my_targets[$idx] * 1e9);
                                $r_yr = round($my_real[$idx] * 1e9);
                                $p_yr = $my_pct[$idx];
                                $selisih = $r_yr - $t_yr;
                                $is_active = $yr == $selected_year;
                                ?>
                                <tr>
                                    <td>
                                        <a href="pajak_dashboard.php?tahun=<?php echo $yr; ?>" style="text-decoration:none;">
                                            <span
                                                class="year-badge <?php echo $is_active ? 'active' : ''; ?>"><?php echo $yr; ?></span>
                                        </a>
                                    </td>
                                    <td><?php echo fmt_rp($t_yr); ?></td>
                                    <td style="font-weight:600;"><?php echo fmt_rp($r_yr); ?></td>
                                    <td style="color:<?php echo $selisih >= 0 ? '#16a34a' : '#ef4444'; ?>;font-weight:600;">
                                        <?php echo ($selisih >= 0 ? '+' : '') . fmt_rp($selisih); ?>
                                    </td>
                                    <td>
                                        <span
                                            style="font-weight:800;font-size:15px;color:<?php echo $p_yr >= 100 ? '#16a34a' : ($p_yr >= 70 ? '#0ea5e9' : '#ea580c'); ?>">
                                            <?php echo number_format($p_yr, 2, ',', '.'); ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($t_yr == 0): ?>
                                            <span
                                                style="background:#f1f5f9;color:#94a3b8;padding:3px 12px;border-radius:99px;font-size:12px;font-weight:600;">Belum
                                                ada target</span>
                                        <?php elseif ($p_yr >= 100): ?>
                                            <span
                                                style="background:#dcfce7;color:#16a34a;padding:3px 12px;border-radius:99px;font-size:12px;font-weight:600;">✓
                                                Tercapai</span>
                                        <?php elseif ($p_yr >= 70): ?>
                                            <span
                                                style="background:#e0f2fe;color:#0284c7;padding:3px 12px;border-radius:99px;font-size:12px;font-weight:600;">⬤
                                                On Track</span>
                                        <?php else: ?>
                                            <span
                                                style="background:#fff7ed;color:#ea580c;padding:3px 12px;border-radius:99px;font-size:12px;font-weight:600;">⚠
                                                Perlu Perhatian</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php endif; ?>

    </div>
</section>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    // ── Chart 1: Per-jenis pajak (existing) ────────────────────────────────
    (function () {
        var labels = <?php echo bapenda_json_encode($chart_labels); ?>;
        var targets = <?php echo bapenda_json_encode($chart_target); ?>;
        var real = <?php echo bapenda_json_encode($chart_real); ?>;

        var ctx = document.getElementById('pajakChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Target (Juta Rp)',
                        data: targets,
                        backgroundColor: 'rgba(14, 165, 233, 0.25)',
                        borderColor: '#0ea5e9',
                        borderWidth: 2,
                        borderRadius: 6,
                    },
                    {
                        label: 'Realisasi YTD (Juta Rp)',
                        data: real,
                        backgroundColor: 'rgba(16, 185, 129, 0.75)',
                        borderColor: '#10b981',
                        borderWidth: 2,
                        borderRadius: 6,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return ctx.dataset.label + ': Rp ' + ctx.parsed.y.toLocaleString('id-ID', { minimumFractionDigits: 2 }) + ' Juta';
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (v) { return 'Rp ' + v.toLocaleString('id-ID') + ' Jt'; }
                        }
                    }
                }
            }
        });
    })();

    // ── Chart 2: Multi-year trend (combo bar + line) ───────────────────────
    (function () {
        var el = document.getElementById('trendChart');
        if (!el) return;

        var labels = <?php echo bapenda_json_encode($my_labels); ?>;
        var targets = <?php echo bapenda_json_encode($my_targets); ?>;
        var real = <?php echo bapenda_json_encode($my_real); ?>;
        var pct = <?php echo bapenda_json_encode($my_pct); ?>;

        new Chart(el.getContext('2d'), {
            data: {
                labels: labels,
                datasets: [
                    {
                        type: 'bar',
                        label: 'Target (Miliar Rp)',
                        data: targets,
                        backgroundColor: 'rgba(14,165,233,0.2)',
                        borderColor: '#0ea5e9',
                        borderWidth: 2,
                        borderRadius: 8,
                        yAxisID: 'yVal',
                        order: 2,
                    },
                    {
                        type: 'bar',
                        label: 'Realisasi (Miliar Rp)',
                        data: real,
                        backgroundColor: 'rgba(16,185,129,0.7)',
                        borderColor: '#10b981',
                        borderWidth: 2,
                        borderRadius: 8,
                        yAxisID: 'yVal',
                        order: 3,
                    },
                    {
                        type: 'line',
                        label: '% Capaian',
                        data: pct,
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245,158,11,0.15)',
                        borderWidth: 3,
                        pointRadius: 6,
                        pointBackgroundColor: '#f59e0b',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        yAxisID: 'yPct',
                        order: 1,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                if (ctx.dataset.yAxisID === 'yPct') {
                                    return ctx.dataset.label + ': ' + ctx.parsed.y.toLocaleString('id-ID', { minimumFractionDigits: 2 }) + '%';
                                }
                                return ctx.dataset.label + ': Rp ' + ctx.parsed.y.toLocaleString('id-ID', { minimumFractionDigits: 3 }) + ' Miliar';
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false } },
                    yVal: {
                        type: 'linear',
                        position: 'left',
                        beginAtZero: true,
                        title: { display: true, text: 'Miliar Rp', color: '#64748b', font: { size: 12 } },
                        ticks: {
                            callback: function (v) { return 'Rp ' + v.toLocaleString('id-ID') + ' M'; }
                        },
                        grid: { color: 'rgba(0,0,0,0.04)' }
                    },
                    yPct: {
                        type: 'linear',
                        position: 'right',
                        beginAtZero: true,
                        max: 150,
                        title: { display: true, text: '% Capaian', color: '#f59e0b', font: { size: 12 } },
                        ticks: {
                            color: '#f59e0b',
                            callback: function (v) { return v + '%'; }
                        },
                        grid: { drawOnChartArea: false }
                    }
                }
            }
        });
    })();
</script>

<?php require_once 'includes/footer.php'; ?>