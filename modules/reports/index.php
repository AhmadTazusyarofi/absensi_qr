<?php
require_once '../../includes/auth_check.php';
require_auth(['admin', 'guru']);

$basePath = '../../';
require_once $basePath . 'config/database.php';

$bulanIndo = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

$bulan       = max(1, min(12, (int) ($_GET['bulan'] ?? date('n'))));
$tahun       = max(2020, min(2099, (int) ($_GET['tahun'] ?? date('Y'))));
$filterKelas = (int) ($_GET['kelas_id'] ?? 0);

$kelasList   = [];
$statHadir   = 0;
$statIzin    = 0;
$statSakit   = 0;
$statAlfa    = 0;
$hariEfektif = 0;
$perKelas    = [];
$dailyData   = [];
$topAlfa     = [];
$dbError     = null;

try {
    $pdo = getDB();

    $kelasList = $pdo->query('SELECT id, nama_kelas FROM kelas ORDER BY tingkat, nama_kelas')->fetchAll();

    // Base condition
    $baseWhere  = 'MONTH(a.tanggal_absensi) = ? AND YEAR(a.tanggal_absensi) = ?';
    $baseParams = [$bulan, $tahun];
    if ($filterKelas > 0) {
        $baseWhere  .= ' AND s.kelas_id = ?';
        $baseParams[] = $filterKelas;
    }

    // Summary stats
    $statsStmt = $pdo->prepare("
        SELECT a.status_kehadiran, COUNT(*) AS total
        FROM absensi a JOIN siswa s ON a.siswa_id = s.id
        WHERE $baseWhere GROUP BY a.status_kehadiran
    ");
    $statsStmt->execute($baseParams);
    foreach ($statsStmt->fetchAll() as $row) {
        match($row['status_kehadiran']) {
            'hadir' => $statHadir = (int)$row['total'],
            'izin'  => $statIzin  = (int)$row['total'],
            'sakit' => $statSakit = (int)$row['total'],
            'alfa'  => $statAlfa  = (int)$row['total'],
            default => null,
        };
    }

    // Hari efektif
    $hariStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT a.tanggal_absensi)
        FROM absensi a JOIN siswa s ON a.siswa_id = s.id
        WHERE $baseWhere
    ");
    $hariStmt->execute($baseParams);
    $hariEfektif = (int) $hariStmt->fetchColumn();

    // Per kelas summary
    $kelasWhere  = 'MONTH(a.tanggal_absensi) = ? AND YEAR(a.tanggal_absensi) = ?';
    $kelasParams = [$bulan, $tahun];
    if ($filterKelas > 0) {
        $kelasWhere  .= ' AND k.id = ?';
        $kelasParams[] = $filterKelas;
    }
    $kelasStmt = $pdo->prepare("
        SELECT k.id, k.nama_kelas, k.tingkat,
               COUNT(DISTINCT s.id) AS total_siswa,
               SUM(CASE WHEN a.status_kehadiran = 'hadir' THEN 1 ELSE 0 END) AS hadir,
               SUM(CASE WHEN a.status_kehadiran = 'izin'  THEN 1 ELSE 0 END) AS izin,
               SUM(CASE WHEN a.status_kehadiran = 'sakit' THEN 1 ELSE 0 END) AS sakit,
               SUM(CASE WHEN a.status_kehadiran = 'alfa'  THEN 1 ELSE 0 END) AS alfa,
               COUNT(a.id) AS total_absensi
        FROM kelas k
        LEFT JOIN siswa s ON s.kelas_id = k.id AND s.status = 'aktif'
        LEFT JOIN absensi a ON a.siswa_id = s.id AND $kelasWhere
        GROUP BY k.id, k.nama_kelas, k.tingkat
        ORDER BY k.tingkat, k.nama_kelas
    ");
    $kelasStmt->execute($kelasParams);
    $perKelas = $kelasStmt->fetchAll();

    // Daily trend for chart
    $dailyStmt = $pdo->prepare("
        SELECT a.tanggal_absensi,
               SUM(CASE WHEN a.status_kehadiran = 'hadir' THEN 1 ELSE 0 END) AS hadir,
               SUM(CASE WHEN a.status_kehadiran IN ('izin','sakit','alfa') THEN 1 ELSE 0 END) AS tidak_hadir
        FROM absensi a JOIN siswa s ON a.siswa_id = s.id
        WHERE $baseWhere
        GROUP BY a.tanggal_absensi ORDER BY a.tanggal_absensi
    ");
    $dailyStmt->execute($baseParams);
    $dailyData = $dailyStmt->fetchAll();

    // Top alfa siswa
    $alfaStmt = $pdo->prepare("
        SELECT s.nama_siswa, s.nis, s.foto, k.nama_kelas, COUNT(*) AS total_alfa
        FROM absensi a
        JOIN siswa s ON a.siswa_id = s.id
        LEFT JOIN kelas k ON s.kelas_id = k.id
        WHERE a.status_kehadiran = 'alfa' AND $baseWhere
        GROUP BY s.id ORDER BY total_alfa DESC LIMIT 8
    ");
    $alfaStmt->execute($baseParams);
    $topAlfa = $alfaStmt->fetchAll();

} catch (PDOException $e) {
    $dbError = $e->getMessage();
}

$totalCatat  = $statHadir + $statIzin + $statSakit + $statAlfa;
$pctHadir    = $totalCatat > 0 ? round(($statHadir / $totalCatat) * 100, 1) : 0;

// Chart data
$chartLabels = array_map(fn($r) => date('d/m', strtotime($r['tanggal_absensi'])), $dailyData);
$chartHadir  = array_map(fn($r) => (int)$r['hadir'],       $dailyData);
$chartAbsen  = array_map(fn($r) => (int)$r['tidak_hadir'], $dailyData);

$pageTitle = 'Laporan Kehadiran';
require_once $basePath . 'includes/header.php';
?>

<div class="flex h-screen overflow-hidden bg-gray-50">

    <?php require_once $basePath . 'includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

        <?php require_once $basePath . 'includes/navbar.php'; ?>

        <main class="flex-1 p-6 space-y-6 overflow-y-auto" id="print-area">

            <!-- Header -->
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Laporan Kehadiran</h2>
                    <p class="text-gray-400 text-sm mt-0.5">
                        Rekap absensi <?= $bulanIndo[$bulan] . ' ' . $tahun ?>
                        <?= $filterKelas ? '· ' . htmlspecialchars(collect($kelasList, $filterKelas)) : '' ?>
                    </p>
                </div>
                <button onclick="window.print()"
                        class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition no-print">
                    <i class="bi bi-printer"></i> Cetak Laporan
                </button>
            </div>

            <?php if ($dbError): ?>
            <div class="px-4 py-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
                <p class="font-semibold mb-1"><i class="bi bi-database-exclamation me-1"></i> Database belum siap</p>
                <p class="text-xs text-red-400 mt-1 font-mono"><?= htmlspecialchars($dbError) ?></p>
            </div>
            <?php endif; ?>

            <!-- Filter -->
            <form method="GET" class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4 flex flex-wrap items-center gap-3 no-print">
                <div>
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1">Bulan</label>
                    <select name="bulan"
                            class="px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $m === $bulan ? 'selected' : '' ?>><?= $bulanIndo[$m] ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1">Tahun</label>
                    <select name="tahun"
                            class="px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition">
                        <?php for ($y = date('Y') + 1; $y >= 2023; $y--): ?>
                        <option value="<?= $y ?>" <?= $y === $tahun ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1">Kelas</label>
                    <select name="kelas_id"
                            class="px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition">
                        <option value="">Semua Kelas</option>
                        <?php foreach ($kelasList as $kls): ?>
                        <option value="<?= $kls['id'] ?>" <?= $filterKelas === (int)$kls['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($kls['nama_kelas']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit"
                            class="px-5 py-2 text-sm font-semibold text-white bg-primary rounded-lg hover:bg-primary/90 transition">
                        <i class="bi bi-funnel me-1"></i> Tampilkan
                    </button>
                    <?php if ($filterKelas || $bulan != date('n') || $tahun != date('Y')): ?>
                    <a href="index.php" class="px-3 py-2 text-sm text-gray-500 hover:text-gray-700 transition">Reset</a>
                    <?php endif; ?>
                </div>
            </form>

            <!-- Stat Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">

                <div class="bg-primary rounded-2xl shadow-sm p-5">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs text-white/80 font-medium uppercase tracking-wide">Kehadiran</p>
                        <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center">
                            <i class="bi bi-check-circle-fill text-white text-base"></i>
                        </div>
                    </div>
                    <p class="text-3xl font-bold text-white"><?= number_format($statHadir, 0, ',', '.') ?></p>
                    <p class="text-xs text-white/60 mt-1"><?= $pctHadir ?>% dari total</p>
                </div>

                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Izin</p>
                        <div class="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center">
                            <i class="bi bi-file-earmark-text-fill text-blue-500 text-base"></i>
                        </div>
                    </div>
                    <p class="text-3xl font-bold text-gray-800"><?= number_format($statIzin, 0, ',', '.') ?></p>
                    <p class="text-xs text-gray-400 mt-1">catatan izin</p>
                </div>

                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Sakit</p>
                        <div class="w-9 h-9 rounded-xl bg-yellow-50 flex items-center justify-center">
                            <i class="bi bi-bandaid-fill text-yellow-500 text-base"></i>
                        </div>
                    </div>
                    <p class="text-3xl font-bold text-gray-800"><?= number_format($statSakit, 0, ',', '.') ?></p>
                    <p class="text-xs text-gray-400 mt-1">catatan sakit</p>
                </div>

                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Alfa</p>
                        <div class="w-9 h-9 rounded-xl bg-red-50 flex items-center justify-center">
                            <i class="bi bi-x-circle-fill text-red-500 text-base"></i>
                        </div>
                    </div>
                    <p class="text-3xl font-bold text-gray-800"><?= number_format($statAlfa, 0, ',', '.') ?></p>
                    <p class="text-xs text-gray-400 mt-1">tanpa keterangan</p>
                </div>

            </div>

            <!-- Chart + Summary mini -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- Chart -->
                <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-bold text-gray-800">Tren Kehadiran Harian</h3>
                        <span class="text-xs text-gray-400"><?= $bulanIndo[$bulan] . ' ' . $tahun ?></span>
                    </div>
                    <?php if (empty($dailyData)): ?>
                    <div class="flex flex-col items-center justify-center py-12 text-gray-300">
                        <i class="bi bi-bar-chart-line text-4xl mb-2"></i>
                        <p class="text-sm">Belum ada data pada periode ini</p>
                    </div>
                    <?php else: ?>
                    <canvas id="trendChart" height="120"></canvas>
                    <?php endif; ?>
                </div>

                <!-- Summary box -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 flex flex-col justify-between">
                    <div>
                        <h3 class="font-bold text-gray-800 mb-4">Ringkasan Periode</h3>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-500">Total Catatan</span>
                                <span class="font-bold text-gray-800"><?= number_format($totalCatat, 0, ',', '.') ?></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-500">Hari Efektif</span>
                                <span class="font-bold text-gray-800"><?= $hariEfektif ?> hari</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-500">Rata-rata/Hari</span>
                                <span class="font-bold text-gray-800">
                                    <?= $hariEfektif > 0 ? round($statHadir / $hariEfektif) : 0 ?> siswa
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Percentage bar -->
                    <div class="mt-6">
                        <div class="flex justify-between text-xs text-gray-400 mb-1.5">
                            <span>Tingkat Kehadiran</span>
                            <span class="font-semibold text-gray-700"><?= $pctHadir ?>%</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2.5 overflow-hidden">
                            <div class="h-full rounded-full bg-primary transition-all duration-500"
                                 style="width: <?= $pctHadir ?>%"></div>
                        </div>
                        <div class="flex justify-between text-xs text-gray-400 mt-2">
                            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-primary inline-block"></span> Hadir</span>
                            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-red-400 inline-block"></span> Tidak Hadir</span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Per Kelas Table -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="font-bold text-gray-800">Rekap Per Kelas</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-xs text-gray-400 uppercase tracking-wide bg-gray-50/60 border-b border-gray-100">
                                <th class="px-5 py-3 text-left font-medium">Kelas</th>
                                <th class="px-5 py-3 text-center font-medium">Siswa Aktif</th>
                                <th class="px-5 py-3 text-center font-medium text-green-600">Hadir</th>
                                <th class="px-5 py-3 text-center font-medium text-blue-600">Izin</th>
                                <th class="px-5 py-3 text-center font-medium text-yellow-600">Sakit</th>
                                <th class="px-5 py-3 text-center font-medium text-red-600">Alfa</th>
                                <th class="px-5 py-3 text-left font-medium">Kehadiran</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php if (empty($perKelas)): ?>
                            <tr>
                                <td colspan="7" class="px-5 py-10 text-center text-gray-300">
                                    <i class="bi bi-inbox text-3xl block mb-2"></i>Belum ada data kelas.
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($perKelas as $kls): ?>
                            <?php
                                $tot = (int)$kls['total_absensi'];
                                $pct = $tot > 0 ? round(((int)$kls['hadir'] / $tot) * 100) : 0;
                                $pctColor = $pct >= 80 ? 'bg-green-500' : ($pct >= 60 ? 'bg-yellow-400' : 'bg-red-400');
                            ?>
                            <tr class="hover:bg-gray-50/60 transition">
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center text-primary shrink-0">
                                            <i class="bi bi-mortarboard text-sm"></i>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-800"><?= htmlspecialchars($kls['nama_kelas']) ?></p>
                                            <p class="text-xs text-gray-400">Kelas <?= $kls['tingkat'] ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-3.5 text-center font-semibold text-gray-700"><?= $kls['total_siswa'] ?></td>
                                <td class="px-5 py-3.5 text-center font-semibold text-green-600"><?= (int)$kls['hadir'] ?></td>
                                <td class="px-5 py-3.5 text-center font-semibold text-blue-600"><?= (int)$kls['izin'] ?></td>
                                <td class="px-5 py-3.5 text-center font-semibold text-yellow-600"><?= (int)$kls['sakit'] ?></td>
                                <td class="px-5 py-3.5 text-center font-semibold text-red-600"><?= (int)$kls['alfa'] ?></td>
                                <td class="px-5 py-3.5 w-40">
                                    <?php if ($tot > 0): ?>
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 bg-gray-100 rounded-full h-2 overflow-hidden">
                                            <div class="h-full rounded-full <?= $pctColor ?>" style="width: <?= $pct ?>%"></div>
                                        </div>
                                        <span class="text-xs font-semibold text-gray-600 w-9 text-right"><?= $pct ?>%</span>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-xs text-gray-300">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Top Alfa -->
            <?php if (!empty($topAlfa)): ?>
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill text-red-400"></i>
                    <h3 class="font-bold text-gray-800">Siswa Paling Sering Alfa</h3>
                    <span class="text-xs text-gray-400 ml-1">— <?= $bulanIndo[$bulan] . ' ' . $tahun ?></span>
                </div>
                <div class="p-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <?php foreach ($topAlfa as $i => $s): ?>
                    <div class="flex items-center gap-3 p-3 rounded-xl border border-gray-100 hover:border-red-100 hover:bg-red-50/30 transition">
                        <div class="relative shrink-0">
                            <?php if ($s['foto']): ?>
                            <img src="/absensi/uploads/students/<?= htmlspecialchars($s['foto']) ?>"
                                 class="w-10 h-10 rounded-full object-cover border border-gray-200" alt="">
                            <?php else: ?>
                            <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center text-red-500 font-bold text-sm">
                                <?= mb_strtoupper(mb_substr($s['nama_siswa'], 0, 1)) ?>
                            </div>
                            <?php endif; ?>
                            <span class="absolute -top-1 -left-1 w-4.5 h-4.5 rounded-full bg-red-500 text-white text-xs font-bold flex items-center justify-center" style="width:18px;height:18px;font-size:10px"><?= $i + 1 ?></span>
                        </div>
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-800 text-sm truncate"><?= htmlspecialchars($s['nama_siswa']) ?></p>
                            <p class="text-xs text-gray-400"><?= htmlspecialchars($s['nama_kelas'] ?? '—') ?></p>
                        </div>
                        <div class="ml-auto shrink-0 text-right">
                            <p class="text-lg font-bold text-red-500"><?= $s['total_alfa'] ?></p>
                            <p class="text-xs text-gray-400">hari</p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        </main>
    </div>
</div>

<style>
@media print {
    .no-print, #sidebar, nav { display: none !important; }
    .flex.h-screen { display: block !important; }
    main { overflow: visible !important; padding: 0 !important; }
}
</style>

<?php if (!empty($dailyData)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('trendChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [
            {
                label: 'Hadir',
                data: <?= json_encode($chartHadir) ?>,
                backgroundColor: 'rgba(59,130,246,0.85)',
                borderRadius: 4,
                borderSkipped: false,
            },
            {
                label: 'Tidak Hadir',
                data: <?= json_encode($chartAbsen) ?>,
                backgroundColor: 'rgba(239,68,68,0.7)',
                borderRadius: 4,
                borderSkipped: false,
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'top', labels: { font: { size: 12 }, boxWidth: 12 } },
            tooltip: { mode: 'index', intersect: false }
        },
        scales: {
            x: { grid: { display: false }, ticks: { font: { size: 11 } } },
            y: { beginAtZero: true, grid: { color: '#f3f4f6' }, ticks: { stepSize: 1, font: { size: 11 } } }
        }
    }
});
</script>
<?php endif; ?>

<?php require_once $basePath . 'includes/footer.php'; ?>
