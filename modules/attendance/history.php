<?php
require_once '../../includes/auth_check.php';
require_auth(['admin', 'guru']);

$basePath = '../../';
require_once $basePath . 'config/database.php';

$perPage   = 15;
$page      = max(1, (int) ($_GET['page'] ?? 1));
$search    = trim($_GET['q'] ?? '');
$filterDate   = $_GET['tanggal'] ?? date('Y-m-d');
$filterStatus = $_GET['status'] ?? '';
$filterKelas  = (int) ($_GET['kelas_id'] ?? 0);

$records    = [];
$totalRows  = 0;
$totalPages = 1;
$kelasList  = [];
$dbError    = null;

$statHadir  = 0;
$statIzin   = 0;
$statSakit  = 0;
$statAlfa   = 0;

try {
    $pdo = getDB();

    $kelasList = $pdo->query('SELECT id, nama_kelas FROM kelas ORDER BY tingkat, nama_kelas')->fetchAll();

    // Build WHERE
    $conditions = ['a.tanggal_absensi = ?'];
    $params     = [$filterDate];

    if ($filterStatus !== '') {
        $conditions[] = 'a.status_kehadiran = ?';
        $params[]     = $filterStatus;
    }
    if ($filterKelas > 0) {
        $conditions[] = 's.kelas_id = ?';
        $params[]     = $filterKelas;
    }
    if ($search !== '') {
        $conditions[] = '(s.nama_siswa LIKE ? OR s.nis LIKE ?)';
        $params[]     = "%$search%";
        $params[]     = "%$search%";
    }

    $where = 'WHERE ' . implode(' AND ', $conditions);

    // Count
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) FROM absensi a
        JOIN siswa s ON a.siswa_id = s.id
        $where
    ");
    $countStmt->execute($params);
    $totalRows  = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($totalRows / $perPage));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * $perPage;

    // Main query
    $stmt = $pdo->prepare("
        SELECT a.id, a.tanggal_absensi, a.waktu_scan, a.status_kehadiran, a.metode_absensi, a.keterangan,
               s.nama_siswa, s.nis, s.foto,
               k.nama_kelas,
               g.nama_guru
        FROM absensi a
        JOIN siswa s ON a.siswa_id = s.id
        LEFT JOIN kelas k ON s.kelas_id = k.id
        LEFT JOIN guru g ON a.guru_id = g.id
        $where
        ORDER BY a.waktu_scan DESC
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($params);
    $records = $stmt->fetchAll();

    // Stats for the selected date (ignoring search/status filter)
    $statsStmt = $pdo->prepare("
        SELECT status_kehadiran, COUNT(*) as total
        FROM absensi
        WHERE tanggal_absensi = ?
        GROUP BY status_kehadiran
    ");
    $statsStmt->execute([$filterDate]);
    foreach ($statsStmt->fetchAll() as $row) {
        match($row['status_kehadiran']) {
            'hadir'  => $statHadir  = (int) $row['total'],
            'izin'   => $statIzin   = (int) $row['total'],
            'sakit'  => $statSakit  = (int) $row['total'],
            'alfa'   => $statAlfa   = (int) $row['total'],
            default  => null,
        };
    }

} catch (PDOException $e) {
    $dbError = $e->getMessage();
}

$totalHari = $statHadir + $statIzin + $statSakit + $statAlfa;

$statusConfig = [
    'hadir' => ['label' => 'Hadir',  'bg' => 'bg-green-50 text-green-700',  'dot' => 'bg-green-500'],
    'izin'  => ['label' => 'Izin',   'bg' => 'bg-blue-50 text-blue-700',    'dot' => 'bg-blue-500'],
    'sakit' => ['label' => 'Sakit',  'bg' => 'bg-yellow-50 text-yellow-700','dot' => 'bg-yellow-500'],
    'alfa'  => ['label' => 'Alfa',   'bg' => 'bg-red-50 text-red-600',      'dot' => 'bg-red-500'],
];

$pageTitle = 'Log Kehadiran';
require_once $basePath . 'includes/header.php';
?>

<div class="flex h-screen overflow-hidden bg-gray-50">

    <?php require_once $basePath . 'includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

        <?php require_once $basePath . 'includes/navbar.php'; ?>

        <main class="flex-1 p-6 space-y-6 overflow-y-auto">

            <!-- Page Header -->
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Log Kehadiran</h2>
                    <p class="text-gray-400 text-sm mt-0.5">Riwayat absensi siswa berdasarkan tanggal dan kelas</p>
                </div>
                <div class="text-sm text-gray-500 bg-white border border-gray-200 rounded-xl px-4 py-2.5 font-medium">
                    <i class="bi bi-calendar3 me-1.5 text-primary"></i>
                    <?= date('l, d F Y', strtotime($filterDate)) ?>
                </div>
            </div>

            <?php if ($dbError): ?>
            <div class="px-4 py-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
                <p class="font-semibold mb-1"><i class="bi bi-database-exclamation me-1"></i> Database belum siap</p>
                <p class="text-xs text-red-400 mt-1 font-mono"><?= htmlspecialchars($dbError) ?></p>
            </div>
            <?php endif; ?>

            <!-- Stat Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">

                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 flex items-center gap-3">
                    <div class="w-11 h-11 rounded-full bg-green-50 flex items-center justify-center text-green-500 text-xl shrink-0">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Hadir</p>
                        <p class="text-2xl font-bold text-gray-800"><?= $statHadir ?></p>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 flex items-center gap-3">
                    <div class="w-11 h-11 rounded-full bg-blue-50 flex items-center justify-center text-blue-500 text-xl shrink-0">
                        <i class="bi bi-file-earmark-text-fill"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Izin</p>
                        <p class="text-2xl font-bold text-gray-800"><?= $statIzin ?></p>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 flex items-center gap-3">
                    <div class="w-11 h-11 rounded-full bg-yellow-50 flex items-center justify-center text-yellow-500 text-xl shrink-0">
                        <i class="bi bi-bandaid-fill"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Sakit</p>
                        <p class="text-2xl font-bold text-gray-800"><?= $statSakit ?></p>
                    </div>
                </div>

                <div class="bg-primary rounded-2xl shadow-sm p-5 flex items-center gap-3">
                    <div class="w-11 h-11 rounded-full bg-white/20 flex items-center justify-center text-white text-xl shrink-0">
                        <i class="bi bi-x-circle-fill"></i>
                    </div>
                    <div>
                        <p class="text-xs text-white/80 font-medium uppercase tracking-wide">Alfa</p>
                        <p class="text-2xl font-bold text-white"><?= $statAlfa ?></p>
                    </div>
                </div>

            </div>

            <!-- Table Card -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">

                <!-- Filter bar -->
                <div class="px-5 py-4 border-b border-gray-100">
                    <form method="GET" class="flex flex-wrap items-center gap-2">

                        <!-- Date -->
                        <input type="date" name="tanggal" value="<?= htmlspecialchars($filterDate) ?>"
                               class="px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition">

                        <!-- Status -->
                        <select name="status"
                                class="px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition">
                            <option value="">Semua Status</option>
                            <option value="hadir"  <?= $filterStatus === 'hadir'  ? 'selected' : '' ?>>Hadir</option>
                            <option value="izin"   <?= $filterStatus === 'izin'   ? 'selected' : '' ?>>Izin</option>
                            <option value="sakit"  <?= $filterStatus === 'sakit'  ? 'selected' : '' ?>>Sakit</option>
                            <option value="alfa"   <?= $filterStatus === 'alfa'   ? 'selected' : '' ?>>Alfa</option>
                        </select>

                        <!-- Kelas -->
                        <select name="kelas_id"
                                class="px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition">
                            <option value="">Semua Kelas</option>
                            <?php foreach ($kelasList as $kls): ?>
                            <option value="<?= $kls['id'] ?>" <?= $filterKelas === (int)$kls['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($kls['nama_kelas']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>

                        <!-- Search -->
                        <div class="relative flex-1 min-w-40">
                            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                                   placeholder="Cari nama atau NIS..."
                                   class="w-full pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition">
                        </div>

                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-lg hover:bg-primary/90 transition">
                            Filter
                        </button>
                        <?php if ($search || $filterStatus || $filterKelas || $filterDate !== date('Y-m-d')): ?>
                        <a href="history.php" class="px-3 py-2 text-sm text-gray-500 hover:text-gray-700 transition">Reset</a>
                        <?php endif; ?>

                    </form>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-xs text-gray-400 uppercase tracking-wide bg-gray-50/60 border-b border-gray-100">
                                <th class="px-5 py-3 text-left font-medium">Siswa</th>
                                <th class="px-5 py-3 text-left font-medium">NIS</th>
                                <th class="px-5 py-3 text-left font-medium">Kelas</th>
                                <th class="px-5 py-3 text-left font-medium">Status</th>
                                <th class="px-5 py-3 text-left font-medium">Metode</th>
                                <th class="px-5 py-3 text-left font-medium">Jam Masuk</th>
                                <th class="px-5 py-3 text-left font-medium">Dicatat Oleh</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">

                            <?php if (empty($records)): ?>
                            <tr>
                                <td colspan="7" class="px-5 py-14 text-center text-gray-300">
                                    <i class="bi bi-journal-x text-4xl block mb-2"></i>
                                    <?= $search || $filterStatus || $filterKelas
                                        ? 'Tidak ada data yang cocok dengan filter.'
                                        : 'Belum ada absensi tercatat untuk tanggal ini.' ?>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($records as $r): ?>
                            <?php $sc = $statusConfig[$r['status_kehadiran']] ?? ['label' => $r['status_kehadiran'], 'bg' => 'bg-gray-100 text-gray-600', 'dot' => 'bg-gray-400']; ?>
                            <tr class="hover:bg-gray-50/60 transition">

                                <!-- Siswa -->
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <?php if ($r['foto']): ?>
                                        <img src="/absensi/uploads/students/<?= htmlspecialchars($r['foto']) ?>"
                                             class="w-9 h-9 rounded-full object-cover border border-gray-200 shrink-0" alt="">
                                        <?php else: ?>
                                        <div class="w-9 h-9 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold text-sm shrink-0">
                                            <?= mb_strtoupper(mb_substr($r['nama_siswa'], 0, 1)) ?>
                                        </div>
                                        <?php endif; ?>
                                        <span class="font-semibold text-gray-800"><?= htmlspecialchars($r['nama_siswa']) ?></span>
                                    </div>
                                </td>

                                <!-- NIS -->
                                <td class="px-5 py-3.5 text-gray-500 font-mono text-xs">
                                    <?= htmlspecialchars($r['nis']) ?>
                                </td>

                                <!-- Kelas -->
                                <td class="px-5 py-3.5">
                                    <?php if ($r['nama_kelas']): ?>
                                    <span class="inline-block px-2.5 py-1 text-xs font-semibold text-primary bg-blue-50 rounded-full">
                                        <?= htmlspecialchars($r['nama_kelas']) ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-gray-300 text-xs">—</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Status -->
                                <td class="px-5 py-3.5">
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold rounded-full <?= $sc['bg'] ?>">
                                        <span class="w-1.5 h-1.5 rounded-full <?= $sc['dot'] ?>"></span>
                                        <?= $sc['label'] ?>
                                    </span>
                                </td>

                                <!-- Metode -->
                                <td class="px-5 py-3.5">
                                    <?php if ($r['metode_absensi'] === 'barcode'): ?>
                                    <span class="inline-flex items-center gap-1 text-xs text-gray-500">
                                        <i class="bi bi-qr-code-scan"></i> Scan QR
                                    </span>
                                    <?php else: ?>
                                    <span class="inline-flex items-center gap-1 text-xs text-gray-500">
                                        <i class="bi bi-pencil-square"></i> Manual
                                    </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Jam Masuk -->
                                <td class="px-5 py-3.5">
                                    <span class="font-semibold text-gray-700">
                                        <?= $r['waktu_scan'] ? date('H:i', strtotime($r['waktu_scan'])) : '—' ?>
                                    </span>
                                    <span class="text-xs text-gray-400 ms-0.5">WIB</span>
                                </td>

                                <!-- Guru -->
                                <td class="px-5 py-3.5 text-gray-500 text-xs">
                                    <?= $r['nama_guru'] ? htmlspecialchars($r['nama_guru']) : '<span class="italic text-gray-300">Sistem</span>' ?>
                                </td>

                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>

                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="px-5 py-4 border-t border-gray-100 flex items-center justify-between gap-4 flex-wrap">
                    <p class="text-sm text-gray-400">
                        Menampilkan <span class="font-medium text-gray-600"><?= number_format(count($records), 0, ',', '.') ?></span>
                        dari <span class="font-medium text-gray-600"><?= number_format($totalRows, 0, ',', '.') ?></span> catatan
                        &nbsp;·&nbsp; Total hari ini: <span class="font-semibold text-gray-700"><?= $totalHari ?></span> siswa
                    </p>
                    <?php if ($totalPages > 1): ?>
                    <?php
                    $queryBase = http_build_query(array_filter([
                        'tanggal'  => $filterDate !== date('Y-m-d') ? $filterDate : null,
                        'status'   => $filterStatus,
                        'kelas_id' => $filterKelas ?: null,
                        'q'        => $search,
                    ]));
                    $sep = $queryBase ? '&' : '';
                    ?>
                    <div class="flex items-center gap-1">
                        <a href="?<?= $queryBase . $sep ?>page=<?= max(1, $page - 1) ?>"
                           class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-100 transition <?= $page <= 1 ? 'opacity-40 pointer-events-none' : '' ?>">
                            <i class="bi bi-chevron-left text-xs"></i>
                        </a>
                        <?php
                        $pStart = max(1, $page - 2);
                        $pEnd   = min($totalPages, $pStart + 4);
                        $pStart = max(1, $pEnd - 4);
                        for ($p = $pStart; $p <= $pEnd; $p++):
                        ?>
                        <a href="?<?= $queryBase . $sep ?>page=<?= $p ?>"
                           class="w-9 h-9 flex items-center justify-center rounded-lg text-sm font-medium transition
                                  <?= $p === $page ? 'bg-primary text-white border border-primary' : 'border border-gray-200 text-gray-600 hover:bg-gray-100' ?>">
                            <?= $p ?>
                        </a>
                        <?php endfor; ?>
                        <a href="?<?= $queryBase . $sep ?>page=<?= min($totalPages, $page + 1) ?>"
                           class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-100 transition <?= $page >= $totalPages ? 'opacity-40 pointer-events-none' : '' ?>">
                            <i class="bi bi-chevron-right text-xs"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

            </div>

        </main>
    </div>
</div>

<?php require_once $basePath . 'includes/footer.php'; ?>
