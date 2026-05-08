<?php
require_once '../../includes/auth_check.php';
require_auth(['admin']);

$basePath = '../../';
require_once $basePath . 'config/database.php';

$search     = trim($_GET['q'] ?? '');
$perPage    = 10;
$page       = max(1, (int) ($_GET['page'] ?? 1));
$teachers   = [];
$totalRows  = 0;
$totalPages = 1;
$totalGuru  = 0;
$totalL     = 0;
$totalP     = 0;
$dbError    = null;

$createErrors  = [];
$editErrors    = [];
$accountErrors = [];
$openModal     = '';
$activeTab     = 'data';
$editData      = null;

try {
    $pdo = getDB();

    // Handle CREATE
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'create') {
        $nip          = trim($_POST['nip'] ?? '');
        $namaGuru     = trim($_POST['nama_guru'] ?? '');
        $jenisKelamin = trim($_POST['jenis_kelamin'] ?? '');
        $noHp         = trim($_POST['no_hp'] ?? '');
        $alamat       = trim($_POST['alamat'] ?? '');

        if ($namaGuru     === '') $createErrors[] = 'Nama guru wajib diisi.';
        if ($jenisKelamin === '') $createErrors[] = 'Jenis kelamin wajib dipilih.';

        if ($nip !== '') {
            $chk = $pdo->prepare('SELECT id FROM guru WHERE nip = ?');
            $chk->execute([$nip]);
            if ($chk->fetch()) $createErrors[] = 'NIP sudah digunakan.';
        }

        if (empty($createErrors)) {
            $stmt = $pdo->prepare('INSERT INTO guru (nip, nama_guru, jenis_kelamin, no_hp, alamat) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$nip ?: null, $namaGuru, $jenisKelamin, $noHp ?: null, $alamat ?: null]);
            header('Location: index.php?created=1');
            exit;
        }

        $openModal = 'create';
    }

    // Handle EDIT
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'edit') {
        $editId       = (int) ($_POST['id'] ?? 0);
        $nip          = trim($_POST['nip'] ?? '');
        $namaGuru     = trim($_POST['nama_guru'] ?? '');
        $jenisKelamin = trim($_POST['jenis_kelamin'] ?? '');
        $noHp         = trim($_POST['no_hp'] ?? '');
        $alamat       = trim($_POST['alamat'] ?? '');

        if ($namaGuru     === '') $editErrors[] = 'Nama guru wajib diisi.';
        if ($jenisKelamin === '') $editErrors[] = 'Jenis kelamin wajib dipilih.';

        if ($nip !== '' && $editId) {
            $chk = $pdo->prepare('SELECT id FROM guru WHERE nip = ? AND id != ?');
            $chk->execute([$nip, $editId]);
            if ($chk->fetch()) $editErrors[] = 'NIP sudah digunakan.';
        }

        if (empty($editErrors) && $editId) {
            $upd = $pdo->prepare('UPDATE guru SET nip=?, nama_guru=?, jenis_kelamin=?, no_hp=?, alamat=? WHERE id=?');
            $upd->execute([$nip ?: null, $namaGuru, $jenisKelamin, $noHp ?: null, $alamat ?: null, $editId]);
            header('Location: index.php?updated=1');
            exit;
        }

        $openModal = 'edit';
        $editData  = compact('editId', 'nip', 'namaGuru', 'jenisKelamin', 'noHp', 'alamat');
    }

    // Handle GURU ACCOUNT
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'guru_account') {
        $editId      = (int) ($_POST['id'] ?? 0);
        $emailGuru   = trim($_POST['email_guru'] ?? '');
        $passwordG   = $_POST['password_guru'] ?? '';
        $guruUserId  = (int) ($_POST['guru_user_id'] ?? 0);

        if ($emailGuru === '')                             $accountErrors[] = 'Email wajib diisi.';
        if (!filter_var($emailGuru, FILTER_VALIDATE_EMAIL)) $accountErrors[] = 'Format email tidak valid.';
        if (!$guruUserId && $passwordG === '')              $accountErrors[] = 'Password wajib diisi untuk akun baru.';
        if ($passwordG !== '' && strlen($passwordG) < 6)   $accountErrors[] = 'Password minimal 6 karakter.';

        if (empty($accountErrors)) {
            $nRow = $pdo->prepare('SELECT nama_guru FROM guru WHERE id = ?');
            $nRow->execute([$editId]);
            $namaGuru = $nRow->fetchColumn() ?: '';

            if ($guruUserId) {
                $pdo->prepare('UPDATE users SET nama=?, email=? WHERE id=?')
                    ->execute([$namaGuru, $emailGuru, $guruUserId]);
                if ($passwordG !== '') {
                    $pdo->prepare('UPDATE users SET password=? WHERE id=?')
                        ->execute([password_hash($passwordG, PASSWORD_DEFAULT), $guruUserId]);
                }
            } else {
                $chk = $pdo->prepare('SELECT id FROM users WHERE email = ?');
                $chk->execute([$emailGuru]);
                if ($chk->fetch()) {
                    $accountErrors[] = 'Email sudah digunakan akun lain.';
                } else {
                    $pdo->prepare('INSERT INTO users (nama, email, password, role) VALUES (?,?,?,"guru")')
                        ->execute([$namaGuru, $emailGuru, password_hash($passwordG, PASSWORD_DEFAULT)]);
                    $newUid = (int) $pdo->lastInsertId();
                    $pdo->prepare('UPDATE guru SET user_id=? WHERE id=?')->execute([$newUid, $editId]);
                }
            }
            if (empty($accountErrors)) {
                header('Location: index.php?account_saved=1'); exit;
            }
        }
        $openModal = 'edit';
        $activeTab = 'account';
    }

    // Main queries
    $where  = '';
    $params = [];
    if ($search !== '') {
        $where  = 'WHERE g.nama_guru LIKE ? OR g.nip LIKE ? OR g.no_hp LIKE ?';
        $params = ["%$search%", "%$search%", "%$search%"];
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM guru g $where");
    $countStmt->execute($params);
    $totalRows  = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($totalRows / $perPage));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * $perPage;

    $stmt = $pdo->prepare("
        SELECT g.id, g.nip, g.nama_guru, g.jenis_kelamin, g.no_hp, g.alamat, g.user_id AS guru_user_id,
               u.email AS guru_email,
               (SELECT COUNT(*) FROM kelas k WHERE k.wali_kelas_id = g.id) AS jumlah_kelas
        FROM guru g
        LEFT JOIN users u ON g.user_id = u.id
        $where
        ORDER BY g.nama_guru
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($params);
    $teachers = $stmt->fetchAll();

    $totalGuru = (int) $pdo->query("SELECT COUNT(*) FROM guru")->fetchColumn();
    $totalL    = (int) $pdo->query("SELECT COUNT(*) FROM guru WHERE jenis_kelamin = 'L'")->fetchColumn();
    $totalP    = (int) $pdo->query("SELECT COUNT(*) FROM guru WHERE jenis_kelamin = 'P'")->fetchColumn();

} catch (PDOException $e) {
    $dbError = $e->getMessage();
}

