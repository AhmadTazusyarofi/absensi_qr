<?php
require_once '../../includes/auth_check.php';
require_auth(['admin']);

$basePath = '../../';
require_once $basePath . 'config/database.php';

$pdo    = getDB();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama    = trim($_POST['nama_siswa'] ?? '');
    $nis     = trim($_POST['nis'] ?? '');
    $jk      = $_POST['jenis_kelamin'] ?? '';
    $kelasId = ((int) ($_POST['kelas_id'] ?? 0)) ?: null;
    $noHp    = trim($_POST['no_hp'] ?? '');
    $alamat  = trim($_POST['alamat'] ?? '');
    $status  = in_array($_POST['status'] ?? '', ['aktif', 'nonaktif'], true) ? $_POST['status'] : 'aktif';

    if ($nama === '') $errors[] = 'Nama siswa wajib diisi.';
    if ($nis  === '') $errors[] = 'NIS wajib diisi.';
    if (!in_array($jk, ['L', 'P'], true)) $errors[] = 'Jenis kelamin wajib dipilih.';

    if ($nis !== '') {
        $chk = $pdo->prepare('SELECT id FROM siswa WHERE nis = ?');
        $chk->execute([$nis]);
        if ($chk->fetch()) $errors[] = 'NIS sudah digunakan siswa lain.';
    }

    $fotoName = null;
    if (!empty($_FILES['foto']['tmp_name'])) {
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        $mime    = mime_content_type($_FILES['foto']['tmp_name']);
        if (!in_array($mime, $allowed, true)) {
            $errors[] = 'Foto harus berformat JPG, PNG, atau WebP.';
        } elseif ($_FILES['foto']['size'] > 2 * 1024 * 1024) {
            $errors[] = 'Ukuran foto maksimal 2MB.';
        } else {
            $ext      = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            $fotoName = bin2hex(random_bytes(8)) . '.' . $ext;
            $dest     = $_SERVER['DOCUMENT_ROOT'] . '/absensi/uploads/students/' . $fotoName;
            if (!move_uploaded_file($_FILES['foto']['tmp_name'], $dest)) {
                $errors[] = 'Gagal menyimpan foto.';
                $fotoName = null;
            }
        }
    }

    if (empty($errors)) {
        $barcode = strtoupper(bin2hex(random_bytes(8)));
        $stmt = $pdo->prepare('
            INSERT INTO siswa (nis, nama_siswa, jenis_kelamin, kelas_id, no_hp, alamat, foto, barcode, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([$nis, $nama, $jk, $kelasId, $noHp, $alamat, $fotoName, $barcode, $status]);
        header('Location: index.php?created=1');
        exit;
    }
}

$kelasList = $pdo->query('SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas')->fetchAll();

$pageTitle = 'Tambah Siswa';
require_once $basePath . 'includes/header.php';
?>

<div class="flex h-screen overflow-hidden bg-gray-50">

    <?php require_once $basePath . 'includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

        <?php require_once $basePath . 'includes/navbar.php'; ?>

        <main class="flex-1 p-6 overflow-y-auto">

            <!-- Header -->
            <div class="flex items-center gap-3 mb-6">
                <a href="index.php" class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-100 transition">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Tambah Siswa</h2>
                    <p class="text-gray-400 text-sm">Isi data siswa baru</p>
                </div>
            </div>

            <?php if ($errors): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-600 space-y-1">
                <?php foreach ($errors as $e): ?>
                <p><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($e) ?></p>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 max-w-3xl">
                <form method="POST" enctype="multipart/form-data" class="space-y-5" novalidate>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">Nama Siswa *</label>
                            <input type="text" name="nama_siswa" value="<?= htmlspecialchars($_POST['nama_siswa'] ?? '') ?>"
                                   placeholder="Nama lengkap" required
                                   class="w-full px-4 py-3 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">NIS *</label>
                            <input type="text" name="nis" value="<?= htmlspecialchars($_POST['nis'] ?? '') ?>"
                                   placeholder="Nomor Induk Siswa" required
                                   class="w-full px-4 py-3 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">Jenis Kelamin *</label>
                            <select name="jenis_kelamin" required
                                    class="w-full px-4 py-3 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                                <option value="">Pilih jenis kelamin</option>
                                <option value="L" <?= (($_POST['jenis_kelamin'] ?? '') === 'L') ? 'selected' : '' ?>>Laki-laki</option>
                                <option value="P" <?= (($_POST['jenis_kelamin'] ?? '') === 'P') ? 'selected' : '' ?>>Perempuan</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">Kelas</label>
                            <select name="kelas_id"
                                    class="w-full px-4 py-3 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                                <option value="">— Pilih kelas —</option>
                                <?php foreach ($kelasList as $k): ?>
                                <option value="<?= $k['id'] ?>" <?= (($_POST['kelas_id'] ?? '') == $k['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($k['nama_kelas']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">No. HP</label>
                            <input type="text" name="no_hp" value="<?= htmlspecialchars($_POST['no_hp'] ?? '') ?>"
                                   placeholder="08xx-xxxx-xxxx"
                                   class="w-full px-4 py-3 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">Status</label>
                            <select name="status"
                                    class="w-full px-4 py-3 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                                <option value="aktif"    <?= (($_POST['status'] ?? 'aktif') === 'aktif')    ? 'selected' : '' ?>>Aktif</option>
                                <option value="nonaktif" <?= (($_POST['status'] ?? '') === 'nonaktif') ? 'selected' : '' ?>>Nonaktif</option>
                            </select>
                        </div>

                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">Alamat</label>
                        <textarea name="alamat" rows="3" placeholder="Alamat lengkap siswa"
                                  class="w-full px-4 py-3 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition resize-none"><?= htmlspecialchars($_POST['alamat'] ?? '') ?></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">Foto Profil</label>
                        <input type="file" name="foto" accept="image/jpeg,image/png,image/webp"
                               class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary file:text-white hover:file:bg-primary/90 cursor-pointer">
                        <p class="text-xs text-gray-400 mt-1">JPG, PNG, atau WebP. Maks 2MB.</p>
                    </div>

                    <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                        <button type="submit"
                                class="px-6 py-2.5 text-sm font-semibold text-white bg-primary rounded-xl hover:bg-primary/90 transition">
                            <i class="bi bi-check-lg me-1"></i> Simpan Siswa
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
