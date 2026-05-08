<?php
require_once '../../includes/auth_check.php';
require_auth(['orang_tua']);

$basePath = '../../';
require_once $basePath . 'config/database.php';

$bulanIndo = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

try {
    $pdo = getDB();

    // Get linked student
    $otStmt = $pdo->prepare('
        SELECT ot.*, s.id AS siswa_id, s.nama_siswa, s.nis, s.foto, s.no_hp, s.alamat, s.jenis_kelamin,
               k.nama_kelas, k.tingkat, k.wali_kelas_id,
               g.nama_guru AS wali_kelas_nama, g.no_hp AS wali_kelas_hp
        FROM orang_tua ot
        JOIN siswa s ON ot.siswa_id = s.id
        LEFT JOIN kelas k ON s.kelas_id = k.id
        LEFT JOIN guru g ON k.wali_kelas_id = g.id
        WHERE ot.pengguna_id = ?
    ');
    $otStmt->execute([$_SESSION['user_id']]);
    $data = $otStmt->fetch();

    if (!$data) {
        // No student linked — show error page
        $pageTitle = 'Pantauan Anak';
        require_once $basePath . 'includes/header.php';
        echo '<div class="flex items-center justify-center min-h-screen bg-gray-50">
                <div class="text-center p-8">
                    <i class="bi bi-exclamation-triangle text-5xl text-yellow-400 block mb-4"></i>
                    <h2 class="text-xl font-bold text-gray-700 mb-2">Akun Belum Terhubung</h2>
                    <p class="text-gray-400 text-sm">Akun Anda belum dihubungkan ke data siswa.<br>Hubungi administrator sekolah.</p>
                </div>
              </div>';
        require_once $basePath . 'includes/footer.php';
        exit;
    }

    $siswaId = $data['siswa_id'];
    $today   = date('Y-m-d');
    $bulan   = (int) date('n');
    $tahun   = (int) date('Y');

    // Today's attendance
    $todayStmt = $pdo->prepare('SELECT * FROM absensi WHERE siswa_id = ? AND tanggal_absensi = ? LIMIT 1');
    $todayStmt->execute([$siswaId, $today]);
    $todayAbsen = $todayStmt->fetch();

    // This month stats
    $monthStmt = $pdo->prepare('
        SELECT status_kehadiran, COUNT(*) AS total
        FROM absensi WHERE siswa_id = ? AND MONTH(tanggal_absensi) = ? AND YEAR(tanggal_absensi) = ?
        GROUP BY status_kehadiran
    ');
    $monthStmt->execute([$siswaId, $bulan, $tahun]);
    $monthStats = ['hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alfa' => 0];
    foreach ($monthStmt->fetchAll() as $row) {
        $monthStats[$row['status_kehadiran']] = (int) $row['total'];
    }
    $monthTotal  = array_sum($monthStats);
    $monthPct    = $monthTotal > 0 ? round(($monthStats['hadir'] / $monthTotal) * 100, 1) : 0;

    // Last month percentage (for comparison)
    $lmBulan = $bulan === 1 ? 12 : $bulan - 1;
    $lmTahun = $bulan === 1 ? $tahun - 1 : $tahun;
    $lmStmt  = $pdo->prepare('SELECT COUNT(*) AS tot, SUM(status_kehadiran="hadir") AS hadir FROM absensi WHERE siswa_id = ? AND MONTH(tanggal_absensi) = ? AND YEAR(tanggal_absensi) = ?');
    $lmStmt->execute([$siswaId, $lmBulan, $lmTahun]);
    $lm       = $lmStmt->fetch();
    $lmPct    = ($lm && $lm['tot'] > 0) ? round(($lm['hadir'] / $lm['tot']) * 100, 1) : null;
    $pctDiff  = $lmPct !== null ? round($monthPct - $lmPct, 1) : null;

    // Semester stats (academic year: July–June)
    $semStart = $bulan >= 7 ? "$tahun-07-01" : ($tahun - 1) . "-07-01";
    $semStmt  = $pdo->prepare('
        SELECT status_kehadiran, COUNT(*) AS total
        FROM absensi WHERE siswa_id = ? AND tanggal_absensi >= ?
        GROUP BY status_kehadiran
    ');
    $semStmt->execute([$siswaId, $semStart]);
    $semStats = ['hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alfa' => 0];
    foreach ($semStmt->fetchAll() as $row) {
        $semStats[$row['status_kehadiran']] = (int) $row['total'];
    }
    $semAbsen = $semStats['alfa'] + $semStats['sakit'];

    // Recent activity (last 8 records)
    $recentStmt = $pdo->prepare('
        SELECT a.tanggal_absensi, a.waktu_scan, a.status_kehadiran, a.metode_absensi, a.keterangan
        FROM absensi a WHERE a.siswa_id = ?
        ORDER BY a.tanggal_absensi DESC, a.waktu_scan DESC LIMIT 8
    ');
    $recentStmt->execute([$siswaId]);
    $recentRecords = $recentStmt->fetchAll();

} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

$statusCfg = [
    'hadir' => ['label' => 'HADIR',  'bg' => 'bg-green-100 text-green-700',  'dot' => 'bg-green-500'],
    'izin'  => ['label' => 'IZIN',   'bg' => 'bg-blue-100 text-blue-700',    'dot' => 'bg-blue-500'],
    'sakit' => ['label' => 'SAKIT',  'bg' => 'bg-yellow-100 text-yellow-700','dot' => 'bg-yellow-500'],
    'alfa'  => ['label' => 'ABSEN',  'bg' => 'bg-red-100 text-red-600',      'dot' => 'bg-red-500'],
];

$hariIndo = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];

$pageTitle = 'Pantauan Anak';
require_once $basePath . 'includes/header.php';
?>

<div class="flex h-screen overflow-hidden bg-gray-50">

    <?php require_once $basePath . 'includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

        <?php require_once $basePath . 'includes/navbar.php'; ?>

        <main class="flex-1 p-6 overflow-y-auto space-y-6">

            <!-- Student profile + monthly card -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- Profile card -->
                <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                    <div class="flex flex-col items-center text-center sm:flex-row sm:items-start sm:text-left gap-5">

                        <!-- Avatar -->
                        <div class="relative shrink-0">
                            <?php if ($data['foto']): ?>
                            <img src="/absensi/uploads/students/<?= htmlspecialchars($data['foto']) ?>"
                                class="w-20 h-20 rounded-full object-cover border-2 border-gray-100 shadow-sm" alt="">
                            <?php else: ?>
                            <div
                                class="w-20 h-20 rounded-full bg-primary/10 flex items-center justify-center text-primary text-3xl font-bold shadow-sm">
                                <?= mb_strtoupper(mb_substr($data['nama_siswa'], 0, 1)) ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Info -->
                        <div class="flex-1 min-w-0">
                            <div
                                class="flex flex-col items-center gap-2 sm:flex-row sm:items-start sm:justify-between sm:gap-3 sm:flex-wrap">
                                <div>
                                    <h2 class="text-xl font-bold text-gray-800">
                                        <?= htmlspecialchars($data['nama_siswa']) ?></h2>
                                    <p class="text-sm text-gray-400 mt-0.5">
                                        <?= $data['nama_kelas'] ? htmlspecialchars($data['nama_kelas']) : 'Kelas belum ditentukan' ?>
                                        <?= $data['nis'] ? ' · NIS ' . htmlspecialchars($data['nis']) : '' ?>
                                    </p>
                                </div>
                                <?php if ($todayAbsen): ?>
                                <span
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold bg-green-50 text-green-700 border border-green-200 rounded-full">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                                    SESI AKTIF
                                </span>
                                <?php else: ?>
                                <span
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold bg-gray-100 text-gray-500 rounded-full">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                                    BELUM ABSEN
                                </span>
                                <?php endif; ?>
                            </div>

                            <!-- Today info chips -->
                            <div class="flex flex-wrap justify-center sm:justify-start gap-3 mt-4">
                                <div class="bg-gray-50 rounded-xl px-4 py-2.5 min-w-32">
                                    <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-0.5">Waktu
                                        Masuk</p>
                                    <p class="text-lg font-bold text-gray-800">
                                        <?= $todayAbsen ? date('H:i', strtotime($todayAbsen['waktu_scan'])) : '—:—' ?>
                                        <?= $todayAbsen ? '<span class="text-xs font-normal text-gray-400">WIB</span>' : '' ?>
                                    </p>
                                </div>
                                <div class="bg-gray-50 rounded-xl px-4 py-2.5 min-w-32">
                                    <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-0.5">Status
                                        Harian</p>
                                    <?php if ($todayAbsen):
                                        $cfg = $statusCfg[$todayAbsen['status_kehadiran']] ?? ['label'=>'—','bg'=>'bg-gray-100 text-gray-500','dot'=>'bg-gray-400'];
                                    ?>
                                    <span
                                        class="inline-flex items-center gap-1.5 px-3 py-1 text-sm font-bold rounded-full <?= $cfg['bg'] ?>">
                                        <span class="w-2 h-2 rounded-full <?= $cfg['dot'] ?>"></span>
                                        <?= $cfg['label'] ?>
                                    </span>
                                    <?php else: ?>
                                    <p class="text-lg font-bold text-gray-300">Tidak ada</p>
                                    <?php endif; ?>
                                </div>
                                <div class="bg-gray-50 rounded-xl px-4 py-2.5 min-w-32">
                                    <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-0.5">Kelas
                                    </p>
                                    <p class="text-sm font-bold text-gray-800">
                                        <?= htmlspecialchars($data['nama_kelas'] ?? '—') ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Monthly attendance card -->
                <div class="bg-primary rounded-2xl shadow-sm p-6 flex flex-col justify-between">
                    <div>
                        <p class="text-xs text-white/70 font-semibold uppercase tracking-widest mb-1">Kehadiran Bulanan
                        </p>
                        <p class="text-xs text-white/60"><?= $bulanIndo[$bulan] . ' ' . $tahun ?></p>
                    </div>
                    <div class="my-4">
                        <div class="flex items-end gap-2">
                            <p class="text-5xl font-bold text-white"><?= $monthPct ?>%</p>
                            <?php if ($pctDiff !== null): ?>
                            <span
                                class="text-sm font-semibold mb-2 <?= $pctDiff >= 0 ? 'text-green-300' : 'text-red-300' ?>">
                                <?= $pctDiff >= 0 ? '+' : '' ?><?= $pctDiff ?>%
                            </span>
                            <?php endif; ?>
                        </div>
                        <?php if ($pctDiff !== null): ?>
                        <p class="text-xs text-white/60 mt-1">dari bulan lalu (<?= $lmPct ?>%)</p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="w-full bg-white/20 rounded-full h-2 mb-2 overflow-hidden">
                            <div class="h-full rounded-full bg-white transition-all" style="width:<?= $monthPct ?>%">
                            </div>
                        </div>
                        <div class="flex justify-between text-xs text-white/60">
                            <span>Target: 90.0%</span>
                            <span><?= $monthPct >= 90 ? '✓ Sesuai Jalur' : '⚠ Di Bawah Target' ?></span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Activity + Semester summary + Wali Kelas -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- Activity timeline -->
                <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-100 shadow-sm">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-gray-800">Linimasa Aktivitas</h3>
                            <p class="text-xs text-gray-400 mt-0.5">Log detail kehadiran terbaru</p>
                        </div>
                        <!-- <a href="/absensi/modules/parents/index.php" class="text-xs text-primary font-semibold hover:underline">
                            Lihat Semua <i class="bi bi-arrow-right ms-0.5"></i>
                        </a> -->
                    </div>

                    <div class="divide-y divide-gray-50">
                        <?php if (empty($recentRecords)): ?>
                        <div class="px-6 py-10 text-center text-gray-300">
                            <i class="bi bi-journal-x text-3xl block mb-2"></i>
                            <p class="text-sm">Belum ada catatan absensi</p>
                        </div>
                        <?php else: ?>
                        <div
                            class="px-6 py-3 grid grid-cols-4 text-xs font-semibold text-gray-400 uppercase tracking-widest">
                            <span>Tanggal</span><span>Tipe</span><span>Status</span><span>Keterangan</span>
                        </div>
                        <?php foreach ($recentRecords as $r):
                            $date = new DateTime($r['tanggal_absensi']);
                            $hari = $hariIndo[$date->format('w')];
                            $tgl  = $date->format('d M Y');
                            $cfg  = $statusCfg[$r['status_kehadiran']] ?? ['label'=>$r['status_kehadiran'],'bg'=>'bg-gray-100 text-gray-500','dot'=>'bg-gray-400'];
                        ?>
                        <div class="px-6 py-3 grid grid-cols-4 items-center gap-2 hover:bg-gray-50/60 transition">
                            <div>
                                <p class="text-sm font-semibold text-gray-700"><?= $tgl ?></p>
                                <p class="text-xs text-gray-400"><?= $hari ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Pindaian Pagi</p>
                                <?php if ($r['waktu_scan']): ?>
                                <p class="text-xs text-gray-400"><?= date('H:i', strtotime($r['waktu_scan'])) ?> WIB</p>
                                <?php endif; ?>
                            </div>
                            <div>
                                <span
                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-bold rounded-full <?= $cfg['bg'] ?>">
                                    <span class="w-1.5 h-1.5 rounded-full <?= $cfg['dot'] ?>"></span>
                                    <?= $cfg['label'] ?>
                                </span>
                            </div>
                            <div class="text-xs text-gray-400">
                                <?= $r['keterangan'] ? htmlspecialchars($r['keterangan']) : '—' ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right column -->
                <div class="space-y-4">

                    <!-- Semester summary -->
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                        <h3 class="font-bold text-gray-800 mb-4">Ringkasan Semester Ini</h3>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-green-50 rounded-xl p-3 text-center">
                                <p class="text-2xl font-bold text-green-700"><?= $semStats['hadir'] ?></p>
                                <p class="text-xs font-semibold text-green-600 uppercase tracking-wide mt-0.5">Hari
                                    Hadir</p>
                            </div>
                            <div class="bg-red-50 rounded-xl p-3 text-center">
                                <p class="text-2xl font-bold text-red-600"><?= $semAbsen ?></p>
                                <p class="text-xs font-semibold text-red-500 uppercase tracking-wide mt-0.5">Hari Absen
                                </p>
                            </div>
                            <div class="bg-blue-50 rounded-xl p-3 text-center">
                                <p class="text-2xl font-bold text-blue-700"><?= $semStats['izin'] ?></p>
                                <p class="text-xs font-semibold text-blue-600 uppercase tracking-wide mt-0.5">Izin</p>
                            </div>
                            <div class="bg-yellow-50 rounded-xl p-3 text-center">
                                <p class="text-2xl font-bold text-yellow-700"><?= $semStats['sakit'] ?></p>
                                <p class="text-xs font-semibold text-yellow-600 uppercase tracking-wide mt-0.5">Sakit
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Wali Kelas -->
                    <?php if ($data['wali_kelas_nama']): ?>
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                        <h3 class="font-bold text-gray-800 mb-4">Wali Kelas</h3>
                        <div class="flex items-center gap-3 mb-4">
                            <div
                                class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 text-lg font-bold shrink-0">
                                <?= mb_strtoupper(mb_substr($data['wali_kelas_nama'], 0, 1)) ?>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-800 text-sm">
                                    <?= htmlspecialchars($data['wali_kelas_nama']) ?></p>
                                <p class="text-xs text-gray-400">Wali Kelas
                                    <?= htmlspecialchars($data['nama_kelas'] ?? '') ?></p>
                            </div>
                        </div>
                        <?php if ($data['wali_kelas_hp']): ?>
                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $data['wali_kelas_hp']) ?>"
                            target="_blank"
                            class="w-full flex items-center justify-center gap-2 py-2.5 text-sm font-semibold text-white bg-primary rounded-xl hover:bg-primary/90 transition">
                            <i class="bi bi-whatsapp"></i> Hubungi Wali Kelas
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                </div>
            </div>

        </main>
    </div>
</div>

<?php require_once $basePath . 'includes/footer.php'; ?>