<?php
/**
 * Helper koneksi realtime read-only ke database PostgreSQL SINPELAJA.
 *
 * Seluruh mapping kode rekening dan rumus perhitungan Target/Realisasi diselaraskan
 * dengan App\Services\PadDashboardService pada portal internal form_pbb2.
 */

/**
 * Mengambil konfigurasi SINPELAJA dari global $sinpelaja_config (config.php).
 */
function sinpelaja_get_config(?array $override = null): array
{
    global $sinpelaja_config;
    $cfg = is_array($override) ? $override : (isset($sinpelaja_config) && is_array($sinpelaja_config) ? $sinpelaja_config : []);

    return array_merge([
        'enabled'      => false,
        'host'         => '',
        'port'         => 5432,
        'dbname'       => '',
        'user'         => '',
        'password'     => '',
        'sslmode'      => 'prefer',
        'timeout'      => 6,
        'statement_ms' => 8000,
    ], $cfg);
}

/**
 * Mengecek kesiapan ekstensi PHP pdo_pgsql dan kelengkapan konfigurasi SINPELAJA.
 */
function sinpelaja_check_readiness(?array $cfg = null): array
{
    $c = sinpelaja_get_config($cfg);
    $hasExt = extension_loaded('pdo_pgsql');
    $enabled = !empty($c['enabled']);
    $configured = $enabled
        && trim((string) $c['host']) !== ''
        && trim((string) $c['dbname']) !== ''
        && trim((string) $c['user']) !== '';

    $reason = '';
    if (!$hasExt) {
        $reason = 'Ekstensi PHP pdo_pgsql belum aktif pada server web.';
    } elseif (!$enabled) {
        $reason = 'Integrasi SINPELAJA belum diaktifkan di config.php ($sinpelaja_config[\'enabled\'] = false).';
    } elseif (!$configured) {
        $reason = 'Konfigurasi host, dbname, atau user SINPELAJA di config.php belum lengkap.';
    }

    return [
        'has_extension' => $hasExt,
        'enabled'       => $enabled,
        'configured'    => $configured,
        'ready'         => $hasExt && $configured,
        'reason'        => $reason,
        'host'          => (string) $c['host'],
        'port'          => (int) ($c['port'] ?: 5432),
        'dbname'        => (string) $c['dbname'],
    ];
}

/**
 * Membuka koneksi PDO PostgreSQL ke SINPELAJA dengan timeout aman.
 * Mengembalikan null (dan mengisi $errorReason) jika ekstensi tidak tersedia,
 * konfigurasi nonaktif/kosong, atau koneksi gagal.
 */