$flash = '';
if (isset($_GET['created']))      $flash = 'success:Guru berhasil ditambahkan.';
if (isset($_GET['updated']))      $flash = 'success:Data guru berhasil diperbarui.';
if (isset($_GET['deleted']))      $flash = 'danger:Guru berhasil dihapus.';
if (isset($_GET['delete_failed'])) $flash = 'danger:Guru tidak dapat dihapus karena masih menjadi wali kelas.';
if (isset($_GET['account_saved'])) $flash = 'success:Akun login guru berhasil disimpan.';

$pageTitle = 'Data Guru';
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
                    <h2 class="text-2xl font-bold text-gray-800">Data Guru</h2>
                    <p class="text-gray-400 text-sm mt-0.5">Manajemen data guru dan wali kelas</p>
                </div>
                <button onclick="openModal('modal-create')"
                        class="flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-primary rounded-xl hover:bg-primary/90 transition shrink-0">
                    <i class="bi bi-plus-circle"></i> Tambah Guru
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

                <div class="bg-primary rounded-2xl shadow-sm p-6 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-white/20 flex items-center justify-center text-white text-2xl shrink-0">
                        <i class="bi bi-person-workspace"></i>
                    </div>
                    <div>
                        <p class="text-xs text-white/80 uppercase tracking-wide font-medium">Total Guru</p>
                        <p class="text-3xl font-bold text-white"><?= number_format($totalGuru, 0, ',', '.') ?></p>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center text-blue-500 text-2xl shrink-0">
                        <i class="bi bi-person"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide font-medium">Laki-laki</p>
                        <p class="text-3xl font-bold text-gray-800"><?= number_format($totalL, 0, ',', '.') ?></p>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-pink-50 flex items-center justify-center text-pink-500 text-2xl shrink-0">
                        <i class="bi bi-person-fill"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide font-medium">Perempuan</p>
                        <p class="text-3xl font-bold text-gray-800"><?= number_format($totalP, 0, ',', '.') ?></p>
                    </div>
                </div>

            </div>

            <!-- Table -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">

                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-4 flex-wrap">
                    <h3 class="font-bold text-gray-800 text-sm uppercase tracking-wide">Daftar Guru</h3>
                    <form method="GET" class="flex items-center gap-2">
                        <div class="relative">
                            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                                   placeholder="Cari nama atau NIP..."
                                   class="pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary w-56 transition">
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
                                <th class="px-5 py-3 text-left font-medium">Nama Guru</th>
                                <th class="px-5 py-3 text-left font-medium">NIP</th>
                                <th class="px-5 py-3 text-left font-medium">Jenis Kelamin</th>
                                <th class="px-5 py-3 text-left font-medium">No. HP</th>
                                <th class="px-5 py-3 text-left font-medium">Wali Kelas</th>
                                <th class="px-5 py-3 text-left font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php if (empty($teachers)): ?>
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center text-gray-300">
                                    <i class="bi bi-inbox text-4xl block mb-2"></i>
                                    <?= $search ? 'Tidak ada guru yang cocok.' : 'Belum ada data guru.' ?>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($teachers as $g): ?>
                            <tr class="hover:bg-gray-50/60 transition">

                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 text-sm font-bold shrink-0">
                                            <?= mb_strtoupper(mb_substr($g['nama_guru'], 0, 1)) ?>
                                        </div>
                                        <span class="font-semibold text-gray-800"><?= htmlspecialchars($g['nama_guru']) ?></span>
                                    </div>
                                </td>

                                <td class="px-5 py-4 text-gray-500 font-mono text-xs">
                                    <?= $g['nip'] ? htmlspecialchars($g['nip']) : '<span class="text-gray-300 italic font-sans">—</span>' ?>
                                </td>

                                <td class="px-5 py-4">
                                    <span class="inline-block px-3 py-1 text-xs font-semibold rounded-full
                                        <?= $g['jenis_kelamin'] === 'L' ? 'bg-blue-50 text-blue-700' : 'bg-pink-50 text-pink-700' ?>">
                                        <?= $g['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan' ?>
                                    </span>
                                </td>

                                <td class="px-5 py-4 text-gray-600">
                                    <?= $g['no_hp'] ? htmlspecialchars($g['no_hp']) : '<span class="text-gray-300 text-xs italic">—</span>' ?>
                                </td>

                                <td class="px-5 py-4">
                                    <?php if ($g['jumlah_kelas'] > 0): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium bg-green-50 text-green-700 rounded-full">
                                        <i class="bi bi-building text-xs"></i>
                                        <?= $g['jumlah_kelas'] ?> kelas
                                    </span>
                                    <?php else: ?>
                                    <span class="text-gray-300 text-xs italic">Tidak ada</span>
                                    <?php endif; ?>
                                </td>

                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-2">
                                        <button
                                                data-id="<?= $g['id'] ?>"
                                                data-nip="<?= htmlspecialchars($g['nip'] ?? '') ?>"
                                                data-nama="<?= htmlspecialchars($g['nama_guru']) ?>"
                                                data-jk="<?= htmlspecialchars($g['jenis_kelamin']) ?>"
                                                data-nohp="<?= htmlspecialchars($g['no_hp'] ?? '') ?>"
                                                data-alamat="<?= htmlspecialchars($g['alamat'] ?? '') ?>"
                                                data-userid="<?= (int)($g['guru_user_id'] ?? 0) ?>"
                                                data-email="<?= htmlspecialchars($g['guru_email'] ?? '') ?>"
                                                onclick="openEditFromBtn(this)"
                                                class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-blue-50 hover:text-primary hover:border-blue-200 transition"
                                                title="Edit">
                                            <i class="bi bi-pencil text-sm"></i>
                                        </button>
                                        <button onclick="confirmDelete(<?= $g['id'] ?>, '<?= htmlspecialchars($g['nama_guru'], ENT_QUOTES) ?>')"
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
                        Menampilkan <?= number_format(count($teachers), 0, ',', '.') ?>
                        dari <?= number_format($totalRows, 0, ',', '.') ?> guru
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

<!-- Modal: Tambah Guru -->
<div id="modal-create" class="fixed inset-0 z-50 flex items-center justify-center hidden">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeModal('modal-create')"></div>
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 p-6">

        <div class="flex items-center justify-between mb-5">
            <div>
                <h3 class="text-lg font-bold text-gray-800">Tambah Guru</h3>
                <p class="text-xs text-gray-400 mt-0.5">Tambahkan data guru baru</p>
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

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">Nama Guru *</label>
                    <input type="text" name="nama_guru"
                           value="<?= $openModal === 'create' ? htmlspecialchars($_POST['nama_guru'] ?? '') : '' ?>"
                           placeholder="Nama lengkap" required
                           class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">NIP</label>
                    <input type="text" name="nip"
                           value="<?= $openModal === 'create' ? htmlspecialchars($_POST['nip'] ?? '') : '' ?>"
                           placeholder="Nomor induk pegawai"
                           class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">Jenis Kelamin *</label>
                    <select name="jenis_kelamin" required
                            class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                        <option value="">Pilih</option>
                        <option value="L" <?= ($openModal === 'create' && ($_POST['jenis_kelamin'] ?? '') === 'L') ? 'selected' : '' ?>>Laki-laki</option>
                        <option value="P" <?= ($openModal === 'create' && ($_POST['jenis_kelamin'] ?? '') === 'P') ? 'selected' : '' ?>>Perempuan</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">No. HP</label>
                    <input type="text" name="no_hp"
                           value="<?= $openModal === 'create' ? htmlspecialchars($_POST['no_hp'] ?? '') : '' ?>"
                           placeholder="08xx-xxxx-xxxx"
                           class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">Alamat</label>
                <textarea name="alamat" rows="2" placeholder="Alamat lengkap"
                          class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition resize-none"><?= $openModal === 'create' ? htmlspecialchars($_POST['alamat'] ?? '') : '' ?></textarea>
            </div>

            <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                <button type="submit"
                        class="px-5 py-2.5 text-sm font-semibold text-white bg-primary rounded-xl hover:bg-primary/90 transition">
                    <i class="bi bi-check-lg me-1"></i> Simpan
                </button>
                <button type="button" onclick="closeModal('modal-create')"
                        class="px-5 py-2.5 text-sm font-medium text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition">
                    Batal
                </button>
            </div>
        </form>

    </div>
</div>

<!-- Modal: Edit Guru -->
<div id="modal-edit" class="fixed inset-0 z-50 flex items-center justify-center hidden">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeModal('modal-edit')"></div>
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 max-h-[90vh] flex flex-col">

        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 shrink-0">
            <div><h3 class="text-lg font-bold text-gray-800">Edit Guru</h3><p class="text-xs text-gray-400 mt-0.5" id="edit-modal-sub">—</p></div>
            <button onclick="closeModal('modal-edit')" class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 transition"><i class="bi bi-x-lg text-sm"></i></button>
        </div>

        <!-- Tabs -->
        <div class="flex border-b border-gray-100 px-6 shrink-0">
            <button id="tab-btn-data" onclick="switchTab('data')"
                    class="px-4 py-3 text-sm font-semibold border-b-2 border-primary text-primary transition">
                <i class="bi bi-person-workspace me-1"></i> Data Guru
            </button>
            <button id="tab-btn-account" onclick="switchTab('account')"
                    class="px-4 py-3 text-sm font-semibold border-b-2 border-transparent text-gray-400 hover:text-gray-600 transition">
                <i class="bi bi-key me-1"></i> Akun Login
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-6">

            <!-- Tab: Data Guru -->
            <div id="tab-data">
                <?php if ($openModal === 'edit' && $activeTab === 'data' && $editErrors): ?>
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-xs text-red-600 space-y-1">
                    <?php foreach ($editErrors as $e): ?><p><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($e) ?></p><?php endforeach; ?>
                </div>
                <?php endif; ?>
                <form method="POST" class="space-y-4" novalidate>
                    <input type="hidden" name="_action" value="edit">
                    <input type="hidden" name="id" id="edit-id" value="<?= $editData['editId'] ?? '' ?>">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">Nama Guru *</label>
                            <input type="text" name="nama_guru" id="edit-nama-guru" value="<?= htmlspecialchars($editData['namaGuru'] ?? '') ?>" placeholder="Nama lengkap" required
                                   class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">NIP</label>
                            <input type="text" name="nip" id="edit-nip" value="<?= htmlspecialchars($editData['nip'] ?? '') ?>" placeholder="Nomor induk pegawai"
                                   class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widests mb-1.5">Jenis Kelamin *</label>
                            <select name="jenis_kelamin" id="edit-jenis-kelamin" required
                                    class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                                <option value="">Pilih</option>
                                <option value="L" <?= ($editData['jenisKelamin'] ?? '') === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                                <option value="P" <?= ($editData['jenisKelamin'] ?? '') === 'P' ? 'selected' : '' ?>>Perempuan</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widests mb-1.5">No. HP</label>
                            <input type="text" name="no_hp" id="edit-no-hp" value="<?= htmlspecialchars($editData['noHp'] ?? '') ?>" placeholder="08xx-xxxx-xxxx"
                                   class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widests mb-1.5">Alamat</label>
                        <textarea name="alamat" id="edit-alamat" rows="2" placeholder="Alamat lengkap"
                                  class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition resize-none"><?= htmlspecialchars($editData['alamat'] ?? '') ?></textarea>
                    </div>
                    <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                        <button type="submit" class="px-5 py-2.5 text-sm font-semibold text-white bg-primary rounded-xl hover:bg-primary/90 transition"><i class="bi bi-check-lg me-1"></i> Simpan Perubahan</button>
                        <button type="button" onclick="closeModal('modal-edit')" class="px-5 py-2.5 text-sm font-medium text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition">Batal</button>
                    </div>
                </form>
            </div>

            <!-- Tab: Akun Login -->
            <div id="tab-account" class="hidden">
                <?php if ($openModal === 'edit' && $activeTab === 'account' && $accountErrors): ?>
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-xs text-red-600 space-y-1">
                    <?php foreach ($accountErrors as $e): ?><p><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($e) ?></p><?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div id="account-status-badge" class="mb-4 hidden"></div>
                <form method="POST" class="space-y-4" novalidate>
                    <input type="hidden" name="_action" value="guru_account">
                    <input type="hidden" name="id" id="account-guru-id">
                    <input type="hidden" name="guru_user_id" id="account-user-id">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widests mb-1.5">Email Login *</label>
                        <input type="email" name="email_guru" id="account-email"
                               class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widests mb-1.5">
                            Password <span id="account-pw-note" class="font-normal text-gray-400 normal-case tracking-normal"></span>
                        </label>
                        <div class="relative">
                            <input type="password" name="password_guru" id="account-pw" placeholder="Min. 6 karakter"
                                   class="w-full px-4 pr-10 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                            <button type="button" tabindex="-1" onclick="togglePw('account-pw', this)"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition">
                                <i class="bi bi-eye text-sm"></i>
                            </button>
                        </div>
                    </div>
                    <div class="flex gap-3 pt-2 border-t border-gray-100">
                        <button type="submit" id="account-submit-btn"
                                class="px-5 py-2.5 text-sm font-semibold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 transition">
                            <i class="bi bi-key me-1"></i> Buat Akun Login
                        </button>
                        <button type="button" onclick="closeModal('modal-edit')" class="px-5 py-2.5 text-sm font-medium text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition">Batal</button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>

<script>
function openModal(id) {
    const el = document.getElementById(id);
    el.classList.remove('hidden');
    el.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    const el = document.getElementById(id);
    el.classList.add('hidden');
    el.style.display = '';
    document.body.style.overflow = '';
}

function switchTab(tab) {
    ['data','account'].forEach(t => {
        document.getElementById('tab-'+t).classList.toggle('hidden', t !== tab);
        const btn = document.getElementById('tab-btn-'+t);
        btn.classList.toggle('border-primary', t === tab);
        btn.classList.toggle('text-primary', t === tab);
        btn.classList.toggle('border-transparent', t !== tab);
        btn.classList.toggle('text-gray-400', t !== tab);
    });
}

function openEditFromBtn(btn) {
    openEditModal({
        id:           btn.dataset.id,
        nip:          btn.dataset.nip,
        nama_guru:    btn.dataset.nama,
        jenis_kelamin:btn.dataset.jk,
        no_hp:        btn.dataset.nohp,
        alamat:       btn.dataset.alamat,
        guru_user_id: btn.dataset.userid,
        guru_email:   btn.dataset.email,
    });
}

function openEditModal(data) {
    document.getElementById('edit-modal-sub').textContent  = data.nama_guru;
    document.getElementById('edit-id').value               = data.id;
    document.getElementById('edit-nama-guru').value        = data.nama_guru;
    document.getElementById('edit-nip').value              = data.nip;
    document.getElementById('edit-jenis-kelamin').value    = data.jenis_kelamin;
    document.getElementById('edit-no-hp').value            = data.no_hp;
    document.getElementById('edit-alamat').value           = data.alamat;

    document.getElementById('account-guru-id').value  = data.id;
    document.getElementById('account-user-id').value  = data.guru_user_id;
    document.getElementById('account-email').value    = data.guru_email;
    document.getElementById('account-pw').value       = '';

    const hasAccount = !!data.guru_user_id;
    document.getElementById('account-pw-note').textContent = hasAccount ? '(kosongkan jika tidak diganti)' : '*';
    document.getElementById('account-submit-btn').innerHTML = hasAccount
        ? '<i class="bi bi-check-lg me-1"></i> Perbarui Akun Login'
        : '<i class="bi bi-key me-1"></i> Buat Akun Login';

    const badge = document.getElementById('account-status-badge');
    badge.innerHTML = hasAccount
        ? '<span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold bg-green-50 text-green-700 border border-green-200 rounded-full"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Akun Aktif · ' + data.guru_email + '</span>'
        : '<span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold bg-gray-100 text-gray-500 rounded-full"><span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span> Belum Ada Akun Login</span>';
    badge.classList.remove('hidden');

    switchTab('data');
    openModal('modal-edit');
}

function confirmDelete(id, nama) {
    if (!confirm(`Hapus guru "${nama}"?\nData guru yang menjadi wali kelas tidak dapat dihapus.`)) return;
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
<?php if ($activeTab === 'account'): ?>switchTab('account');<?php endif; ?>
<?php endif; ?>
</script>

<?php require_once $basePath . 'includes/footer.php'; ?>
