<?php
require_once '../../includes/auth_check.php';
require_auth(['admin']);

$basePath = '../../';
require_once $basePath . 'config/database.php';

$search     = trim($_GET['q'] ?? '');
$perPage    = 10;
$page       = max(1, (int) ($_GET['page'] ?? 1));
$classes    = [];
$totalRows  = 0;
$totalPages = 1;
$totalKelas = 0;
$totalGuru  = 0;
$totalSiswa = 0;
$dbError    = null;

try {
    $pdo = getDB();

    $where  = '';
    $params = [];
    if ($search !== '') {
        $where  = 'WHERE k.nama_kelas LIKE ? OR k.tingkat LIKE ? OR k.tahun_ajaran LIKE ?';
        $params = ["%$search%", "%$search%", "%$search%"];
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM kelas k $where");
    $countStmt->execute($params);
    $totalRows  = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($totalRows / $perPage));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * $perPage;

    $stmt = $pdo->prepare("
        SELECT k.id, k.nama_kelas, k.tingkat, k.tahun_ajaran,
               g.nama_guru AS wali_kelas,
               (SELECT COUNT(*) FROM siswa s WHERE s.kelas_id = k.id AND s.status = 'aktif') AS jumlah_siswa
        FROM kelas k
        LEFT JOIN guru g ON k.wali_kelas_id = g.id
        $where
        ORDER BY k.tingkat, k.nama_kelas
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($params);
    $classes = $stmt->fetchAll();

    $totalKelas = (int) $pdo->query('SELECT COUNT(*) FROM kelas')->fetchColumn();
    $totalGuru  = (int) $pdo->query('SELECT COUNT(*) FROM guru')->fetchColumn();
    $totalSiswa = (int) $pdo->query('SELECT COUNT(*) FROM siswa WHERE status = "aktif"')->fetchColumn();

} catch (PDOException $e) {
    $dbError = $e->getMessage();
}

$flash = '';
if (isset($_GET['created'])) $flash = 'success:Kelas berhasil ditambahkan.';
if (isset($_GET['updated'])) $flash = 'success:Data kelas berhasil diperbarui.';
if (isset($_GET['deleted'])) $flash = 'danger:Kelas berhasil dihapus.';

$pageTitle = 'Data Kelas';
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
                    <h2 class="text-2xl font-bold text-gray-800">Data Kelas</h2>
                    <p class="text-gray-400 text-sm mt-0.5">Manajemen kelas, wali kelas, dan data rombongan belajar</p>
                </div>
                <a href="create.php"
                   class="flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-primary rounded-xl hover:bg-primary/90 transition shrink-0">
                    <i class="bi bi-plus-circle"></i> Tambah Kelas
                </a>
            </div>

            <?php if ($flash): ?>
            <?php [$fType, $fMsg] = explode(':', $flash, 2); ?>
            <div class="px-4 py-3 rounded-xl text-sm flex items-center gap-2
                        <?= $fType === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-600' ?>">
                <i class="bi <?= $fType === 'success' ? 'bi-check-circle' : 'bi-exclamation-circle' ?>"></i>
                <?= htmlspecialchars($fMsg) ?>
            </div>
            <?php endif; ?>

            <?php if ($dbError): ?>
            <div class="px-4 py-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
                <p class="font-semibold mb-1"><i class="bi bi-database-exclamation me-1"></i> Database belum siap</p>
                <p class="text-xs text-red-500">Pastikan sudah import <code>database/absensi.sql</code> via phpMyAdmin.</p>
                <p class="text-xs text-red-400 mt-1 font-mono"><?= htmlspecialchars($dbError) ?></p>
            </div>
            <?php endif; ?>

            <!-- Stat Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center text-blue-500 text-2xl shrink-0">
                        <i class="bi bi-building"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide font-medium">Total Kelas</p>
                        <p class="text-3xl font-bold text-gray-800"><?= number_format($totalKelas, 0, ',', '.') ?></p>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-purple-50 flex items-center justify-center text-purple-500 text-2xl shrink-0">
                        <i class="bi bi-person-workspace"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide font-medium">Total Guru</p>
                        <p class="text-3xl font-bold text-gray-800"><?= number_format($totalGuru, 0, ',', '.') ?></p>
                    </div>
                </div>

                <div class="bg-primary rounded-2xl shadow-sm p-6 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-white/20 flex items-center justify-center text-white text-2xl shrink-0">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div>
                        <p class="text-xs text-white/80 uppercase tracking-wide font-medium">Total Siswa Aktif</p>
                        <p class="text-3xl font-bold text-white"><?= number_format($totalSiswa, 0, ',', '.') ?></p>
                    </div>
                </div>

            </div>

            <!-- Table -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">

                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-4 flex-wrap">
                    <h3 class="font-bold text-gray-800 text-sm uppercase tracking-wide">Daftar Kelas</h3>
                    <form method="GET" class="flex items-center gap-2">
                        <div class="relative">
                            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                                   placeholder="Cari nama kelas atau tingkat..."
                                   class="pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary w-64 transition">
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
                                <th class="px-5 py-3 text-left font-medium">Nama Kelas</th>
                                <th class="px-5 py-3 text-left font-medium">Tingkat</th>
                                <th class="px-5 py-3 text-left font-medium">Wali Kelas</th>
                                <th class="px-5 py-3 text-left font-medium">Jumlah Siswa</th>
                                <th class="px-5 py-3 text-left font-medium">Tahun Ajaran</th>
                                <th class="px-5 py-3 text-left font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php if (empty($classes)): ?>
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center text-gray-300">
                                    <i class="bi bi-inbox text-4xl block mb-2"></i>
                                    <?= $search ? 'Tidak ada kelas yang cocok.' : 'Belum ada data kelas.' ?>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($classes as $k): ?>
                            <tr class="hover:bg-gray-50/60 transition">

                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-xl bg-primary/10 flex items-center justify-center text-primary shrink-0">
                                            <i class="bi bi-mortarboard text-base"></i>
                                        </div>
                                        <span class="font-semibold text-gray-800"><?= htmlspecialchars($k['nama_kelas']) ?></span>
                                    </div>
                                </td>

                                <td class="px-5 py-4">
                                    <span class="inline-block px-3 py-1 text-xs font-semibold rounded-full
                                        <?php
                                        echo match($k['tingkat']) {
                                            'X'    => 'bg-green-50 text-green-700',
                                            'XI'   => 'bg-yellow-50 text-yellow-700',
                                            'XII'  => 'bg-blue-50 text-blue-700',
                                            default => 'bg-gray-100 text-gray-600',
                                        };
                                        ?>">
                                        Kelas <?= htmlspecialchars($k['tingkat'] ?? '—') ?>
                                    </span>
                                </td>

                                <td class="px-5 py-4">
                                    <?php if ($k['wali_kelas']): ?>
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 text-xs font-bold shrink-0">
                                            <?= mb_strtoupper(mb_substr($k['wali_kelas'], 0, 1)) ?>
                                        </div>
                                        <span class="text-gray-700"><?= htmlspecialchars($k['wali_kelas']) ?></span>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-gray-300 text-xs italic">Belum ditentukan</span>
                                    <?php endif; ?>
                                </td>

                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-lg font-bold text-gray-800"><?= $k['jumlah_siswa'] ?></span>
                                        <span class="text-xs text-gray-400">siswa</span>
                                    </div>
                                </td>

                                <td class="px-5 py-4 text-gray-600">
                                    <?= htmlspecialchars($k['tahun_ajaran'] ?? '—') ?>
                                </td>

                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-2">
                                        <a href="edit.php?id=<?= $k['id'] ?>"
                                           class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-blue-50 hover:text-primary hover:border-blue-200 transition"
                                           title="Edit">
                                            <i class="bi bi-pencil text-sm"></i>
                                        </a>
                                        <button onclick="confirmDelete(<?= $k['id'] ?>, '<?= htmlspecialchars($k['nama_kelas'], ENT_QUOTES) ?>')"
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
                        Menampilkan <?= number_format(count($classes), 0, ',', '.') ?>
                        dari <?= number_format($totalRows, 0, ',', '.') ?> kelas
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
                                  <?= $p === $page ? 'bg-primary text-white border border-primary' : 'border border-gray-200 text-gray-600 hover:bg-gray-100' ?>">
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

<!-- Delete form -->
<form id="delete-form" method="POST" action="delete.php">
    <input type="hidden" name="id" id="delete-id">
</form>

<script>
function confirmDelete(id, nama) {
    if (!confirm(`Hapus kelas "${nama}"?\nSemua siswa di kelas ini akan kehilangan data kelasnya.`)) return;
    document.getElementById('delete-id').value = id;
    document.getElementById('delete-form').submit();
}
</script>

<?php require_once $basePath . 'includes/footer.php'; ?>
