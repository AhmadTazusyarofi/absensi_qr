<?php
require_once '../../includes/auth_check.php';
require_auth(['admin']);

$basePath = '../../';
require_once $basePath . 'config/database.php';

$pdo = getDB();
$id  = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if (!$id) { header('Location: index.php'); exit; }

$row = $pdo->prepare('SELECT * FROM siswa WHERE id = ?');
$row->execute([$id]);
$student = $row->fetch();

if (!$student) { header('Location: index.php'); exit; }

$errors        = [];
$parentErrors  = [];
$parentSuccess = '';

// ── Handle PARENT account POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'parent') {
    $namaOt   = trim($_POST['nama_orang_tua'] ?? '');
    $hubungan = trim($_POST['hubungan'] ?? '');
    $noHpOt   = trim($_POST['no_hp_ot'] ?? '');
    $email    = trim($_POST['email_ot'] ?? '');
    $password = $_POST['password_ot'] ?? '';
    $isExist  = (int) ($_POST['parent_id'] ?? 0);

    if ($namaOt   === '') $parentErrors[] = 'Nama orang tua wajib diisi.';
    if ($hubungan === '') $parentErrors[] = 'Hubungan wajib dipilih.';
    if ($email    === '') $parentErrors[] = 'Email wajib diisi.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $parentErrors[] = 'Format email tidak valid.';
    if (!$isExist && $password === '') $parentErrors[] = 'Password wajib diisi untuk akun baru.';
    if ($password !== '' && strlen($password) < 6) $parentErrors[] = 'Password minimal 6 karakter.';

    if (empty($parentErrors)) {
        if ($isExist) {
            // Update existing
            $userId = (int) ($_POST['user_id_ot'] ?? 0);
            $pdo->prepare('UPDATE orang_tua SET nama_orang_tua=?, hubungan=?, no_hp=? WHERE id=?')
                ->execute([$namaOt, $hubungan, $noHpOt ?: null, $isExist]);
            $pdo->prepare('UPDATE users SET nama=?, email=? WHERE id=?')
                ->execute([$namaOt, $email, $userId]);
            if ($password !== '') {
                $pdo->prepare('UPDATE users SET password=? WHERE id=?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
            }
            $parentSuccess = 'Akun orang tua berhasil diperbarui.';
        } else {
            // Check email unique
            $chk = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $chk->execute([$email]);
            if ($chk->fetch()) {
                $parentErrors[] = 'Email sudah digunakan akun lain.';
            } else {
                $pdo->prepare('INSERT INTO users (nama, email, password, role) VALUES (?, ?, ?, "orang_tua")')
                    ->execute([$namaOt, $email, password_hash($password, PASSWORD_DEFAULT)]);
                $userId = (int) $pdo->lastInsertId();
                $pdo->prepare('INSERT INTO orang_tua (pengguna_id, siswa_id, nama_orang_tua, no_hp, hubungan) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$userId, $id, $namaOt, $noHpOt ?: null, $hubungan]);
                $parentSuccess = 'Akun orang tua berhasil dibuat.';
            }
        }
    }
}

// ── Handle STUDENT update POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') !== 'parent') {
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
        $chk = $pdo->prepare('SELECT id FROM siswa WHERE nis = ? AND id != ?');
        $chk->execute([$nis, $id]);
        if ($chk->fetch()) $errors[] = 'NIS sudah digunakan siswa lain.';
    }

    $fotoName = $student['foto'];
    if (!empty($_FILES['foto']['tmp_name'])) {
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        $mime    = mime_content_type($_FILES['foto']['tmp_name']);
        if (!in_array($mime, $allowed, true)) {
            $errors[] = 'Foto harus berformat JPG, PNG, atau WebP.';
        } elseif ($_FILES['foto']['size'] > 2 * 1024 * 1024) {
            $errors[] = 'Ukuran foto maksimal 2MB.';
        } else {
            $ext     = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            $newName = bin2hex(random_bytes(8)) . '.' . $ext;
            $dest    = $_SERVER['DOCUMENT_ROOT'] . '/absensi/uploads/students/' . $newName;
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $dest)) {
                if ($student['foto']) @unlink($_SERVER['DOCUMENT_ROOT'] . '/absensi/uploads/students/' . $student['foto']);
                $fotoName = $newName;
            } else {
                $errors[] = 'Gagal menyimpan foto.';
            }
        }
    }

    if (empty($errors)) {
        $pdo->prepare('UPDATE siswa SET nis=?, nama_siswa=?, jenis_kelamin=?, kelas_id=?, no_hp=?, alamat=?, foto=?, status=? WHERE id=?')
            ->execute([$nis, $nama, $jk, $kelasId, $noHp, $alamat, $fotoName, $status, $id]);
        header('Location: index.php?updated=1');
        exit;
    }

    $student = array_merge($student, compact('nama', 'nis', 'jk', 'kelasId', 'noHp', 'alamat', 'status'));
    $student['jenis_kelamin'] = $jk;
    $student['kelas_id']      = $kelasId;
}

