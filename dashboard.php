<?php
require_once 'includes/auth_check.php';
require_auth(['admin', 'guru', 'siswa', 'orang_tua']);

$pageTitle = 'Dashboard';
require_once 'includes/header.php';

$role = $_SESSION['role'];
$nama = $_SESSION['nama'];

// Stats (query real data, default 0 if tables empty)
$totalSiswa   = 0;
$hadirHariIni = 0;
$absenHariIni = 0;
$recentLogs   = [];

try {
    require_once 'config/database.php';
    $pdo   = getDB();
    $today = date('Y-m-d');

    if (in_array($role, ['admin', 'guru'])) {
        $totalSiswa   = (int) $pdo->query('SELECT COUNT(*) FROM siswa WHERE status = "aktif"')->fetchColumn();

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM absensi WHERE tanggal_absensi = ? AND status_kehadiran = ?');
        $stmt->execute([$today, 'hadir']);
        $hadirHariIni = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM absensi WHERE tanggal_absensi = ? AND status_kehadiran IN (?,?,?)');
        $stmt->execute([$today, 'izin', 'sakit', 'alfa']);
        $absenHariIni = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT s.nama_siswa, k.nama_kelas, a.waktu_scan, a.status_kehadiran
            FROM absensi a
            JOIN siswa s ON a.siswa_id = s.id
            LEFT JOIN kelas k ON s.kelas_id = k.id
            ORDER BY a.id DESC
            LIMIT 5
        ");
        $stmt->execute();
        $recentLogs = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    // Tables not yet seeded — show zeros
}

$statusBadge = [
    'hadir'  => 'bg-blue-100 text-blue-700',
    'izin'   => 'bg-yellow-100 text-yellow-700',
    'sakit'  => 'bg-purple-100 text-purple-700',
    'alfa'   => 'bg-red-100 text-red-700',
];
?>

<div class="flex h-screen overflow-hidden bg-gray-50">

    <?php require_once 'includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

        <?php require_once 'includes/navbar.php'; ?>

        <main class="flex-1 p-6 space-y-6 overflow-y-auto">

            <!-- Heading -->
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Hallo, <?= htmlspecialchars($nama) ?>!</h2>
                <p class="text-gray-400 text-sm mt-0.5">
                    Berikut adalah Ringkasan kehadiran hari ini, <?= date('l, d F Y') ?>
                </p>
            </div>

            <?php if (in_array($role, ['admin', 'guru'])): ?>

            <!-- Stat Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

                <div
                    class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Total Siswa Terdaftar</p>
                        <p class="text-4xl font-bold text-gray-800"><?= number_format($totalSiswa, 0, ',', '.') ?></p>
                    </div>
                    <div
                        class="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center text-blue-500 text-2xl shrink-0">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>

                <div
                    class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Hadir Hari Ini</p>
                        <p class="text-4xl font-bold text-green-500"><?= number_format($hadirHariIni, 0, ',', '.') ?>
                        </p>
                    </div>
                    <div
                        class="w-12 h-12 rounded-full bg-green-50 flex items-center justify-center text-green-500 text-2xl shrink-0">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                </div>

                <div
                    class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Absen Hari Ini</p>
                        <p class="text-4xl font-bold text-red-500"><?= number_format($absenHariIni, 0, ',', '.') ?></p>
                    </div>
                    <div
                        class="w-12 h-12 rounded-full bg-red-50 flex items-center justify-center text-red-500 text-2xl shrink-0">
                        <i class="bi bi-x-circle-fill"></i>
                    </div>
                </div>

            </div>

            <!-- Log + Weekly Summary -->
            <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

                <!-- Log Kehadiran Terbaru -->
                <div class="lg:col-span-3 bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-gray-800">Log Kehadiran baru</h3>
                        <a href="/absensi/modules/attendance/history.php"
                            class="text-sm text-primary font-medium hover:underline flex items-center gap-1">
                            Lihat Semua <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>

                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-xs text-gray-400 uppercase tracking-wide border-b border-gray-100">
                                <th class="pb-3 text-left font-medium">Nama Siswa</th>
                                <th class="pb-3 text-left font-medium">Kelas</th>
                                <th class="pb-3 text-left font-medium">Waktu</th>
                                <th class="pb-3 text-left font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php if (empty($recentLogs)): ?>
                            <tr>
                                <td colspan="4" class="py-8 text-center text-gray-300 text-sm">
                                    <i class="bi bi-inbox text-3xl block mb-2"></i>
                                    Belum ada data kehadiran hari ini
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($recentLogs as $log): ?>
                            <tr>
                                <td class="py-3 flex items-center gap-2">
                                    <div
                                        class="w-7 h-7 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 shrink-0 text-xs font-bold">
                                        <?= mb_strtoupper(mb_substr($log['nama_siswa'], 0, 1)) ?>
                                    </div>
                                    <span class="font-medium text-gray-700 truncate max-w-30">
                                        <?= htmlspecialchars($log['nama_siswa']) ?>
                                    </span>
                                </td>
                                <td class="py-3 text-gray-500"><?= htmlspecialchars($log['nama_kelas'] ?? '-') ?></td>
                                <td class="py-3 text-gray-500">
                                    <?= $log['waktu_scan'] ? substr($log['waktu_scan'], 0, 5) . ' WIB' : '—' ?></td>
                                <td class="py-3">
                                    <?php $s = $log['status_kehadiran']; ?>
                                    <span
                                        class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $statusBadge[$s] ?? 'bg-gray-100 text-gray-600' ?>">
                                        <span class="w-1.5 h-1.5 rounded-full bg-current opacity-70"></span>
                                        <?= strtoupper($s) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Ringkasan Mingguan -->
                <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                    <h3 class="font-semibold text-gray-800 mb-1">Ringkasan Mingguan</h3>
                    <p class="text-xs text-gray-400 mb-4">Rerata kehadiran 7 hari terakhir</p>
                    <div class="mb-1">
                        <p class="text-xs text-gray-400 uppercase tracking-wide">Rerata Kehadiran</p>
                        <p class="text-2xl font-bold text-gray-800" id="avg-rate">—%</p>
                    </div>
                    <canvas id="weeklyChart" height="160"></canvas>
                    <div class="flex items-center gap-4 mt-3 text-xs text-gray-400">
                        <span class="flex items-center gap-1.5">
                            <span class="w-3 h-3 rounded-sm bg-primary inline-block"></span> Minggu Ini
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="w-3 h-3 rounded-sm bg-blue-100 inline-block"></span> Minggu Lalu
                        </span>
                    </div>
                </div>

            </div>

            <?php else: ?>

            <!-- View untuk Siswa & Orang Tua -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-8 text-center">
                <i class="bi bi-calendar-check text-5xl text-primary/30 block mb-3"></i>
                <p class="text-gray-500">Pilih menu di sidebar untuk melihat data kehadiran.</p>
            </div>

            <?php endif; ?>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
<?php if (in_array($role, ['admin', 'guru'])): ?>
const days = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum'];
const thisW = [88, 92, 85, 94, 90];
const lastW = [82, 87, 80, 88, 85];
const avg = (thisW.reduce((a, b) => a + b, 0) / thisW.length).toFixed(1);

document.getElementById('avg-rate').textContent = avg + '%';

new Chart(document.getElementById('weeklyChart'), {
    type: 'bar',
    data: {
        labels: days,
        datasets: [{
                label: 'Minggu Ini',
                data: thisW,
                backgroundColor: '#0070ba',
                borderRadius: 6,
                borderSkipped: false,
            },
            {
                label: 'Minggu Lalu',
                data: lastW,
                backgroundColor: '#bfdbfe',
                borderRadius: 6,
                borderSkipped: false,
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                min: 60,
                max: 100,
                ticks: {
                    callback: v => v + '%',
                    font: {
                        size: 10
                    }
                },
                grid: {
                    color: '#f3f4f6'
                }
            },
            x: {
                grid: {
                    display: false
                },
                ticks: {
                    font: {
                        size: 11
                    }
                }
            }
        }
    }
});
<?php endif; ?>
</script>

<?php require_once 'includes/footer.php'; ?>