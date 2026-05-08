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
$guruList   = [];
$dbError    = null;

$createErrors = [];
$editErrors   = [];
$openModal    = '';
$editData     = null;

try {
    $pdo = getDB();

    // Handle CREATE
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'create') {
        $namaKelas   = trim($_POST['nama_kelas'] ?? '');
        $tingkat     = trim($_POST['tingkat'] ?? '');
        $tahunAjaran = trim($_POST['tahun_ajaran'] ?? '');
        $waliKelasId = ((int) ($_POST['wali_kelas_id'] ?? 0)) ?: null;

        if ($namaKelas === '') $createErrors[] = 'Nama kelas wajib diisi.';
        if ($tingkat   === '') $createErrors[] = 'Tingkat wajib dipilih.';

        if ($namaKelas !== '') {
            $chk = $pdo->prepare('SELECT id FROM kelas WHERE nama_kelas = ?');
            $chk->execute([$namaKelas]);
            if ($chk->fetch()) $createErrors[] = 'Nama kelas sudah digunakan.';
        }

        if (empty($createErrors)) {
            $stmt = $pdo->prepare('INSERT INTO kelas (nama_kelas, tingkat, tahun_ajaran, wali_kelas_id) VALUES (?, ?, ?, ?)');
            $stmt->execute([$namaKelas, $tingkat, $tahunAjaran, $waliKelasId]);
            header('Location: index.php?created=1');
            exit;
        }

        $openModal  = 'create';
    }

    // Handle EDIT
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'edit') {
        $editId      = (int) ($_POST['id'] ?? 0);
        $namaKelas   = trim($_POST['nama_kelas'] ?? '');
        $tingkat     = trim($_POST['tingkat'] ?? '');
        $tahunAjaran = trim($_POST['tahun_ajaran'] ?? '');
        $waliKelasId = ((int) ($_POST['wali_kelas_id'] ?? 0)) ?: null;

        if ($namaKelas === '') $editErrors[] = 'Nama kelas wajib diisi.';
        if ($tingkat   === '') $editErrors[] = 'Tingkat wajib dipilih.';

        if ($namaKelas !== '' && $editId) {
            $chk = $pdo->prepare('SELECT id FROM kelas WHERE nama_kelas = ? AND id != ?');
            $chk->execute([$namaKelas, $editId]);
            if ($chk->fetch()) $editErrors[] = 'Nama kelas sudah digunakan.';
        }

        if (empty($editErrors) && $editId) {
            $upd = $pdo->prepare('UPDATE kelas SET nama_kelas=?, tingkat=?, tahun_ajaran=?, wali_kelas_id=? WHERE id=?');
            $upd->execute([$namaKelas, $tingkat, $tahunAjaran, $waliKelasId, $editId]);
            header('Location: index.php?updated=1');
            exit;
        }

        $openModal = 'edit';
        $editData  = [
            'id'           => $editId,
            'namaKelas'    => $namaKelas,
            'tingkat'      => $tingkat,
            'tahunAjaran'  => $tahunAjaran,
            'waliKelasId'  => $waliKelasId,
        ];
    }

    // Main queries
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
        SELECT k.id, k.nama_kelas, k.tingkat, k.tahun_ajaran, k.wali_kelas_id,
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
    $guruList   = $pdo->query('SELECT id, nama_guru FROM guru ORDER BY nama_guru')->fetchAll();

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
                <button onclick="openModal('modal-create')"
                        class="flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-primary rounded-xl hover:bg-primary/90 transition shrink-0">
                    <i class="bi bi-plus-circle"></i> Tambah Kelas
                </button>
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
                                        <button onclick="openEditModal(<?= htmlspecialchars(json_encode([
                                                    'id'           => $k['id'],
                                                    'nama_kelas'   => $k['nama_kelas'],
                                                    'tingkat'      => $k['tingkat'],
                                                    'tahun_ajaran' => $k['tahun_ajaran'] ?? '',
                                                    'wali_kelas_id'=> (string) ($k['wali_kelas_id'] ?? ''),
                                                ]), ENT_QUOTES) ?>"
                                                class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-blue-50 hover:text-primary hover:border-blue-200 transition"
                                                title="Edit">
                                            <i class="bi bi-pencil text-sm"></i>
                                        </button>
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