function sinpelaja_get_pdo(?array $override = null, ?string &$errorReason = null): ?PDO
{
    $cfg = sinpelaja_get_config($override);
    $status = sinpelaja_check_readiness($cfg);
    if (!$status['ready']) {
        $errorReason = $status['reason'];
        return null;
    }

    $allowedSsl = ['disable', 'allow', 'prefer', 'require', 'verify-ca', 'verify-full'];
    $sslmode = in_array((string) $cfg['sslmode'], $allowedSsl, true) ? (string) $cfg['sslmode'] : 'prefer';
    $port = max(1, min(65535, (int) ($cfg['port'] ?: 5432)));
    $timeout = max(2, min(30, (int) ($cfg['timeout'] ?: 6)));
    $stmtMs = max(1000, min(30000, (int) ($cfg['statement_ms'] ?: 8000)));

    $dsn = sprintf(
        'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s;connect_timeout=%d',
        trim((string) $cfg['host']),
        $port,
        trim((string) $cfg['dbname']),
        $sslmode,
        $timeout
    );

    try {
        $pdo = new PDO($dsn, (string) $cfg['user'], (string) $cfg['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT            => $timeout,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET statement_timeout = {$stmtMs}");
        $pdo->exec("SET search_path TO public, pad, pbb, bphtb, retribusi");
        return $pdo;
    } catch (Throwable $e) {
        // Jangan pernah menyertakan DSN penuh atau kredensial pada pesan diagnostik
        $sqlState = ($e instanceof PDOException && $e->getCode()) ? (string) $e->getCode() : '';
        $errorReason = 'Gagal terhubung ke database SINPELAJA' . ($sqlState !== '' ? " (kode: {$sqlState})" : '') . '.';
        return null;
    }
}

/**
 * Tes koneksi singkat untuk halaman admin (mengembalikan status, latency ms, dan pesan aman).
 */
function sinpelaja_test_connection(?array $override = null): array
{
    $readiness = sinpelaja_check_readiness($override);
    if (!$readiness['ready']) {
        return array_merge($readiness, [
            'connected'  => false,
            'latency_ms' => null,
            'message'    => $readiness['reason'],
        ]);
    }

    $t0 = microtime(true);
    $err = null;
    $pdo = sinpelaja_get_pdo($override, $err);
    if (!$pdo) {
        return array_merge($readiness, [
            'connected'  => false,
            'latency_ms' => null,
            'message'    => $err ?: 'Koneksi SINPELAJA gagal.',
        ]);
    }

    try {
        $pdo->query('SELECT 1')->fetchColumn();
        $ms = round((microtime(true) - $t0) * 1000, 1);
        return array_merge($readiness, [
            'connected'  => true,
            'latency_ms' => $ms,
            'message'    => "Terhubung ke SINPELAJA ({$ms} ms).",
        ]);
    } catch (Throwable $e) {
        return array_merge($readiness, [
            'connected'  => false,
            'latency_ms' => null,
            'message'    => 'Koneksi terbuka namun query uji gagal.',
        ]);
    }
}

/**
 * Daftar referensi jenis pajak/pendapatan kanonik (urut tampil pada chart & tabel).
 */
function sinpelaja_get_jenis_catalog(): array
{
    return [
        ['kode' => '4.1.01.09',    'nama' => 'PAJAK REKLAME',                                         'core' => true],
        ['kode' => '4.1.01.12',    'nama' => 'PAJAK AIR TANAH',                                       'core' => true],
        ['kode' => '4.1.01.13',    'nama' => 'PAJAK SARANG BURUNG WALET',                             'core' => true],
        ['kode' => '4.1.01.14',    'nama' => 'PAJAK MINERAL BUKAN LOGAM DAN BATUAN',                  'core' => true],
        ['kode' => '4.1.01.15',    'nama' => 'PAJAK BUMI DAN BANGUNAN PERDESAAN DAN PERKOTAAN (PBBP2)', 'core' => true],
        ['kode' => '4.1.01.16',    'nama' => 'BEA PEROLEHAN HAK ATAS TANAH DAN BANGUNAN (BPHTB)',     'core' => true],
        ['kode' => '4.1.01.19.01', 'nama' => 'PBJT-MAKANAN DAN/ATAU MINUMAN',                         'core' => true],
        ['kode' => '4.1.01.19.02', 'nama' => 'PBJT-TENAGA LISTRIK',                                   'core' => true],
        ['kode' => '4.1.01.19.03', 'nama' => 'PBJT-JASA PERHOTELAN',                                  'core' => true],
        ['kode' => '4.1.01.19.04', 'nama' => 'PBJT-JASA PARKIR',                                      'core' => true],
        ['kode' => '4.1.01.19.05', 'nama' => 'PBJT-JASA KESENIAN DAN HIBURAN',                        'core' => true],
        ['kode' => '4.1.01.20',    'nama' => 'OPSEN PAJAK KENDARAAN BERMOTOR (PKB)',                  'core' => true],
        ['kode' => '4.1.01.21',    'nama' => 'OPSEN BEA BALIK NAMA KENDARAAN BERMOTOR (BBNKB)',       'core' => true],
        ['kode' => '4.1.02.02',    'nama' => 'RETRIBUSI JASA USAHA',                                  'core' => false],
        ['kode' => '4.1.04.12',    'nama' => 'PENDAPATAN DENDA PAJAK DAERAH',                         'core' => false],
    ];
}

/**
 * Memetakan kd_rek_join sub-rekening di SINPELAJA ke 15 kode kelompok dashboard.
 */
function sinpelaja_map_rekening(string $kdRekJoin): ?string
{
    static $prefixes = [
        '4.1.01.19.01', '4.1.01.19.02', '4.1.01.19.03', '4.1.01.19.04', '4.1.01.19.05',
        '4.1.01.09', '4.1.01.12', '4.1.01.13', '4.1.01.14', '4.1.01.15',
        '4.1.01.16', '4.1.01.20', '4.1.01.21', '4.1.02.02', '4.1.04.12',
    ];

    $kd = trim($kdRekJoin);
    foreach ($prefixes as $pref) {
        if (strpos($kd, $pref) === 0) {
            return $pref;
        }
    }
    return null;
}

/**
 * Batas tanggal inklusif-awal & eksklusif-akhir untuk filter tahun dan kumulatif s/d bulan.
 */
function sinpelaja_date_bounds(int $year, $bulan = 'all'): array
{
    $start = sprintf('%04d-01-01', $year);
    if ($bulan === 'all' || $bulan === '' || $bulan === null) {
        $end = sprintf('%04d-01-01', $year + 1);
    } else {
        $b = max(1, min(12, (int) $bulan));
        $dt = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $b));
        $end = $dt->modify('+1 month')->format('Y-m-d');
    }
    return [$start, $end];
}

