<?php
require_once '../../includes/auth_check.php';
require_auth(['admin', 'guru']);

$basePath = '../../';
require_once $basePath . 'config/database.php';

$today      = date('Y-m-d');
$filterDate = $_GET['tanggal'] ?? $today;
$filterKelas = (int) ($_GET['kelas_id'] ?? 0);

$kelasList = [];
$students  = [];
$dbError   = null;
$flash     = '';

$guruId = null;

try {
    $pdo = getDB();

    // Resolve guru_id from session user
    $gStmt = $pdo->prepare('SELECT id FROM guru WHERE user_id = ?');
    $gStmt->execute([$_SESSION['user_id']]);
    $guruRow = $gStmt->fetch();
    $guruId  = $guruRow ? (int) $guruRow['id'] : null;

    $kelasList = $pdo->query('SELECT id, nama_kelas FROM kelas ORDER BY tingkat, nama_kelas')->fetchAll();

    // ── POST: save one student's manual attendance ──────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'manual_save') {
        $siswaId    = (int) ($_POST['siswa_id'] ?? 0);
        $tanggal    = $_POST['tanggal'] ?? $today;
        $status     = $_POST['status_kehadiran'] ?? '';
        $keterangan = trim($_POST['keterangan'] ?? '');

        $allowed = ['izin', 'sakit', 'alfa'];
        if ($siswaId && in_array($status, $allowed, true)) {
            // Upsert: update if exists, insert if not
            $chk = $pdo->prepare('SELECT id FROM absensi WHERE siswa_id = ? AND tanggal_absensi = ?');
            $chk->execute([$siswaId, $tanggal]);
            $existing = $chk->fetch();

            if ($existing) {
                $pdo->prepare('UPDATE absensi SET status_kehadiran=?, metode_absensi="manual", keterangan=?, guru_id=? WHERE id=?')
                    ->execute([$status, $keterangan ?: null, $guruId, $existing['id']]);
            } else {
                $pdo->prepare('INSERT INTO absensi (siswa_id, guru_id, tanggal_absensi, waktu_scan, status_kehadiran, metode_absensi, keterangan) VALUES (?,?,?,?,?,?,?)')
                    ->execute([$siswaId, $guruId, $tanggal, date('H:i:s'), $status, 'manual', $keterangan ?: null]);
            }
        }

        $qs = http_build_query(['tanggal' => $tanggal, 'kelas_id' => (int)($_POST['kelas_id'] ?? 0), 'saved' => 1]);
        header("Location: manual.php?$qs"); exit;
    }

    // ── Load students ───────────────────────────────────────────────────────
    if ($filterKelas > 0) {
        $stmt = $pdo->prepare("
            SELECT s.id, s.nama_siswa, s.nis, s.foto, s.jenis_kelamin,
                   k.nama_kelas,
                   a.id AS absensi_id, a.status_kehadiran, a.metode_absensi, a.waktu_scan, a.keterangan
            FROM siswa s
            LEFT JOIN kelas k ON s.kelas_id = k.id
            LEFT JOIN absensi a ON a.siswa_id = s.id AND a.tanggal_absensi = ?
            WHERE s.kelas_id = ? AND s.status = 'aktif'
            ORDER BY s.nama_siswa ASC
        ");
        $stmt->execute([$filterDate, $filterKelas]);
        $students = $stmt->fetchAll();
    }

} catch (PDOException $e) {
    $dbError = $e->getMessage();
}

if (isset($_GET['saved'])) $flash = 'success:Absensi manual berhasil disimpan.';

$statusCfg = [
    'hadir' => ['label' => 'Hadir',  'bg' => 'bg-green-100 text-green-700',  'dot' => 'bg-green-500'],
    'izin'  => ['label' => 'Izin',   'bg' => 'bg-blue-100 text-blue-700',    'dot' => 'bg-blue-500'],
    'sakit' => ['label' => 'Sakit',  'bg' => 'bg-yellow-100 text-yellow-700','dot' => 'bg-yellow-500'],
    'alfa'  => ['label' => 'Alfa',   'bg' => 'bg-red-100 text-red-600',      'dot' => 'bg-red-500'],
];

$totalHadir = 0; $totalIzin = 0; $totalSakit = 0; $totalAlfa = 0; $totalBelum = 0;
foreach ($students as $s) {
    match ($s['status_kehadiran'] ?? null) {
        'hadir' => $totalHadir++,
        'izin'  => $totalIzin++,
        'sakit' => $totalSakit++,
        'alfa'  => $totalAlfa++,
        default => $totalBelum++,
    };
}

