<?php
require_once '../../includes/auth_check.php';
require_auth(['admin']);

$basePath = '../../';
require_once $basePath . 'config/database.php';

$pdo = getDB();

// Search & pagination
$search  = trim($_GET['q'] ?? '');
$perPage = 10;
$page    = max(1, (int) ($_GET['page'] ?? 1));

$where  = '';
$params = [];
if ($search !== '') {
    $where  = 'WHERE s.nama_siswa LIKE ? OR s.nis LIKE ?';
    $params = ["%$search%", "%$search%"];
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM siswa s $where");
$countStmt->execute($params);
$totalRows  = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$stmt = $pdo->prepare("
    SELECT s.id, s.nis, s.nama_siswa, s.jenis_kelamin, s.foto, s.status, s.barcode,
           k.nama_kelas,
           u.email
    FROM   siswa s
    LEFT JOIN kelas k ON s.kelas_id = k.id
    LEFT JOIN users u ON s.user_id  = u.id
    $where
    ORDER BY s.id DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$students = $stmt->fetchAll();

// Stats
$today   = date('Y-m-d');
$totalSiswa = (int) $pdo->query('SELECT COUNT(*) FROM siswa WHERE status = "aktif"')->fetchColumn();

$aktifStmt = $pdo->prepare('SELECT COUNT(DISTINCT siswa_id) FROM absensi WHERE tanggal_absensi = ? AND status_kehadiran = "hadir"');
$aktifStmt->execute([$today]);
$aktifHariIni = (int) $aktifStmt->fetchColumn();

$persenKehadiran = $totalSiswa > 0 ? round(($aktifHariIni / $totalSiswa) * 100) : 0;

// Flash messages
$flash = '';
if (isset($_GET['created']))  $flash = 'success:Siswa berhasil ditambahkan.';
if (isset($_GET['updated']))  $flash = 'success:Data siswa berhasil diperbarui.';
if (isset($_GET['deleted']))  $flash = 'danger:Siswa berhasil dihapus.';

$pageTitle = 'Data Siswa';
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
                    <h2 class="text-2xl font-bold text-gray-800">Data Siswa</h2>
                    <p class="text-gray-400 text-sm mt-0.5">Manajemen data profil dan kartu identitas digital siswa</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <a href="export_pdf.php"
                       class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition">
                        <i class="bi bi-file-earmark-pdf"></i> Ekspor PDF
                    </a>
                    <a href="create.php"
                       class="flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-primary rounded-xl hover:bg-primary/90 transition">
                        <i class="bi bi-person-plus"></i> Tambah Siswa
                    </a>
                </div>
            </div>

            <?php if ($flash): ?>
            <?php [$type, $msg] = explode(':', $flash, 2); ?>
            <div class="px-4 py-3 rounded-xl text-sm flex items-center gap-2
                        <?= $type === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-600' ?>">
                <i class="bi <?= $type === 'success' ? 'bi-check-circle' : 'bi-exclamation-circle' ?>"></i>
                <?= htmlspecialchars($msg) ?>
            </div>
            <?php endif; ?>

            <!-- Stat Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center text-blue-500 text-2xl shrink-0">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide font-medium">Total Siswa</p>
                        <p class="text-3xl font-bold text-gray-800"><?= number_format($totalSiswa, 0, ',', '.') ?></p>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-green-50 flex items-center justify-center text-green-500 text-2xl shrink-0">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide font-medium">Aktif Hari Ini</p>
                        <p class="text-3xl font-bold text-gray-800"><?= number_format($aktifHariIni, 0, ',', '.') ?></p>
                    </div>
                </div>

                <div class="bg-primary rounded-2xl shadow-sm p-6 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-white/20 flex items-center justify-center text-white text-2xl shrink-0">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <div>
                        <p class="text-xs text-white/80 uppercase tracking-wide font-medium">Persentase Kehadiran</p>
                        <p class="text-3xl font-bold text-white"><?= $persenKehadiran ?>%</p>
                    </div>
                </div>

            </div>

            <!-- Table -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">

                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-4 flex-wrap">
                    <h3 class="font-bold text-gray-800 text-sm uppercase tracking-wide">Data Siswa Terbaru</h3>
                    <form method="GET" class="flex items-center gap-2">
                        <div class="relative">
                            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                                   placeholder="Cari nama atau NIS..."
                                   class="pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary w-60 transition">
                        </div>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-lg hover:bg-primary/90 transition">
                            Cari
                        </button>
                        <?php if ($search): ?>
                        <a href="index.php" class="px-3 py-2 text-sm text-gray-500 hover:text-gray-700 transition">Reset</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-xs text-gray-400 uppercase tracking-wide bg-gray-50/60 border-b border-gray-100">
                                <th class="px-5 py-3 text-left font-medium">Foto</th>
                                <th class="px-5 py-3 text-left font-medium">Nama</th>
                                <th class="px-5 py-3 text-left font-medium">NIS</th>
                                <th class="px-5 py-3 text-left font-medium">Kelas</th>
                                <th class="px-5 py-3 text-left font-medium">Pratinjau QR</th>
                                <th class="px-5 py-3 text-left font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center text-gray-300">
                                    <i class="bi bi-inbox text-4xl block mb-2"></i>
                                    <?= $search ? 'Tidak ada siswa yang cocok.' : 'Belum ada data siswa.' ?>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($students as $s): ?>
                            <tr class="hover:bg-gray-50/60 transition">

                                <td class="px-5 py-4">
                                    <?php if ($s['foto']): ?>
                                    <img src="/absensi/uploads/students/<?= htmlspecialchars($s['foto']) ?>"
                                         alt="Foto" class="w-10 h-10 rounded-full object-cover border border-gray-200">
                                    <?php else: ?>
                                    <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold text-sm">
                                        <?= mb_strtoupper(mb_substr($s['nama_siswa'], 0, 1)) ?>
                                    </div>
                                    <?php endif; ?>
                                </td>

                                <td class="px-5 py-4">
                                    <p class="font-semibold text-gray-800"><?= htmlspecialchars($s['nama_siswa']) ?></p>
                                    <p class="text-xs text-gray-400"><?= htmlspecialchars($s['email'] ?? '-') ?></p>
                                </td>

                                <td class="px-5 py-4 text-gray-600 font-mono text-xs"><?= htmlspecialchars($s['nis']) ?></td>

                                <td class="px-5 py-4">
                                    <?php if ($s['nama_kelas']): ?>
                                    <span class="inline-block px-3 py-1 text-xs font-semibold text-primary bg-blue-50 rounded-full">
                                        <?= htmlspecialchars($s['nama_kelas']) ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-gray-300 text-xs">—</span>
                                    <?php endif; ?>
                                </td>

                                <td class="px-5 py-4">
                                    <button
                                        onclick="showQR('<?= htmlspecialchars($s['nama_siswa'], ENT_QUOTES) ?>', '<?= htmlspecialchars($s['barcode'] ?? '', ENT_QUOTES) ?>')"
                                        class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-blue-50 hover:text-primary hover:border-blue-200 transition"
                                        title="Pratinjau QR">
                                        <i class="bi bi-qr-code text-base"></i>
                                    </button>
                                </td>

                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-2">
                                        <a href="edit.php?id=<?= $s['id'] ?>"
                                           class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-blue-50 hover:text-primary hover:border-blue-200 transition"
                                           title="Edit">
                                            <i class="bi bi-pencil text-sm"></i>
                                        </a>
                                        <button
                                            onclick="confirmDelete(<?= $s['id'] ?>, '<?= htmlspecialchars($s['nama_siswa'], ENT_QUOTES) ?>')"
                                            class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-red-50 hover:text-red-500 hover:border-red-200 transition"
                                            title="Hapus">
                                            <i class="bi bi-trash text-sm"></i>
                                        </button>
                                    </div>
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
                        Menampilkan <?= number_format(count($students), 0, ',', '.') ?>
                        dari <?= number_format($totalRows, 0, ',', '.') ?> siswa
                    </p>

                    <?php if ($totalPages > 1): ?>
                    <div class="flex items-center gap-1">
                        <a href="?page=<?= max(1, $page - 1) ?><?= $search ? '&q=' . urlencode($search) : '' ?>"
                           class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-100 transition <?= $page <= 1 ? 'opacity-40 pointer-events-none' : '' ?>">
                            <i class="bi bi-chevron-left text-xs"></i>
                        </a>

                        <?php
                        $pStart = max(1, $page - 2);
                        $pEnd   = min($totalPages, $pStart + 4);
                        $pStart = max(1, $pEnd - 4);
                        for ($p = $pStart; $p <= $pEnd; $p++):
                        ?>
                        <a href="?page=<?= $p ?><?= $search ? '&q=' . urlencode($search) : '' ?>"
                           class="w-9 h-9 flex items-center justify-center rounded-lg text-sm font-medium transition
                                  <?= $p === $page
                                        ? 'bg-primary text-white border border-primary'
                                        : 'border border-gray-200 text-gray-600 hover:bg-gray-100' ?>">
                            <?= $p ?>
                        </a>
                        <?php endfor; ?>

                        <a href="?page=<?= min($totalPages, $page + 1) ?><?= $search ? '&q=' . urlencode($search) : '' ?>"
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

<!-- QR Modal -->
<div id="qr-modal" class="fixed inset-0 bg-black/40 z-50 hidden items-center justify-center flex">
    <div class="bg-white rounded-2xl shadow-xl p-6 w-80 text-center" onclick="event.stopPropagation()">
        <h3 class="font-bold text-gray-800 mb-1" id="qr-name"></h3>
        <p class="text-xs text-gray-400 mb-4">Barcode / QR Siswa</p>
        <div class="bg-gray-50 rounded-xl p-6 mb-4">
            <i class="bi bi-qr-code text-7xl text-gray-300 block"></i>
        </div>
        <p id="qr-code-text" class="text-xs text-gray-500 font-mono mb-4 break-all"></p>
        <button onclick="closeQR()"
                class="w-full py-2.5 text-sm font-semibold text-white bg-primary rounded-xl hover:bg-primary/90 transition">
            Tutup
        </button>
    </div>
</div>

<!-- Delete form -->
<form id="delete-form" method="POST" action="delete.php">
    <input type="hidden" name="id" id="delete-id">
</form>

<script>
function showQR(nama, barcode) {
    document.getElementById('qr-name').textContent = nama;
    document.getElementById('qr-code-text').textContent = barcode || '(belum ada barcode)';
    document.getElementById('qr-modal').classList.remove('hidden');
}
function closeQR() {
    document.getElementById('qr-modal').classList.add('hidden');
}
document.getElementById('qr-modal').addEventListener('click', closeQR);

function confirmDelete(id, nama) {
    if (!confirm(`Hapus siswa "${nama}"?\nTindakan ini tidak dapat dibatalkan.`)) return;
    document.getElementById('delete-id').value = id;
    document.getElementById('delete-form').submit();
}
</script>

<?php require_once $basePath . 'includes/footer.php'; ?>