<!-- Modal: Tambah Kelas -->
<div id="modal-create" class="fixed inset-0 z-50 flex items-center justify-center hidden">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeModal('modal-create')"></div>
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 p-6 animate-fade-in">

        <div class="flex items-center justify-between mb-5">
            <div>
                <h3 class="text-lg font-bold text-gray-800">Tambah Kelas</h3>
                <p class="text-xs text-gray-400 mt-0.5">Buat rombongan belajar baru</p>
            </div>
            <button onclick="closeModal('modal-create')" class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>

        <?php if ($openModal === 'create' && $createErrors): ?>
        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-xs text-red-600 space-y-1">
            <?php foreach ($createErrors as $e): ?>
            <p><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($e) ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4" novalidate>
            <input type="hidden" name="_action" value="create">

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">Nama Kelas *</label>
                <input type="text" name="nama_kelas"
                       value="<?= $openModal === 'create' ? htmlspecialchars($_POST['nama_kelas'] ?? '') : '' ?>"
                       placeholder="Contoh: XII IPS 1" required
                       class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">Tingkat *</label>
                    <select name="tingkat" required
                            class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                        <option value="">Pilih tingkat</option>
                        <?php foreach (['X', 'XI', 'XII'] as $t): ?>
                        <option value="<?= $t ?>" <?= ($openModal === 'create' && ($_POST['tingkat'] ?? '') === $t) ? 'selected' : '' ?>>
                            Kelas <?= $t ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">Tahun Ajaran</label>
                    <input type="text" name="tahun_ajaran"
                           value="<?= $openModal === 'create' ? htmlspecialchars($_POST['tahun_ajaran'] ?? '2025/2026') : '2025/2026' ?>"
                           placeholder="2025/2026"
                           class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">Wali Kelas</label>
                <select name="wali_kelas_id"
                        class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                    <option value="">— Pilih wali kelas —</option>
                    <?php foreach ($guruList as $g): ?>
                    <option value="<?= $g['id'] ?>" <?= ($openModal === 'create' && ($_POST['wali_kelas_id'] ?? '') == $g['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($g['nama_guru']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                <button type="submit"
                        class="px-5 py-2.5 text-sm font-semibold text-white bg-primary rounded-xl hover:bg-primary/90 transition">
                    <i class="bi bi-check-lg me-1"></i> Simpan Kelas
                </button>
                <button type="button" onclick="closeModal('modal-create')"
                        class="px-5 py-2.5 text-sm font-medium text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition">
                    Batal
                </button>
            </div>
        </form>

    </div>
</div>

<!-- Modal: Edit Kelas -->
<div id="modal-edit" class="fixed inset-0 z-50 flex items-center justify-center hidden">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeModal('modal-edit')"></div>
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 p-6 animate-fade-in">

        <div class="flex items-center justify-between mb-5">
            <div>
                <h3 class="text-lg font-bold text-gray-800">Edit Kelas</h3>
                <p class="text-xs text-gray-400 mt-0.5">Ubah data kelas</p>
            </div>
            <button onclick="closeModal('modal-edit')" class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>

        <?php if ($openModal === 'edit' && $editErrors): ?>
        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-xs text-red-600 space-y-1">
            <?php foreach ($editErrors as $e): ?>
            <p><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($e) ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4" novalidate>
            <input type="hidden" name="_action" value="edit">
            <input type="hidden" name="id" id="edit-id" value="<?= $editData['id'] ?? '' ?>">

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">Nama Kelas *</label>
                <input type="text" name="nama_kelas" id="edit-nama-kelas"
                       value="<?= htmlspecialchars($editData['namaKelas'] ?? '') ?>"
                       placeholder="Contoh: XII IPS 1" required
                       class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">Tingkat *</label>
                    <select name="tingkat" id="edit-tingkat" required
                            class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                        <option value="">Pilih tingkat</option>
                        <?php foreach (['X', 'XI', 'XII'] as $t): ?>
                        <option value="<?= $t ?>" <?= ($editData['tingkat'] ?? '') === $t ? 'selected' : '' ?>>
                            Kelas <?= $t ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">Tahun Ajaran</label>
                    <input type="text" name="tahun_ajaran" id="edit-tahun-ajaran"
                           value="<?= htmlspecialchars($editData['tahunAjaran'] ?? '') ?>"
                           placeholder="2025/2026"
                           class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">Wali Kelas</label>
                <select name="wali_kelas_id" id="edit-wali-kelas-id"
                        class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                    <option value="">— Pilih wali kelas —</option>
                    <?php foreach ($guruList as $g): ?>
                    <option value="<?= $g['id'] ?>" <?= ($editData['waliKelasId'] ?? '') == $g['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($g['nama_guru']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                <button type="submit"
                        class="px-5 py-2.5 text-sm font-semibold text-white bg-primary rounded-xl hover:bg-primary/90 transition">
                    <i class="bi bi-check-lg me-1"></i> Simpan Perubahan
                </button>
                <button type="button" onclick="closeModal('modal-edit')"
                        class="px-5 py-2.5 text-sm font-medium text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition">
                    Batal
                </button>
            </div>
        </form>

    </div>
</div>

<script>
function openModal(id) {
    document.getElementById(id).classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
    document.body.style.overflow = '';
}

function openEditModal(data) {
    document.getElementById('edit-id').value          = data.id;
    document.getElementById('edit-nama-kelas').value  = data.nama_kelas;
    document.getElementById('edit-tingkat').value     = data.tingkat;
    document.getElementById('edit-tahun-ajaran').value = data.tahun_ajaran;
    document.getElementById('edit-wali-kelas-id').value = data.wali_kelas_id || '';
    openModal('modal-edit');
}

function confirmDelete(id, nama) {
    if (!confirm(`Hapus kelas "${nama}"?\nSemua siswa di kelas ini akan kehilangan data kelasnya.`)) return;
    document.getElementById('delete-id').value = id;
    document.getElementById('delete-form').submit();
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal('modal-create');
        closeModal('modal-edit');
    }
});

<?php if ($openModal): ?>
openModal('modal-<?= $openModal ?>');
<?php endif; ?>
</script>

<?php require_once $basePath . 'includes/footer.php'; ?>