/**
 * Mengambil seluruh data dashboard pajak secara realtime dari SINPELAJA untuk tahun & filter bulan.
 * Melempar Throwable jika query gagal sehingga pemanggil dapat melakukan fallback aman ke MySQL.
 */
function sinpelaja_fetch_dashboard_bundle(PDO $pdo, int $requestedYear, $bulan = 'all'): array
{
    // 1. Daftar tahun tersedia dari pad.target_pajak (sangat cepat ~100 baris) + rentang historis 5 tahun
    $targetYearsRaw = $pdo->query(
        "SELECT DISTINCT CAST(TRIM(thn_pajak) AS INT) AS yr
         FROM pad.target_pajak
         WHERE TRIM(thn_pajak) ~ '^[0-9]{4}$'
         ORDER BY yr DESC"
    )->fetchAll(PDO::FETCH_COLUMN);

    $currentYear = (int) date('Y');
    $yearsMap = [];
    foreach ($targetYearsRaw as $y) {
        $yearsMap[(int) $y] = true;
    }
    $yearsMap[$currentYear] = true;

    $maxYear = !empty($yearsMap) ? max(array_keys($yearsMap)) : $currentYear;
    $trendStartYear = $maxYear - 4;
    for ($y = $maxYear; $y >= $trendStartYear; $y--) {
        $yearsMap[$y] = true;
    }
    $years = array_keys($yearsMap);
    rsort($years);

    $selectedYear = $requestedYear > 0 ? $requestedYear : $currentYear;
    if (!in_array($selectedYear, $years, true) && !empty($years)) {
        // Tetap hormati tahun yang diminta jika berada di rentang masuk akal (mis. 2020-2030),
        // hanya masukkan ke daftar agar select tetap konsisten.
        if ($selectedYear >= 2015 && $selectedYear <= $currentYear + 2) {
            $years[] = $selectedYear;
            rsort($years);
        } else {
            $selectedYear = $years[0];
        }
    }

    // 2. Hitung target untuk $selectedYear
    $catalog = sinpelaja_get_jenis_catalog();
    $targetsByKode = [];
    $realisasiByKode = [];
    foreach ($catalog as $c) {
        $targetsByKode[$c['kode']] = 0.0;
        $realisasiByKode[$c['kode']] = 0.0;
    }

    // Aturan SINPELAJA: gunakan target_perubahan bila > 0, jika 0 gunakan target_penyusunan
    $stmtTarget = $pdo->prepare(
        "SELECT kd_rek_join,
                SUM(CASE WHEN COALESCE(target_perubahan, 0) > 0
                         THEN target_perubahan
                         ELSE COALESCE(target_penyusunan, 0) END) AS target_amount
         FROM pad.target_pajak
         WHERE TRIM(thn_pajak) = :tahun
         GROUP BY kd_rek_join"
    );
    $stmtTarget->execute([':tahun' => (string) $selectedYear]);

    $totalTargetAll = 0.0;
    $unmappedTarget = 0.0;
    foreach ($stmtTarget->fetchAll() as $row) {
        $amt = (float) $row['target_amount'];
        $totalTargetAll += $amt;
        $mapped = sinpelaja_map_rekening((string) $row['kd_rek_join']);
        if ($mapped !== null) {
            $targetsByKode[$mapped] += $amt;
        } else {
            $unmappedTarget += $amt;
        }
    }

    // 3. Hitung realisasi pada rentang [1 Jan $selectedYear .. akhir $bulan / akhir tahun]
    [$startDate, $endDate] = sinpelaja_date_bounds($selectedYear, $bulan);

    // 3a. pad.setor -> Reklame, Air Tanah, Walet, MBLB, PBJT 5 sektor
    $stmtSetor = $pdo->prepare(
        "SELECT kd_rek_join, COALESCE(SUM(jml_pokok), 0) AS total
         FROM pad.setor
         WHERE tgl_pembayaran >= :start AND tgl_pembayaran < :end
         GROUP BY kd_rek_join"
    );
    $stmtSetor->execute([':start' => $startDate, ':end' => $endDate]);
    foreach ($stmtSetor->fetchAll() as $row) {
        $mapped = sinpelaja_map_rekening((string) $row['kd_rek_join']);
        if ($mapped !== null) {
            $realisasiByKode[$mapped] += (float) $row['total'];
        }
    }

    // 3b. pbb.pembayaran_sppt -> Pokok PBB (4.1.01.15) & Denda Pajak (4.1.04.12)
    $stmtPbb = $pdo->prepare(
        "SELECT COALESCE(SUM(jml_yg_dibayar - COALESCE(denda, 0)), 0) AS pokok,
                COALESCE(SUM(COALESCE(denda, 0)), 0) AS denda
         FROM pbb.pembayaran_sppt
         WHERE tgl_pembayaran >= :start AND tgl_pembayaran < :end"
    );
    $stmtPbb->execute([':start' => $startDate, ':end' => $endDate]);
    $pbbRow = $stmtPbb->fetch() ?: ['pokok' => 0, 'denda' => 0];
    $realisasiByKode['4.1.01.15'] += (float) $pbbRow['pokok'];
    $realisasiByKode['4.1.04.12'] += (float) $pbbRow['denda'];

    // 3c. bphtb.pembayaran -> BPHTB (4.1.01.16)
    $stmtBphtb = $pdo->prepare(
        "SELECT COALESCE(SUM(jml_yg_dibayar), 0) AS total
         FROM bphtb.pembayaran
         WHERE tgl_pembayaran >= :start AND tgl_pembayaran < :end"
    );
    $stmtBphtb->execute([':start' => $startDate, ':end' => $endDate]);
    $realisasiByKode['4.1.01.16'] += (float) $stmtBphtb->fetchColumn();

    // 3d. pad.setor_lainnya -> Opsen PKB (4.1.01.20) & Opsen BBNKB (4.1.01.21)
    $stmtLainnya = $pdo->prepare(
        "SELECT kd_rek_join, COALESCE(SUM(jml_yg_dibayar), 0) AS total
         FROM pad.setor_lainnya
         WHERE tgl_pembayaran >= :start AND tgl_pembayaran < :end
         GROUP BY kd_rek_join"
    );
    $stmtLainnya->execute([':start' => $startDate, ':end' => $endDate]);
    foreach ($stmtLainnya->fetchAll() as $row) {
        $kd = (string) $row['kd_rek_join'];
        if (strpos($kd, '4.1.01.20') === 0) {
            $realisasiByKode['4.1.01.20'] += (float) $row['total'];
        } elseif (strpos($kd, '4.1.01.21') === 0) {
            $realisasiByKode['4.1.01.21'] += (float) $row['total'];
        }
    }

    // 3e. retribusi.setor -> Retribusi Jasa Usaha (4.1.02.02)
    $stmtRet = $pdo->prepare(
        "SELECT COALESCE(SUM(jml_yg_dibayar), 0) AS total
         FROM retribusi.setor
         WHERE tgl_pembayaran >= :start AND tgl_pembayaran < :end
           AND kd_rek_join LIKE '4.1.02.02%'"
    );
    $stmtRet->execute([':start' => $startDate, ':end' => $endDate]);
    $realisasiByKode['4.1.02.02'] += (float) $stmtRet->fetchColumn();

    // Susun jenis_list final (13 jenis pajak inti selalu tampil; non-core & selisih PAD tampil bila > 0)
    $jenisList = [];
    $targets = [];
    $realisasi = [];
    foreach ($catalog as $item) {
        $kd = $item['kode'];
        $tVal = $targetsByKode[$kd] ?? 0.0;
        $rVal = $realisasiByKode[$kd] ?? 0.0;
        if (!$item['core'] && $tVal <= 0 && $rVal <= 0) {
            continue;
        }
        $jenisList[] = ['id' => $kd, 'kode' => $kd, 'nama' => $item['nama']];
        $targets[$kd] = $tVal;
        $realisasi[$kd] = $rVal;
    }

    if ($unmappedTarget > 0) {
        $jenisList[] = ['id' => '4.1.04.27', 'kode' => '4.1.04.27', 'nama' => 'LAIN-LAIN PAD YANG SAH LAINNYA'];
        $targets['4.1.04.27'] = $unmappedTarget;
        $realisasi['4.1.04.27'] = 0.0;
    }

    $totalTarget = $totalTargetAll;
    $totalRealisasi = array_sum($realisasi);
    $pctTotal = $totalTarget > 0 ? round(($totalRealisasi / $totalTarget) * 100, 2) : 0.0;

    // 4. Data Perbandingan Multi-Tahun (5 tahun terakhir s/d $maxYear) dalam 6 query batch
    $trendEndYear = max($maxYear, $selectedYear);
    $trendStartYear = $trendEndYear - 4;
    $trendYears = range($trendStartYear, $trendEndYear);
    $myTargetsByYr = array_fill_keys($trendYears, 0.0);
    $myRealByYr = array_fill_keys($trendYears, 0.0);

    $placeholders = [];
    $yrParams = [];
    foreach ($trendYears as $idx => $yr) {
        $ph = ':yr' . $idx;
        $placeholders[] = $ph;
        $yrParams[$ph] = (string) $yr;
    }

    $stmtMyT = $pdo->prepare(
        "SELECT CAST(TRIM(thn_pajak) AS INT) AS yr,
                SUM(CASE WHEN COALESCE(target_perubahan, 0) > 0
                         THEN target_perubahan
                         ELSE COALESCE(target_penyusunan, 0) END) AS total
         FROM pad.target_pajak
         WHERE TRIM(thn_pajak) IN (" . implode(',', $placeholders) . ")
         GROUP BY CAST(TRIM(thn_pajak) AS INT)"
    );
    $stmtMyT->execute($yrParams);
    foreach ($stmtMyT->fetchAll() as $row) {
        $yr = (int) $row['yr'];
        if (isset($myTargetsByYr[$yr])) {
            $myTargetsByYr[$yr] = (float) $row['total'];
        }
    }

    $myStart = sprintf('%04d-01-01', $trendStartYear);
    $myEnd = sprintf('%04d-01-01', $trendEndYear + 1);
    $rangeParams = [':start' => $myStart, ':end' => $myEnd];

    $stmtMySetor = $pdo->prepare(
        "SELECT EXTRACT(YEAR FROM tgl_pembayaran)::INT AS yr, kd_rek_join, COALESCE(SUM(jml_pokok), 0) AS total
         FROM pad.setor
         WHERE tgl_pembayaran >= :start AND tgl_pembayaran < :end
         GROUP BY 1, 2"
    );
    $stmtMySetor->execute($rangeParams);
    foreach ($stmtMySetor->fetchAll() as $row) {
        $yr = (int) $row['yr'];
        if (isset($myRealByYr[$yr]) && sinpelaja_map_rekening((string) $row['kd_rek_join']) !== null) {
            $myRealByYr[$yr] += (float) $row['total'];
        }
    }

    $stmtMyPbb = $pdo->prepare(
        "SELECT EXTRACT(YEAR FROM tgl_pembayaran)::INT AS yr, COALESCE(SUM(jml_yg_dibayar), 0) AS total
         FROM pbb.pembayaran_sppt
         WHERE tgl_pembayaran >= :start AND tgl_pembayaran < :end
         GROUP BY 1"
    );
    $stmtMyPbb->execute($rangeParams);
    foreach ($stmtMyPbb->fetchAll() as $row) {
        $yr = (int) $row['yr'];
        if (isset($myRealByYr[$yr])) {
            $myRealByYr[$yr] += (float) $row['total'];
        }
    }

    $stmtMyBphtb = $pdo->prepare(
        "SELECT EXTRACT(YEAR FROM tgl_pembayaran)::INT AS yr, COALESCE(SUM(jml_yg_dibayar), 0) AS total
         FROM bphtb.pembayaran
         WHERE tgl_pembayaran >= :start AND tgl_pembayaran < :end
         GROUP BY 1"
    );
    $stmtMyBphtb->execute($rangeParams);
    foreach ($stmtMyBphtb->fetchAll() as $row) {
        $yr = (int) $row['yr'];
        if (isset($myRealByYr[$yr])) {
            $myRealByYr[$yr] += (float) $row['total'];
        }
    }

    $stmtMyLainnya = $pdo->prepare(
        "SELECT EXTRACT(YEAR FROM tgl_pembayaran)::INT AS yr, kd_rek_join, COALESCE(SUM(jml_yg_dibayar), 0) AS total
         FROM pad.setor_lainnya
         WHERE tgl_pembayaran >= :start AND tgl_pembayaran < :end
         GROUP BY 1, 2"
    );
    $stmtMyLainnya->execute($rangeParams);
    foreach ($stmtMyLainnya->fetchAll() as $row) {
        $yr = (int) $row['yr'];
        $kd = (string) $row['kd_rek_join'];
        if (isset($myRealByYr[$yr]) && (strpos($kd, '4.1.01.20') === 0 || strpos($kd, '4.1.01.21') === 0)) {
            $myRealByYr[$yr] += (float) $row['total'];
        }
    }

    $stmtMyRet = $pdo->prepare(
        "SELECT EXTRACT(YEAR FROM tgl_pembayaran)::INT AS yr, COALESCE(SUM(jml_yg_dibayar), 0) AS total
         FROM retribusi.setor
         WHERE tgl_pembayaran >= :start AND tgl_pembayaran < :end
           AND kd_rek_join LIKE '4.1.02.02%'
         GROUP BY 1"
    );
    $stmtMyRet->execute($rangeParams);
    foreach ($stmtMyRet->fetchAll() as $row) {
        $yr = (int) $row['yr'];
        if (isset($myRealByYr[$yr])) {
            $myRealByYr[$yr] += (float) $row['total'];
        }
    }

    $multiYears = [];
    $myLabels = [];
    $myTargets = [];
    $myReal = [];
    $myPct = [];
    foreach ($trendYears as $yr) {
        $totT = $myTargetsByYr[$yr];
        $totR = $myRealByYr[$yr];
        $pctYr = $totT > 0 ? round(($totR / $totT) * 100, 2) : 0.0;
        $multiYears[] = (int) $yr;
        $myLabels[] = (string) $yr;
        $myTargets[] = round($totT / 1e9, 3);
        $myReal[] = round($totR / 1e9, 3);
        $myPct[] = $pctYr;
    }

    return [
        'source'          => 'sinpelaja',
        'years'           => $years,
        'selected_year'   => $selectedYear,
        'jenis_list'      => $jenisList,
        'targets'         => $targets,
        'realisasi'       => $realisasi,
        'total_target'    => $totalTarget,
        'total_realisasi' => $totalRealisasi,
        'pct_total'       => $pctTotal,
        'multi_years'     => $multiYears,
        'my_labels'       => $myLabels,
        'my_targets'      => $myTargets,
        'my_real'         => $myReal,
        'my_pct'          => $myPct,
        'fetched_at'      => date('Y-m-d H:i:s'),
    ];
}

