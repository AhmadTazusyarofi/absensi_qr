<?php
require_once '../../includes/auth_check.php';
require_auth(['admin']);

$basePath = '../../';
require_once $basePath . 'config/database.php';

$pdo    = getDB();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $namaKelas   = trim($_POST['nama_kelas'] ?? '');
    $tingkat     = trim($_POST['tingkat'] ?? '');
    $tahunAjaran = trim($_POST['tahun_ajaran'] ?? '');
    $waliKelasId = ((int) ($_POST['wali_kelas_id'] ?? 0)) ?: null;

    if ($namaKelas === '') $errors[] = 'Nama kelas wajib diisi.';
    if ($tingkat   === '') $errors[] = 'Tingkat wajib dipilih.';

    if ($namaKelas !== '') {
        $chk = $pdo->prepare('SELECT id FROM kelas WHERE nama_kelas = ?');
        $chk->execute([$namaKelas]);
        if ($chk->fetch()) $errors[] = 'Nama kelas sudah digunakan.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('
            INSERT INTO kelas (nama_kelas, tingkat, tahun_ajaran, wali_kelas_id)
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([$namaKelas, $tingkat, $tahunAjaran, $waliKelasId]);
        header('Location: index.php?created=1');
        exit;
    }
}

$guruList = $pdo->query('SELECT id, nama_guru FROM guru ORDER BY nama_guru')->fetchAll();

$pageTitle = 'Tambah Kelas';
require_once $basePath . 'includes/header.php';
?>

<div class="flex h-screen overflow-hidden bg-gray-50">

    <?php require_once $basePath . 'includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

        <?php require_once $basePath . 'includes/navbar.php'; ?>

        <main class="flex-1 p-6 overflow-y-auto">

            <div class="flex items-center gap-3 mb-6">
                <a href="index.php" class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-100 transition">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Tambah Kelas</h2>
                    <p class="text-gray-400 text-sm">Buat rombongan belajar baru</p>
                </div>
            </div>

            <?php if ($errors): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-600 space-y-1">
                <?php foreach ($errors as $e): ?>
                <p><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($e) ?></p>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 max-w-xl">
                <form method="POST" class="space-y-5" novalidate>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">Nama Kelas *</label>
                        <input type="text" name="nama_kelas" value="<?= htmlspecialchars($_POST['nama_kelas'] ?? '') ?>"
                               placeholder="Contoh: XII IPS 1" required
                               class="w-full px-4 py-3 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                    </div>

                    <div class="grid grid-cols-2 gap-5">

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">Tingkat *</label>
                            <select name="tingkat" required
                                    class="w-full px-4 py-3 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                                <option value="">Pilih tingkat</option>
                                <?php foreach (['X', 'XI', 'XII'] as $t): ?>
                                <option value="<?= $t ?>" <?= (($_POST['tingkat'] ?? '') === $t) ? 'selected' : '' ?>>
                                    Kelas <?= $t ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">Tahun Ajaran</label>
                            <input type="text" name="tahun_ajaran" value="<?= htmlspecialchars($_POST['tahun_ajaran'] ?? '2025/2026') ?>"
                                   placeholder="2025/2026"
                                   class="w-full px-4 py-3 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                        </div>

                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">Wali Kelas</label>
                        <select name="wali_kelas_id"
                                class="w-full px-4 py-3 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                            <option value="">— Pilih wali kelas —</option>
                            <?php foreach ($guruList as $g): ?>
                            <option value="<?= $g['id'] ?>" <?= (($_POST['wali_kelas_id'] ?? '') == $g['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($g['nama_guru']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($guruList)): ?>
                        <p class="text-xs text-gray-400 mt-1">
                            <i class="bi bi-info-circle me-1"></i>Belum ada data guru. Tambahkan guru terlebih dahulu.
                        </p>
                        <?php endif; ?>
                    </div>

                    <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                        <button type="submit"
                                class="px-6 py-2.5 text-sm font-semibold text-white bg-primary rounded-xl hover:bg-primary/90 transition">
                            <i class="bi bi-check-lg me-1"></i> Simpan Kelas
                        </button>
                        <a href="index.php"
                           class="px-6 py-2.5 text-sm font-medium text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition">
                            Batal
                        </a>
                    </div>

                </form>
            </div>

        </main>
    </div>
</div>

<?php require_once $basePath . 'includes/footer.php'; ?>