$pageTitle = 'Absensi Manual';
require_once $basePath . 'includes/header.php';
?>

<div class="flex h-screen overflow-hidden bg-gray-50">

    <?php require_once $basePath . 'includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

        <?php require_once $basePath . 'includes/navbar.php'; ?>

        <main class="flex-1 p-6 space-y-6 overflow-y-auto">

            <!-- Header -->
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Absensi Manual</h2>
                    <p class="text-gray-400 text-sm mt-0.5">Input kehadiran izin, sakit, dan alfa per siswa</p>
                </div>
                <a href="scan.php"
                   class="flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-primary bg-blue-50 rounded-xl hover:bg-blue-100 transition shrink-0">
                    <i class="bi bi-qr-code-scan"></i> Pemindai QR
                </a>
            </div>

            <?php if ($flash): ?>
            <?php [$ftype, $fmsg] = explode(':', $flash, 2); ?>
            <div class="px-4 py-3 rounded-xl text-sm flex items-center gap-2 bg-green-50 border border-green-200 text-green-700">
                <i class="bi bi-check-circle"></i> <?= htmlspecialchars($fmsg) ?>
            </div>
            <?php endif; ?>

            <?php if ($dbError): ?>
            <div class="px-4 py-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
                <i class="bi bi-database-exclamation me-1"></i> <?= htmlspecialchars($dbError) ?>
            </div>
            <?php endif; ?>

            <!-- Filter -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                <form method="GET" class="flex flex-wrap items-end gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">Tanggal</label>
                        <input type="date" name="tanggal" value="<?= htmlspecialchars($filterDate) ?>"
                               class="px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widests mb-1.5">Kelas</label>
                        <select name="kelas_id" class="px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition min-w-44">
                            <option value="0">— Pilih kelas —</option>
                            <?php foreach ($kelasList as $k): ?>
                            <option value="<?= $k['id'] ?>" <?= $filterKelas === (int)$k['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($k['nama_kelas']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit"
                            class="px-5 py-2.5 text-sm font-semibold text-white bg-primary rounded-xl hover:bg-primary/90 transition">
                        <i class="bi bi-funnel me-1"></i> Tampilkan
                    </button>
                </form>
            </div>

            <?php if ($filterKelas > 0 && !empty($students)): ?>

            <!-- Stat chips -->
            <div class="flex flex-wrap gap-3">
                <div class="flex items-center gap-2 px-4 py-2 bg-white rounded-xl border border-gray-100 shadow-sm text-sm">
                    <span class="w-2.5 h-2.5 rounded-full bg-green-500"></span>
                    <span class="font-semibold text-gray-700"><?= $totalHadir ?></span>
                    <span class="text-gray-400">Hadir</span>
                </div>
                <div class="flex items-center gap-2 px-4 py-2 bg-white rounded-xl border border-gray-100 shadow-sm text-sm">
                    <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                    <span class="font-semibold text-gray-700"><?= $totalIzin ?></span>
                    <span class="text-gray-400">Izin</span>
                </div>
                <div class="flex items-center gap-2 px-4 py-2 bg-white rounded-xl border border-gray-100 shadow-sm text-sm">
                    <span class="w-2.5 h-2.5 rounded-full bg-yellow-500"></span>
                    <span class="font-semibold text-gray-700"><?= $totalSakit ?></span>
                    <span class="text-gray-400">Sakit</span>
                </div>
                <div class="flex items-center gap-2 px-4 py-2 bg-white rounded-xl border border-gray-100 shadow-sm text-sm">
                    <span class="w-2.5 h-2.5 rounded-full bg-red-500"></span>
                    <span class="font-semibold text-gray-700"><?= $totalAlfa ?></span>
                    <span class="text-gray-400">Alfa</span>
                </div>
                <div class="flex items-center gap-2 px-4 py-2 bg-white rounded-xl border border-gray-100 shadow-sm text-sm">
                    <span class="w-2.5 h-2.5 rounded-full bg-gray-300"></span>
                    <span class="font-semibold text-gray-700"><?= $totalBelum ?></span>
                    <span class="text-gray-400">Belum dicatat</span>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="font-bold text-gray-800 text-sm uppercase tracking-wide">
                        Daftar Siswa
                        <span class="ml-2 px-2 py-0.5 text-xs font-semibold text-primary bg-blue-50 rounded-full"><?= count($students) ?> siswa</span>
                    </h3>
                </div>

                <div class="divide-y divide-gray-50">
                    <?php foreach ($students as $s):
                        $status   = $s['status_kehadiran'] ?? null;
                        $cfg      = $statusCfg[$status] ?? null;
                        $isHadir  = $status === 'hadir';
                    ?>
                    <div class="px-5 py-4 flex items-start gap-4 flex-wrap hover:bg-gray-50/60 transition">

                        <!-- Avatar + name -->
                        <div class="flex items-center gap-3 min-w-48 flex-1">
                            <?php if ($s['foto']): ?>
                            <img src="/absensi/uploads/students/<?= htmlspecialchars($s['foto']) ?>"
                                 class="w-10 h-10 rounded-full object-cover border border-gray-200 shrink-0" alt="">
                            <?php else: ?>
                            <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold text-sm shrink-0">
                                <?= mb_strtoupper(mb_substr($s['nama_siswa'], 0, 1)) ?>
                            </div>
                            <?php endif; ?>
                            <div>
                                <p class="font-semibold text-gray-800 text-sm leading-tight"><?= htmlspecialchars($s['nama_siswa']) ?></p>
                                <p class="text-xs text-gray-400 font-mono"><?= htmlspecialchars($s['nis']) ?></p>
                            </div>
                        </div>

                        <!-- Current status badge -->
                        <div class="flex items-center min-w-28">
                            <?php if ($cfg): ?>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-full <?= $cfg['bg'] ?>">
                                <span class="w-1.5 h-1.5 rounded-full <?= $cfg['dot'] ?>"></span>
                                <?= $cfg['label'] ?>
                                <?php if ($s['metode_absensi'] === 'manual'): ?>
                                <span class="opacity-60">(manual)</span>
                                <?php elseif ($s['metode_absensi'] === 'barcode'): ?>
                                <i class="bi bi-qr-code opacity-60 text-xs"></i>
                                <?php endif; ?>
                            </span>
                            <?php else: ?>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-full bg-gray-100 text-gray-400">
                                <span class="w-1.5 h-1.5 rounded-full bg-gray-300"></span>
                                Belum dicatat
                            </span>
                            <?php endif; ?>
                        </div>

                        <!-- Manual input form -->
                        <?php if (!$isHadir): ?>
                        <form method="POST" class="flex items-center gap-2 flex-wrap">
                            <input type="hidden" name="_action" value="manual_save">
                            <input type="hidden" name="siswa_id" value="<?= $s['id'] ?>">
                            <input type="hidden" name="tanggal" value="<?= htmlspecialchars($filterDate) ?>">
                            <input type="hidden" name="kelas_id" value="<?= $filterKelas ?>">

                            <select name="status_kehadiran" required
                                    class="px-3 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                                <option value="" disabled <?= !$status ? 'selected' : '' ?>>— Pilih —</option>
                                <option value="izin"  <?= $status === 'izin'  ? 'selected' : '' ?>>Izin</option>
                                <option value="sakit" <?= $status === 'sakit' ? 'selected' : '' ?>>Sakit</option>
                                <option value="alfa"  <?= $status === 'alfa'  ? 'selected' : '' ?>>Alfa</option>
                            </select>

                            <input type="text" name="keterangan"
                                   value="<?= htmlspecialchars($s['keterangan'] ?? '') ?>"
                                   placeholder="Keterangan (opsional)"
                                   class="px-3 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition w-48">

                            <button type="submit"
                                    class="px-4 py-2 text-sm font-semibold text-white bg-primary rounded-xl hover:bg-primary/90 transition shrink-0">
                                <i class="bi bi-check-lg me-1"></i> Simpan
                            </button>
                        </form>
                        <?php else: ?>
                        <p class="text-xs text-gray-400 italic self-center">
                            <i class="bi bi-qr-code me-1"></i>
                            Hadir via scan pukul <?= $s['waktu_scan'] ? date('H:i', strtotime($s['waktu_scan'])) : '—' ?>
                        </p>
                        <?php endif; ?>

                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php elseif ($filterKelas > 0 && empty($students)): ?>
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-6 py-14 text-center text-gray-300">
                <i class="bi bi-people text-4xl block mb-2"></i>
                <p class="text-sm">Tidak ada siswa aktif di kelas ini.</p>
            </div>
            <?php else: ?>
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-6 py-14 text-center text-gray-300">
                <i class="bi bi-filter-circle text-4xl block mb-2"></i>
                <p class="text-sm">Pilih tanggal dan kelas untuk mulai mencatat absensi.</p>
            </div>
            <?php endif; ?>

        </main>
    </div>
</div>

<?php require_once $basePath . 'includes/footer.php'; ?>