/**
 * Merender HTML banner status integrasi SINPELAJA untuk panel admin
 * (Target Pajak & Realisasi Pajak). Tidak menampilkan password/kredensial.
 */
function sinpelaja_admin_status_banner(?array $override = null): string
{
    $st = sinpelaja_test_connection($override);
    if ($st['connected']) {
        $bg = '#ecfdf5';
        $bd = '#a7f3d0';
        $fg = '#065f46';
        $icon = 'fa-check-circle';
        $title = 'Integrasi Realtime SINPELAJA Aktif';
        $desc = sprintf(
            'Terhubung ke PostgreSQL %s (%s:%d, latensi %s ms). Dashboard pajak publik otomatis menampilkan data realtime SINPELAJA; data di halaman ini berfungsi sebagai cadangan (fallback) otomatis.',
            htmlspecialchars($st['dbname'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($st['host'], ENT_QUOTES, 'UTF-8'),
            (int) $st['port'],
            htmlspecialchars((string) $st['latency_ms'], ENT_QUOTES, 'UTF-8')
        );
    } else {
        $bg = '#fffbeb';
        $bd = '#fde68a';
        $fg = '#92400e';
        $icon = 'fa-exclamation-triangle';
        $title = 'Integrasi SINPELAJA Tidak Aktif (Mode Portal Lokal)';
        $desc = htmlspecialchars($st['message'], ENT_QUOTES, 'UTF-8')
            . ' Dashboard publik saat ini menampilkan data MySQL lokal dari halaman ini. Periksa ekstensi <code>pdo_pgsql</code> &amp; <code>$sinpelaja_config</code> di <code>config.php</code>.';
    }

    return '<div style="margin-bottom:20px;padding:14px 18px;border-radius:12px;background:' . $bg . ';border:1px solid ' . $bd . ';color:' . $fg . ';display:flex;align-items:flex-start;gap:12px;font-size:13.5px;line-height:1.5;">'
        . '<i class="fas ' . $icon . '" style="font-size:18px;margin-top:2px;flex-shrink:0;"></i>'
        . '<div><strong style="display:block;margin-bottom:2px;">' . $title . '</strong>' . $desc . '</div>'
        . '</div>';
}

