<?php
require_once '../../includes/auth_check.php';
require_auth(['siswa']);

$basePath = '../../';
require_once $basePath . 'config/database.php';

$siswa   = null;
$absensi = [];
$dbError = null;

try {
    $pdo = getDB();

    $stmt = $pdo->prepare('
        SELECT s.*, k.nama_kelas, k.tingkat
        FROM siswa s
        LEFT JOIN kelas k ON s.kelas_id = k.id
        WHERE s.user_id = ?
        LIMIT 1
    ');
    $stmt->execute([$_SESSION['user_id']]);
    $siswa = $stmt->fetch();

    if ($siswa) {
        // Recent attendance (last 10)
        $aStmt = $pdo->prepare('
            SELECT tanggal_absensi, waktu_scan, status_kehadiran, metode_absensi
            FROM absensi WHERE siswa_id = ?
            ORDER BY tanggal_absensi DESC, waktu_scan DESC LIMIT 10
        ');
        $aStmt->execute([$siswa['id']]);
        $absensi = $aStmt->fetchAll();

        // Monthly stats
        $mStmt = $pdo->prepare('
            SELECT status_kehadiran, COUNT(*) AS total
            FROM absensi WHERE siswa_id = ? AND MONTH(tanggal_absensi) = MONTH(NOW()) AND YEAR(tanggal_absensi) = YEAR(NOW())
            GROUP BY status_kehadiran
        ');
        $mStmt->execute([$siswa['id']]);
        $monthStats = ['hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alfa' => 0];
        foreach ($mStmt->fetchAll() as $r) $monthStats[$r['status_kehadiran']] = (int)$r['total'];
        $monthTotal = array_sum($monthStats);
        $monthPct   = $monthTotal > 0 ? round(($monthStats['hadir'] / $monthTotal) * 100) : 0;
    }

} catch (PDOException $e) {
    $dbError = $e->getMessage();
}

$statusCfg = [
    'hadir' => ['label' => 'Hadir',  'bg' => 'bg-green-100 text-green-700',  'dot' => 'bg-green-500'],
    'izin'  => ['label' => 'Izin',   'bg' => 'bg-blue-100 text-blue-700',    'dot' => 'bg-blue-500'],
    'sakit' => ['label' => 'Sakit',  'bg' => 'bg-yellow-100 text-yellow-700','dot' => 'bg-yellow-500'],
    'alfa'  => ['label' => 'Alfa',   'bg' => 'bg-red-100 text-red-600',      'dot' => 'bg-red-500'],
];

$bulanIndo = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$hariIndo  = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];

$pageTitle = 'Data Pribadi';
require_once $basePath . 'includes/header.php';
?>

<div class="flex h-screen overflow-hidden bg-gray-50">

    <?php require_once $basePath . 'includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

        <?php require_once $basePath . 'includes/navbar.php'; ?>

        <main class="flex-1 p-6 space-y-6 overflow-y-auto">

            <div>
                <h2 class="text-2xl font-bold text-gray-800">Data Pribadi</h2>
                <p class="text-gray-400 text-sm mt-0.5">Profil dan kartu identitas digital kamu</p>
            </div>

            <?php if ($dbError): ?>
            <div class="px-4 py-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
                <i class="bi bi-database-exclamation me-1"></i>
                <?php if (str_contains($dbError, 'user_id')): ?>
                Kolom <code>user_id</code> belum ada di tabel <code>siswa</code>. Jalankan SQL ini di phpMyAdmin:<br>
                <code class="text-xs mt-1 block bg-red-100 px-3 py-2 rounded-lg">ALTER TABLE siswa ADD COLUMN user_id INT NULL DEFAULT NULL;</code>
                <?php else: ?>
                <?= htmlspecialchars($dbError) ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!$siswa && !$dbError): ?>
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-6 py-16 text-center text-gray-400">
                <i class="bi bi-person-x text-5xl block mb-3"></i>
                <p class="font-semibold text-gray-600 mb-1">Akun belum terhubung</p>
                <p class="text-sm">Hubungi admin untuk menghubungkan akun ini ke data siswa.</p>
            </div>
            <?php endif; ?>

            <?php if ($siswa): ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- Profile + QR card -->
                <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                    <div class="flex flex-col sm:flex-row gap-6">

                        <!-- Left: Photo + QR -->
                        <div class="flex flex-col items-center gap-4 shrink-0">
                            <?php if ($siswa['foto']): ?>
                            <img src="/absensi/uploads/students/<?= htmlspecialchars($siswa['foto']) ?>"
                                 class="w-24 h-24 rounded-full object-cover border-4 border-gray-100 shadow-sm" alt="">
                            <?php else: ?>
                            <div class="w-24 h-24 rounded-full bg-primary/10 flex items-center justify-center text-primary text-4xl font-bold shadow-sm">
                                <?= mb_strtoupper(mb_substr($siswa['nama_siswa'], 0, 1)) ?>
                            </div>
                            <?php endif; ?>

                            <!-- QR Code -->
                            <div class="bg-gray-50 rounded-2xl p-3 border border-gray-100">
                                <div id="qr-code-box" class="flex items-center justify-center" style="width:160px;height:160px;"></div>
                            </div>
                            <p class="text-xs text-gray-400 font-mono text-center break-all max-w-40"><?= htmlspecialchars($siswa['barcode']) ?></p>
                            <p class="text-xs text-gray-400 text-center">Tunjukkan QR ini ke guru untuk absensi</p>
                        </div>

                        <!-- Right: Info -->
                        <div class="flex-1 min-w-0">
                            <h2 class="text-2xl font-bold text-gray-800 mb-1"><?= htmlspecialchars($siswa['nama_siswa']) ?></h2>
                            <p class="text-primary font-semibold font-mono text-sm mb-4">NIS: <?= htmlspecialchars($siswa['nis']) ?></p>

                            <div class="space-y-3">
                                <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl">
                                    <i class="bi bi-building text-primary shrink-0"></i>
                                    <div>
                                        <p class="text-xs text-gray-400 font-medium">Kelas</p>
                                        <p class="text-sm font-semibold text-gray-700"><?= htmlspecialchars($siswa['nama_kelas'] ?? 'Belum ditentukan') ?></p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl">
                                    <i class="bi bi-gender-ambiguous text-primary shrink-0"></i>
                                    <div>
                                        <p class="text-xs text-gray-400 font-medium">Jenis Kelamin</p>
                                        <p class="text-sm font-semibold text-gray-700"><?= $siswa['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></p>
                                    </div>
                                </div>
                                <?php if ($siswa['no_hp']): ?>
                                <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl">
                                    <i class="bi bi-phone text-primary shrink-0"></i>
                                    <div>
                                        <p class="text-xs text-gray-400 font-medium">No. HP</p>
                                        <p class="text-sm font-semibold text-gray-700"><?= htmlspecialchars($siswa['no_hp']) ?></p>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php if ($siswa['alamat']): ?>
                                <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl">
                                    <i class="bi bi-geo-alt text-primary shrink-0"></i>
                                    <div>
                                        <p class="text-xs text-gray-400 font-medium">Alamat</p>
                                        <p class="text-sm font-semibold text-gray-700"><?= htmlspecialchars($siswa['alamat']) ?></p>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Monthly stats -->
                <div class="space-y-4">
                    <div class="bg-primary rounded-2xl shadow-sm p-6">
                        <p class="text-xs text-white/70 font-semibold uppercase tracking-widest mb-1">Kehadiran Bulan Ini</p>
                        <p class="text-xs text-white/60 mb-3"><?= $bulanIndo[(int)date('n')] . ' ' . date('Y') ?></p>
                        <p class="text-5xl font-bold text-white mb-3"><?= $monthPct ?>%</p>
                        <div class="w-full bg-white/20 rounded-full h-2 overflow-hidden">
                            <div class="h-full rounded-full bg-white" style="width:<?= $monthPct ?>%"></div>
                        </div>
                        <p class="text-xs text-white/60 mt-2">Target: 90%</p>
                    </div>

                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                        <h3 class="font-bold text-gray-800 text-sm mb-3">Rincian Bulan Ini</h3>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="bg-green-50 rounded-xl p-3 text-center">
                                <p class="text-2xl font-bold text-green-700"><?= $monthStats['hadir'] ?></p>
                                <p class="text-xs font-semibold text-green-600 uppercase tracking-wide mt-0.5">Hadir</p>
                            </div>
                            <div class="bg-blue-50 rounded-xl p-3 text-center">
                                <p class="text-2xl font-bold text-blue-700"><?= $monthStats['izin'] ?></p>
                                <p class="text-xs font-semibold text-blue-600 uppercase tracking-wide mt-0.5">Izin</p>
                            </div>
                            <div class="bg-yellow-50 rounded-xl p-3 text-center">
                                <p class="text-2xl font-bold text-yellow-700"><?= $monthStats['sakit'] ?></p>
                                <p class="text-xs font-semibold text-yellow-600 uppercase tracking-wide mt-0.5">Sakit</p>
                            </div>
                            <div class="bg-red-50 rounded-xl p-3 text-center">
                                <p class="text-2xl font-bold text-red-600"><?= $monthStats['alfa'] ?></p>
                                <p class="text-xs font-semibold text-red-500 uppercase tracking-wide mt-0.5">Alfa</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Recent attendance -->
            <?php if (!empty($absensi)): ?>
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="font-bold text-gray-800 text-sm uppercase tracking-wide">Riwayat Kehadiran Terbaru</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-xs text-gray-400 uppercase tracking-wide bg-gray-50/60 border-b border-gray-100">
                                <th class="px-5 py-3 text-left font-medium">Tanggal</th>
                                <th class="px-5 py-3 text-left font-medium">Hari</th>
                                <th class="px-5 py-3 text-left font-medium">Jam Masuk</th>
                                <th class="px-5 py-3 text-left font-medium">Status</th>
                                <th class="px-5 py-3 text-left font-medium">Metode</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php foreach ($absensi as $a):
                                $d   = new DateTime($a['tanggal_absensi']);
                                $cfg = $statusCfg[$a['status_kehadiran']] ?? ['label'=>$a['status_kehadiran'],'bg'=>'bg-gray-100 text-gray-500','dot'=>'bg-gray-400'];
                            ?>
                            <tr class="hover:bg-gray-50/60 transition">
                                <td class="px-5 py-3 text-gray-700 font-medium"><?= $d->format('d M Y') ?></td>
                                <td class="px-5 py-3 text-gray-500"><?= $hariIndo[$d->format('w')] ?></td>
                                <td class="px-5 py-3 text-gray-600 font-mono">
                                    <?= $a['waktu_scan'] ? date('H:i', strtotime($a['waktu_scan'])) : '—' ?>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold rounded-full <?= $cfg['bg'] ?>">
                                        <span class="w-1.5 h-1.5 rounded-full <?= $cfg['dot'] ?>"></span>
                                        <?= $cfg['label'] ?>
                                    </span>
                                </td>
                                <td class="px-5 py-3">
                                    <?php if ($a['metode_absensi'] === 'barcode'): ?>
                                    <span class="inline-flex items-center gap-1 text-xs text-gray-500"><i class="bi bi-qr-code"></i> Scan</span>
                                    <?php else: ?>
                                    <span class="inline-flex items-center gap-1 text-xs text-gray-500"><i class="bi bi-pencil-square"></i> Manual</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <?php endif; // end $siswa ?>

        </main>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
<?php if ($siswa && $siswa['barcode']): ?>
new QRCode(document.getElementById('qr-code-box'), {
    text: '<?= addslashes($siswa['barcode']) ?>',
    width: 160, height: 160,
    colorDark: '#1a1a2e', colorLight: '#f9fafb',
    correctLevel: QRCode.CorrectLevel.M
});
<?php endif; ?>
</script>

<?php require_once $basePath . 'includes/footer.php'; ?>