// ── Queries ──────────────────────────────────────────────────────────────────
$kelasList = $pdo->query('SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas')->fetchAll();

$parentStmt = $pdo->prepare('SELECT ot.*, u.email FROM orang_tua ot LEFT JOIN users u ON ot.pengguna_id = u.id WHERE ot.siswa_id = ?');
$parentStmt->execute([$id]);
$parentData = $parentStmt->fetch() ?: null;

$pageTitle = 'Edit Siswa';
require_once $basePath . 'includes/header.php';
?>

<div class="flex h-screen overflow-hidden bg-gray-50">

    <?php require_once $basePath . 'includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

        <?php require_once $basePath . 'includes/navbar.php'; ?>

        <main class="flex-1 p-6 overflow-y-auto space-y-6">

            <!-- Header -->
            <div class="flex items-center gap-3">
                <a href="index.php" class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-100 transition">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Edit Siswa</h2>
                    <p class="text-gray-400 text-sm">Ubah data <?= htmlspecialchars($student['nama_siswa']) ?></p>
                </div>
            </div>

            <?php if ($errors): ?>
            <div class="p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-600 space-y-1">
                <?php foreach ($errors as $e): ?>
                <p><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($e) ?></p>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Student form -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 max-w-3xl">
                <h3 class="text-sm font-bold text-gray-700 uppercase tracking-widest mb-5">Data Siswa</h3>
                <form method="POST" enctype="multipart/form-data" class="space-y-5" novalidate>
                    <input type="hidden" name="id" value="<?= $id ?>">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">Nama Siswa *</label>
                            <input type="text" name="nama_siswa" value="<?= htmlspecialchars($student['nama_siswa']) ?>" required
                                   class="w-full px-4 py-3 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">NIS *</label>
                            <input type="text" name="nis" value="<?= htmlspecialchars($student['nis']) ?>" required
                                   class="w-full px-4 py-3 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">Jenis Kelamin *</label>
                            <select name="jenis_kelamin" required
                                    class="w-full px-4 py-3 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                                <option value="L" <?= $student['jenis_kelamin'] === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                                <option value="P" <?= $student['jenis_kelamin'] === 'P' ? 'selected' : '' ?>>Perempuan</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">Kelas</label>
                            <select name="kelas_id"
                                    class="w-full px-4 py-3 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                                <option value="">— Pilih kelas —</option>
                                <?php foreach ($kelasList as $k): ?>
                                <option value="<?= $k['id'] ?>" <?= $student['kelas_id'] == $k['id'] ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">No. HP</label>
                            <input type="text" name="no_hp" value="<?= htmlspecialchars($student['no_hp'] ?? '') ?>"
                                   class="w-full px-4 py-3 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">Status</label>
                            <select name="status"
                                    class="w-full px-4 py-3 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                                <option value="aktif"    <?= $student['status'] === 'aktif'    ? 'selected' : '' ?>>Aktif</option>
                                <option value="nonaktif" <?= $student['status'] === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">Alamat</label>
                        <textarea name="alamat" rows="2"
                                  class="w-full px-4 py-3 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition resize-none"><?= htmlspecialchars($student['alamat'] ?? '') ?></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">Foto Profil</label>
                        <?php if ($student['foto']): ?>
                        <div class="flex items-center gap-3 mb-2">
                            <img src="/absensi/uploads/students/<?= htmlspecialchars($student['foto']) ?>"
                                 alt="Foto" class="w-14 h-14 rounded-xl object-cover border border-gray-200">
                            <p class="text-xs text-gray-400">Upload foto baru untuk mengganti.</p>
                        </div>
                        <?php endif; ?>
                        <input type="file" name="foto" accept="image/jpeg,image/png,image/webp"
                               class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary file:text-white hover:file:bg-primary/90 cursor-pointer">
                        <p class="text-xs text-gray-400 mt-1">JPG, PNG, atau WebP. Maks 2MB.</p>
                    </div>

                    <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                        <button type="submit"
                                class="px-6 py-2.5 text-sm font-semibold text-white bg-primary rounded-xl hover:bg-primary/90 transition">
                            <i class="bi bi-check-lg me-1"></i> Simpan Perubahan
                        </button>
                        <a href="index.php" class="px-6 py-2.5 text-sm font-medium text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition">Batal</a>
                    </div>
                </form>
            </div>

            <!-- Parent account section -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 max-w-3xl">

                <div class="flex items-center justify-between mb-5">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-purple-50 flex items-center justify-center text-purple-500 shrink-0">
                            <i class="bi bi-person-heart text-base"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-gray-700 uppercase tracking-widest">Akun Orang Tua</h3>
                            <p class="text-xs text-gray-400">Login untuk pantau kehadiran anak</p>
                        </div>
                    </div>
                    <?php if ($parentData): ?>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold bg-green-50 text-green-700 rounded-full">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Akun Aktif
                    </span>
                    <?php else: ?>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold bg-gray-100 text-gray-500 rounded-full">
                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span> Belum Ada Akun
                    </span>
                    <?php endif; ?>
                </div>

                <?php if ($parentSuccess): ?>
                <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 rounded-xl text-sm text-green-700 flex items-center gap-2">
                    <i class="bi bi-check-circle"></i> <?= htmlspecialchars($parentSuccess) ?>
                </div>
                <?php endif; ?>

                <?php if ($parentErrors): ?>
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-xs text-red-600 space-y-1">
                    <?php foreach ($parentErrors as $e): ?>
                    <p><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($e) ?></p>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <form method="POST" class="space-y-4" novalidate>
                    <input type="hidden" name="_action" value="parent">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <?php if ($parentData): ?>
                    <input type="hidden" name="parent_id" value="<?= $parentData['id'] ?>">
                    <input type="hidden" name="user_id_ot" value="<?= $parentData['pengguna_id'] ?>">
                    <?php endif; ?>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">Nama Orang Tua *</label>
                            <input type="text" name="nama_orang_tua"
                                   value="<?= htmlspecialchars($parentData['nama_orang_tua'] ?? '') ?>"
                                   placeholder="Nama lengkap orang tua" required
                                   class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">Hubungan *</label>
                            <select name="hubungan" required
                                    class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                                <option value="">Pilih hubungan</option>
                                <option value="ayah" <?= ($parentData['hubungan'] ?? '') === 'ayah' ? 'selected' : '' ?>>Ayah</option>
                                <option value="ibu"  <?= ($parentData['hubungan'] ?? '') === 'ibu'  ? 'selected' : '' ?>>Ibu</option>
                                <option value="wali" <?= ($parentData['hubungan'] ?? '') === 'wali' ? 'selected' : '' ?>>Wali</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">Email Login *</label>
                            <input type="email" name="email_ot"
                                   value="<?= htmlspecialchars($parentData['email'] ?? '') ?>"
                                   placeholder="email@contoh.com" required
                                   class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">No. HP</label>
                            <input type="text" name="no_hp_ot"
                                   value="<?= htmlspecialchars($parentData['no_hp'] ?? '') ?>"
                                   placeholder="08xx-xxxx-xxxx"
                                   class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">
                                Password <?= $parentData ? '<span class="font-normal text-gray-400 normal-case tracking-normal">(kosongkan jika tidak diganti)</span>' : '*' ?>
                            </label>
                            <input type="password" name="password_ot"
                                   placeholder="<?= $parentData ? 'Password baru (opsional)' : 'Min. 6 karakter' ?>"
                                   <?= $parentData ? '' : 'required' ?>
                                   class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                        </div>
                    </div>

                    <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                        <button type="submit"
                                class="px-6 py-2.5 text-sm font-semibold text-white bg-purple-600 rounded-xl hover:bg-purple-700 transition">
                            <i class="bi bi-person-check me-1"></i>
                            <?= $parentData ? 'Perbarui Akun Orang Tua' : 'Buat Akun Orang Tua' ?>
                        </button>
                        <?php if ($parentData): ?>
                        <div class="text-xs text-gray-400">
                            <i class="bi bi-info-circle me-1"></i>
                            Login: <span class="font-mono text-gray-600"><?= htmlspecialchars($parentData['email']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </form>

            </div>

        </main>
    </div>
</div>

<?php require_once $basePath . 'includes/footer.php'; ?>
