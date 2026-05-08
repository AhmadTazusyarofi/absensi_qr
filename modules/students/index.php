<?php
require_once '../../includes/auth_check.php';
require_auth(['admin']);

$basePath = '../../';
require_once $basePath . 'config/database.php';

$search  = trim($_GET['q'] ?? '');
$perPage = 10;
$page    = max(1, (int) ($_GET['page'] ?? 1));

$createErrors  = [];
$editErrors    = [];
$parentErrors  = [];
$accountErrors = [];
$openModal     = '';
$activeTab     = 'data';
$editStudent   = null;
$editParent    = null;

// ── POST: Create ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'create') {
    $pdo  = getDB();
    $nama = trim($_POST['nama_siswa'] ?? '');
    $nis  = trim($_POST['nis'] ?? '');
    $jk   = $_POST['jenis_kelamin'] ?? '';
    $kelasId = ((int) ($_POST['kelas_id'] ?? 0)) ?: null;
    $noHp    = trim($_POST['no_hp'] ?? '');
    $alamat  = trim($_POST['alamat'] ?? '');
    $status  = in_array($_POST['status'] ?? '', ['aktif','nonaktif'], true) ? $_POST['status'] : 'aktif';

    if ($nama === '') $createErrors[] = 'Nama siswa wajib diisi.';
    if ($nis  === '') $createErrors[] = 'NIS wajib diisi.';
    if (!in_array($jk, ['L','P'], true)) $createErrors[] = 'Jenis kelamin wajib dipilih.';

    if ($nis !== '') {
        $chk = $pdo->prepare('SELECT id FROM siswa WHERE nis = ?');
        $chk->execute([$nis]);
        if ($chk->fetch()) $createErrors[] = 'NIS sudah digunakan.';
    }

    $fotoName = null;
    if (!empty($_FILES['foto']['tmp_name'])) {
        $mime    = mime_content_type($_FILES['foto']['tmp_name']);
        $allowed = ['image/jpeg','image/png','image/webp'];
        if (!in_array($mime, $allowed, true)) {
            $createErrors[] = 'Foto harus JPG, PNG, atau WebP.';
        } elseif ($_FILES['foto']['size'] > 2 * 1024 * 1024) {
            $createErrors[] = 'Ukuran foto maksimal 2MB.';
        } else {
            $ext      = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            $fotoName = bin2hex(random_bytes(8)) . '.' . $ext;
            $dest     = $_SERVER['DOCUMENT_ROOT'] . '/absensi/uploads/students/' . $fotoName;
            if (!move_uploaded_file($_FILES['foto']['tmp_name'], $dest)) {
                $createErrors[] = 'Gagal menyimpan foto.';
                $fotoName = null;
            }
        }
    }

    if (empty($createErrors)) {
        $barcode = strtoupper(bin2hex(random_bytes(8)));
        $pdo->prepare('INSERT INTO siswa (nis, nama_siswa, jenis_kelamin, kelas_id, no_hp, alamat, status, barcode, foto) VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([$nis, $nama, $jk, $kelasId, $noHp ?: null, $alamat ?: null, $status, $barcode, $fotoName]);
        header('Location: index.php?created=1'); exit;
    }
    $openModal = 'create';
}

// ── POST: Edit ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'edit') {
    $pdo     = getDB();
    $editId  = (int) ($_POST['id'] ?? 0);
    $nama    = trim($_POST['nama_siswa'] ?? '');
    $nis     = trim($_POST['nis'] ?? '');
    $jk      = $_POST['jenis_kelamin'] ?? '';
    $kelasId = ((int) ($_POST['kelas_id'] ?? 0)) ?: null;
    $noHp    = trim($_POST['no_hp'] ?? '');
    $alamat  = trim($_POST['alamat'] ?? '');
    $status  = in_array($_POST['status'] ?? '', ['aktif','nonaktif'], true) ? $_POST['status'] : 'aktif';
    $curFoto = $_POST['current_foto'] ?? null;

    if ($nama === '') $editErrors[] = 'Nama siswa wajib diisi.';
    if ($nis  === '') $editErrors[] = 'NIS wajib diisi.';
    if (!in_array($jk, ['L','P'], true)) $editErrors[] = 'Jenis kelamin wajib dipilih.';

    if ($nis !== '' && $editId) {
        $chk = $pdo->prepare('SELECT id FROM siswa WHERE nis = ? AND id != ?');
        $chk->execute([$nis, $editId]);
        if ($chk->fetch()) $editErrors[] = 'NIS sudah digunakan siswa lain.';
    }

    $fotoName = $curFoto;
    if (!empty($_FILES['foto']['tmp_name'])) {
        $mime    = mime_content_type($_FILES['foto']['tmp_name']);
        $allowed = ['image/jpeg','image/png','image/webp'];
        if (!in_array($mime, $allowed, true)) {
            $editErrors[] = 'Foto harus JPG, PNG, atau WebP.';
        } elseif ($_FILES['foto']['size'] > 2 * 1024 * 1024) {
            $editErrors[] = 'Ukuran foto maksimal 2MB.';
        } else {
            $ext      = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            $newName  = bin2hex(random_bytes(8)) . '.' . $ext;
            $dest     = $_SERVER['DOCUMENT_ROOT'] . '/absensi/uploads/students/' . $newName;
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $dest)) {
                if ($curFoto) @unlink($_SERVER['DOCUMENT_ROOT'] . '/absensi/uploads/students/' . $curFoto);
                $fotoName = $newName;
            } else {
                $editErrors[] = 'Gagal menyimpan foto.';
            }
        }
    }

    if (empty($editErrors) && $editId) {
        $pdo->prepare('UPDATE siswa SET nis=?,nama_siswa=?,jenis_kelamin=?,kelas_id=?,no_hp=?,alamat=?,foto=?,status=? WHERE id=?')
            ->execute([$nis, $nama, $jk, $kelasId, $noHp ?: null, $alamat ?: null, $fotoName, $status, $editId]);
        header('Location: index.php?updated=1'); exit;
    }

    $openModal = 'edit';
    $editStudent = ['id'=>$editId,'nis'=>$nis,'nama_siswa'=>$nama,'jenis_kelamin'=>$jk,
                    'kelas_id'=>$kelasId,'no_hp'=>$noHp,'alamat'=>$alamat,'status'=>$status,'foto'=>$fotoName];
}

// ── POST: Siswa account ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'siswa_account') {
    $pdo          = getDB();
    $editId       = (int) ($_POST['id'] ?? 0);
    $emailSiswa   = trim($_POST['email_siswa'] ?? '');
    $passwordSiswa= $_POST['password_siswa'] ?? '';
    $siswaUserId  = (int) ($_POST['siswa_user_id'] ?? 0);

    if ($emailSiswa === '')                              $accountErrors[] = 'Email wajib diisi.';
    if (!filter_var($emailSiswa, FILTER_VALIDATE_EMAIL)) $accountErrors[] = 'Format email tidak valid.';
    if (!$siswaUserId && $passwordSiswa === '')           $accountErrors[] = 'Password wajib diisi untuk akun baru.';
    if ($passwordSiswa !== '' && strlen($passwordSiswa) < 6) $accountErrors[] = 'Password minimal 6 karakter.';

    if (empty($accountErrors)) {
        // Fetch siswa name for the user record
        $sRow = $pdo->prepare('SELECT nama_siswa FROM siswa WHERE id = ?');
        $sRow->execute([$editId]);
        $namaSiswa = $sRow->fetchColumn() ?: '';

        if ($siswaUserId) {
            $pdo->prepare('UPDATE users SET nama=?, email=? WHERE id=?')
                ->execute([$namaSiswa, $emailSiswa, $siswaUserId]);
            if ($passwordSiswa !== '') {
                $pdo->prepare('UPDATE users SET password=? WHERE id=?')
                    ->execute([password_hash($passwordSiswa, PASSWORD_DEFAULT), $siswaUserId]);
            }
        } else {
            $chk = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $chk->execute([$emailSiswa]);
            if ($chk->fetch()) {
                $accountErrors[] = 'Email sudah digunakan akun lain.';
            } else {
                $pdo->prepare('INSERT INTO users (nama, email, password, role) VALUES (?,?,?,"siswa")')
                    ->execute([$namaSiswa, $emailSiswa, password_hash($passwordSiswa, PASSWORD_DEFAULT)]);
                $newUid = (int) $pdo->lastInsertId();
                $pdo->prepare('UPDATE siswa SET user_id=? WHERE id=?')->execute([$newUid, $editId]);
            }
        }
        if (empty($accountErrors)) {
            header('Location: index.php?account_saved=1'); exit;
        }
    }

    $openModal = 'edit';
    $activeTab = 'account';
}

// ── POST: Parent account ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'parent') {
    $pdo      = getDB();
    $editId   = (int) ($_POST['id'] ?? 0);
    $namaOt   = trim($_POST['nama_orang_tua'] ?? '');
    $hubungan = trim($_POST['hubungan'] ?? '');
    $noHpOt   = trim($_POST['no_hp_ot'] ?? '');
    $email    = trim($_POST['email_ot'] ?? '');
    $password = $_POST['password_ot'] ?? '';
    $parentId = (int) ($_POST['parent_id'] ?? 0);
    $parentUserId = (int) ($_POST['user_id_ot'] ?? 0);

    if ($namaOt   === '') $parentErrors[] = 'Nama orang tua wajib diisi.';
    if ($hubungan === '') $parentErrors[] = 'Hubungan wajib dipilih.';
    if ($email    === '') $parentErrors[] = 'Email wajib diisi.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $parentErrors[] = 'Format email tidak valid.';
    if (!$parentId && $password === '') $parentErrors[] = 'Password wajib diisi untuk akun baru.';
    if ($password !== '' && strlen($password) < 6) $parentErrors[] = 'Password minimal 6 karakter.';

    if (empty($parentErrors)) {
        if ($parentId) {
            $pdo->prepare('UPDATE orang_tua SET nama_orang_tua=?,hubungan=?,no_hp=? WHERE id=?')
                ->execute([$namaOt, $hubungan, $noHpOt ?: null, $parentId]);
            $pdo->prepare('UPDATE users SET nama=?,email=? WHERE id=?')
                ->execute([$namaOt, $email, $parentUserId]);
            if ($password !== '') {
                $pdo->prepare('UPDATE users SET password=? WHERE id=?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $parentUserId]);
            }
        } else {
            $chk = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $chk->execute([$email]);
            if ($chk->fetch()) {
                $parentErrors[] = 'Email sudah digunakan akun lain.';
            } else {
                $pdo->prepare('INSERT INTO users (nama,email,password,role) VALUES (?,?,?,"orang_tua")')
                    ->execute([$namaOt, $email, password_hash($password, PASSWORD_DEFAULT)]);
                $uid = (int) $pdo->lastInsertId();
                $pdo->prepare('INSERT INTO orang_tua (pengguna_id,siswa_id,nama_orang_tua,no_hp,hubungan) VALUES (?,?,?,?,?)')
                    ->execute([$uid, $editId, $namaOt, $noHpOt ?: null, $hubungan]);
            }
        }
        if (empty($parentErrors)) {
            header('Location: index.php?parent_saved=1'); exit;
        }
    }

    $openModal = 'edit';
    $activeTab = 'parent';
}

// ── Main queries ──────────────────────────────────────────────────────────────
$students = []; $totalRows = 0; $totalPages = 1;
$totalSiswa = 0; $aktifHariIni = 0; $persenKehadiran = 0;
$kelasList = []; $dbError = null;

try {
    $pdo = getDB();
    $kelasList = $pdo->query('SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas')->fetchAll();

    $where = ''; $params = [];
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
               s.no_hp, s.alamat, s.kelas_id,
               k.nama_kelas,
               ot.id AS parent_id, ot.pengguna_id AS parent_user_id,
               ot.nama_orang_tua AS parent_nama, ot.hubungan AS parent_hubungan,
               ot.no_hp AS parent_hp,
               u.email AS parent_email,
               s.user_id AS siswa_user_id,
               us.email AS siswa_email
        FROM siswa s
        LEFT JOIN kelas k ON s.kelas_id = k.id
        LEFT JOIN orang_tua ot ON ot.siswa_id = s.id
        LEFT JOIN users u ON ot.pengguna_id = u.id
        LEFT JOIN users us ON s.user_id = us.id
        $where
        ORDER BY s.id DESC
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($params);
    $students = $stmt->fetchAll();

    $today      = date('Y-m-d');
    $totalSiswa = (int) $pdo->query('SELECT COUNT(*) FROM siswa WHERE status = "aktif"')->fetchColumn();
    $aktifStmt  = $pdo->prepare('SELECT COUNT(DISTINCT siswa_id) FROM absensi WHERE tanggal_absensi = ? AND status_kehadiran = "hadir"');
    $aktifStmt->execute([$today]);
    $aktifHariIni    = (int) $aktifStmt->fetchColumn();
    $persenKehadiran = $totalSiswa > 0 ? round(($aktifHariIni / $totalSiswa) * 100) : 0;

    // Refetch editStudent data if needed
    if ($openModal === 'edit' && !$editStudent && isset($_POST['id'])) {
        $r = $pdo->prepare('SELECT s.*, ot.id AS parent_id, ot.pengguna_id AS parent_user_id, ot.nama_orang_tua AS parent_nama, ot.hubungan AS parent_hubungan, ot.no_hp AS parent_hp, u.email AS parent_email FROM siswa s LEFT JOIN orang_tua ot ON ot.siswa_id = s.id LEFT JOIN users u ON ot.pengguna_id = u.id WHERE s.id = ?');
        $r->execute([(int)$_POST['id']]);
        $editStudent = $r->fetch() ?: null;
    }

} catch (PDOException $e) {
    $dbError = $e->getMessage();
}

$flash = '';
if (isset($_GET['created']))       $flash = 'success:Siswa berhasil ditambahkan.';
if (isset($_GET['updated']))       $flash = 'success:Data siswa berhasil diperbarui.';
if (isset($_GET['deleted']))       $flash = 'danger:Siswa berhasil dihapus.';
if (isset($_GET['parent_saved']))  $flash = 'success:Akun orang tua berhasil disimpan.';
if (isset($_GET['account_saved'])) $flash = 'success:Akun login siswa berhasil disimpan.';

$pageTitle = 'Data Siswa';
require_once $basePath . 'includes/header.php';
?>

<div class="flex h-screen overflow-hidden bg-gray-50">

    <?php require_once $basePath . 'includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

        <?php require_once $basePath . 'includes/navbar.php'; ?>

        <main class="flex-1 p-6 space-y-6 overflow-y-auto">

            <!-- Header -->
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Data Siswa</h2>
                    <p class="text-gray-400 text-sm mt-0.5">Manajemen data profil dan kartu identitas digital siswa</p>
                </div>
                <button onclick="openModal('modal-create')"
                        class="flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-primary rounded-xl hover:bg-primary/90 transition shrink-0">
                    <i class="bi bi-person-plus"></i> Tambah Siswa
                </button>
            </div>

            <?php if ($flash): ?>
            <?php [$type, $msg] = explode(':', $flash, 2); ?>
            <div class="px-4 py-3 rounded-xl text-sm flex items-center gap-2
                        <?= $type === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-600' ?>">
                <i class="bi <?= $type === 'success' ? 'bi-check-circle' : 'bi-exclamation-circle' ?>"></i>
                <?= htmlspecialchars($msg) ?>
            </div>
            <?php endif; ?>

            <?php if ($dbError): ?>
            <div class="px-4 py-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
                <p class="font-semibold mb-1"><i class="bi bi-database-exclamation me-1"></i> Database belum siap</p>
                <p class="text-xs text-red-400 mt-1 font-mono"><?= htmlspecialchars($dbError) ?></p>
            </div>
            <?php endif; ?>

            <!-- Stat Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center text-blue-500 text-2xl shrink-0"><i class="bi bi-people-fill"></i></div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide font-medium">Total Siswa</p>
                        <p class="text-3xl font-bold text-gray-800"><?= number_format($totalSiswa, 0, ',', '.') ?></p>
                    </div>
                </div>
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-green-50 flex items-center justify-center text-green-500 text-2xl shrink-0"><i class="bi bi-check-circle-fill"></i></div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide font-medium">Aktif Hari Ini</p>
                        <p class="text-3xl font-bold text-gray-800"><?= number_format($aktifHariIni, 0, ',', '.') ?></p>
                    </div>
                </div>
                <div class="bg-primary rounded-2xl shadow-sm p-6 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-white/20 flex items-center justify-center text-white text-2xl shrink-0"><i class="bi bi-graph-up-arrow"></i></div>
                    <div>
                        <p class="text-xs text-white/80 uppercase tracking-wide font-medium">Persentase Kehadiran</p>
                        <p class="text-3xl font-bold text-white"><?= $persenKehadiran ?>%</p>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-4 flex-wrap">
                    <h3 class="font-bold text-gray-800 text-sm uppercase tracking-wide">Data Siswa</h3>
                    <form method="GET" class="flex items-center gap-2">
                        <div class="relative">
                            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama atau NIS..."
                                   class="pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary w-60 transition">
                        </div>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-lg hover:bg-primary/90 transition">Cari</button>
                        <?php if ($search): ?>
                        <a href="index.php" class="px-3 py-2 text-sm text-gray-500 hover:text-gray-700 transition">Reset</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-xs text-gray-400 uppercase tracking-wide bg-gray-50/60 border-b border-gray-100">
                                <th class="px-5 py-3 text-left font-medium">Siswa</th>
                                <th class="px-5 py-3 text-left font-medium">NIS</th>
                                <th class="px-5 py-3 text-left font-medium">Kelas</th>
                                <th class="px-5 py-3 text-left font-medium">Orang Tua</th>
                                <th class="px-5 py-3 text-left font-medium">QR</th>
                                <th class="px-5 py-3 text-left font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php if (empty($students)): ?>
                            <tr><td colspan="6" class="px-5 py-12 text-center text-gray-300">
                                <i class="bi bi-inbox text-4xl block mb-2"></i>
                                <?= $search ? 'Tidak ada siswa yang cocok.' : 'Belum ada data siswa.' ?>
                            </td></tr>
                            <?php else: ?>
                            <?php foreach ($students as $s): ?>
                            <tr class="hover:bg-gray-50/60 transition">
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <?php if ($s['foto']): ?>
                                        <img src="/absensi/uploads/students/<?= htmlspecialchars($s['foto']) ?>"
                                             class="w-10 h-10 rounded-full object-cover border border-gray-200 shrink-0" alt="">
                                        <?php else: ?>
                                        <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold text-sm shrink-0">
                                            <?= mb_strtoupper(mb_substr($s['nama_siswa'], 0, 1)) ?>
                                        </div>
                                        <?php endif; ?>
                                        <div>
                                            <p class="font-semibold text-gray-800"><?= htmlspecialchars($s['nama_siswa']) ?></p>
                                            <p class="text-xs text-gray-400"><?= htmlspecialchars($s['no_hp'] ?? '—') ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-gray-600 font-mono text-xs"><?= htmlspecialchars($s['nis']) ?></td>
                                <td class="px-5 py-4">
                                    <?php if ($s['nama_kelas']): ?>
                                    <span class="inline-block px-3 py-1 text-xs font-semibold text-primary bg-blue-50 rounded-full"><?= htmlspecialchars($s['nama_kelas']) ?></span>
                                    <?php else: ?>
                                    <span class="text-gray-300 text-xs">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-5 py-4">
                                    <?php if ($s['parent_nama']): ?>
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 font-bold text-sm shrink-0">
                                            <?= mb_strtoupper(mb_substr($s['parent_nama'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold text-gray-700 leading-tight"><?= htmlspecialchars($s['parent_nama']) ?></p>
                                            <p class="text-xs text-gray-400 capitalize mt-0.5"><?= htmlspecialchars($s['parent_hubungan'] ?? '') ?></p>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-xs text-gray-300 italic">Belum ada</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-5 py-4">
                                    <button onclick="showQR('<?= htmlspecialchars($s['nama_siswa'], ENT_QUOTES) ?>', '<?= htmlspecialchars($s['barcode'] ?? '', ENT_QUOTES) ?>')"
                                            class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-blue-50 hover:text-primary hover:border-blue-200 transition" title="Pratinjau QR">
                                        <i class="bi bi-qr-code text-base"></i>
                                    </button>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-2">
                                        <button onclick="openEditModal(<?= htmlspecialchars(json_encode([
                                            'id'             => $s['id'],
                                            'nis'            => $s['nis'],
                                            'nama_siswa'     => $s['nama_siswa'],
                                            'jenis_kelamin'  => $s['jenis_kelamin'],
                                            'kelas_id'       => (string)($s['kelas_id'] ?? ''),
                                            'no_hp'          => $s['no_hp'] ?? '',
                                            'alamat'         => $s['alamat'] ?? '',
                                            'status'         => $s['status'],
                                            'foto'           => $s['foto'] ?? '',
                                            'parent_id'      => (string)($s['parent_id'] ?? ''),
                                            'parent_user_id' => (string)($s['parent_user_id'] ?? ''),
                                            'parent_nama'    => $s['parent_nama'] ?? '',
                                            'parent_hubungan'=> $s['parent_hubungan'] ?? '',
                                            'parent_hp'      => $s['parent_hp'] ?? '',
                                            'parent_email'   => $s['parent_email'] ?? '',
                                            'siswa_user_id'  => (string)($s['siswa_user_id'] ?? ''),
                                            'siswa_email'    => $s['siswa_email'] ?? '',
                                        ]), ENT_QUOTES) ?>)"
                                                class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-blue-50 hover:text-primary hover:border-blue-200 transition" title="Edit">
                                            <i class="bi bi-pencil text-sm"></i>
                                        </button>
                                        <button onclick="confirmDelete(<?= $s['id'] ?>, '<?= htmlspecialchars($s['nama_siswa'], ENT_QUOTES) ?>')"
                                                class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-red-50 hover:text-red-500 hover:border-red-200 transition" title="Hapus">
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
                    <p class="text-sm text-gray-400">Menampilkan <?= number_format(count($students), 0, ',', '.') ?> dari <?= number_format($totalRows, 0, ',', '.') ?> siswa</p>
                    <?php if ($totalPages > 1): ?>
                    <div class="flex items-center gap-1">
                        <a href="?page=<?= max(1,$page-1) ?><?= $search ? '&q='.urlencode($search) : '' ?>"
                           class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-100 transition <?= $page<=1?'opacity-40 pointer-events-none':'' ?>">
                            <i class="bi bi-chevron-left text-xs"></i>
                        </a>
                        <?php $pS=max(1,$page-2);$pE=min($totalPages,$pS+4);$pS=max(1,$pE-4); for($p=$pS;$p<=$pE;$p++): ?>
                        <a href="?page=<?=$p?><?=$search?'&q='.urlencode($search):''?>"
                           class="w-9 h-9 flex items-center justify-center rounded-lg text-sm font-medium transition <?=$p===$page?'bg-primary text-white border border-primary':'border border-gray-200 text-gray-600 hover:bg-gray-100'?>"><?=$p?></a>
                        <?php endfor; ?>
                        <a href="?page=<?= min($totalPages,$page+1) ?><?= $search ? '&q='.urlencode($search) : '' ?>"
                           class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-100 transition <?= $page>=$totalPages?'opacity-40 pointer-events-none':'' ?>">
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
<form id="delete-form" method="POST" action="delete.php"><input type="hidden" name="id" id="delete-id"></form>

<!-- QR Modal -->
<div id="qr-modal" class="fixed inset-0 bg-black/40 z-50 hidden items-center justify-center flex">
    <div class="bg-white rounded-2xl shadow-xl p-6 w-80 text-center" onclick="event.stopPropagation()">
        <h3 class="font-bold text-gray-800 mb-1" id="qr-name"></h3>
        <p class="text-xs text-gray-400 mb-4">Scan QR untuk absensi</p>
        <div class="bg-gray-50 rounded-xl p-4 mb-3 flex items-center justify-center min-h-[216px]">
            <div id="qr-container"></div>
        </div>
        <p id="qr-code-text" class="text-xs text-gray-400 font-mono mb-4 break-all"></p>
        <button onclick="closeQR()" class="w-full py-2.5 text-sm font-semibold text-white bg-primary rounded-xl hover:bg-primary/90 transition">Tutup</button>
    </div>
</div>

<!-- ── Modal: Tambah Siswa ──────────────────────────────────────────────────── -->
<div id="modal-create" class="fixed inset-0 z-50 flex items-center justify-center hidden">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeModal('modal-create')"></div>
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-xl mx-4 max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 shrink-0">
            <div><h3 class="text-lg font-bold text-gray-800">Tambah Siswa</h3><p class="text-xs text-gray-400 mt-0.5">Daftarkan siswa baru</p></div>
            <button onclick="closeModal('modal-create')" class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 transition"><i class="bi bi-x-lg text-sm"></i></button>
        </div>
        <div class="flex-1 overflow-y-auto p-6">
            <?php if ($openModal === 'create' && $createErrors): ?>
            <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-xs text-red-600 space-y-1">
                <?php foreach ($createErrors as $e): ?><p><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($e) ?></p><?php endforeach; ?>
            </div>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data" class="space-y-4" novalidate>
                <input type="hidden" name="_action" value="create">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">Nama Siswa *</label>
                        <input type="text" name="nama_siswa" value="<?= $openModal==='create'?htmlspecialchars($_POST['nama_siswa']??''):'' ?>" required
                               class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">NIS *</label>
                        <input type="text" name="nis" value="<?= $openModal==='create'?htmlspecialchars($_POST['nis']??''):'' ?>" required
                               class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">Jenis Kelamin *</label>
                        <select name="jenis_kelamin" required class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                            <option value="">Pilih</option>
                            <option value="L" <?= ($openModal==='create'&&($_POST['jenis_kelamin']??'')==='L')?'selected':'' ?>>Laki-laki</option>
                            <option value="P" <?= ($openModal==='create'&&($_POST['jenis_kelamin']??'')==='P')?'selected':'' ?>>Perempuan</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">Kelas</label>
                        <select name="kelas_id" class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                            <option value="">— Pilih kelas —</option>
                            <?php foreach ($kelasList as $k): ?>
                            <option value="<?= $k['id'] ?>" <?= ($openModal==='create'&&($_POST['kelas_id']??'')==$k['id'])?'selected':'' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">No. HP</label>
                        <input type="text" name="no_hp" value="<?= $openModal==='create'?htmlspecialchars($_POST['no_hp']??''):'' ?>"
                               class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widests mb-1.5">Status</label>
                        <select name="status" class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widests mb-1.5">Alamat</label>
                    <textarea name="alamat" rows="2" class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition resize-none"><?= $openModal==='create'?htmlspecialchars($_POST['alamat']??''):'' ?></textarea>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widests mb-1.5">Foto Profil</label>
                    <input type="file" name="foto" accept="image/jpeg,image/png,image/webp"
                           class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary file:text-white hover:file:bg-primary/90 cursor-pointer">
                    <p class="text-xs text-gray-400 mt-1">JPG, PNG, atau WebP. Maks 2MB. (Opsional)</p>
                </div>
                <div class="flex gap-3 pt-2 border-t border-gray-100">
                    <button type="submit" class="px-5 py-2.5 text-sm font-semibold text-white bg-primary rounded-xl hover:bg-primary/90 transition">
                        <i class="bi bi-check-lg me-1"></i> Simpan Siswa
                    </button>
                    <button type="button" onclick="closeModal('modal-create')" class="px-5 py-2.5 text-sm font-medium text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Modal: Edit Siswa ───────────────────────────────────────────────────── -->
<div id="modal-edit" class="fixed inset-0 z-50 flex items-center justify-center hidden">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeModal('modal-edit')"></div>
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-xl mx-4 max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 shrink-0">
            <div><h3 class="text-lg font-bold text-gray-800" id="edit-modal-title">Edit Siswa</h3><p class="text-xs text-gray-400 mt-0.5" id="edit-modal-sub">—</p></div>
            <button onclick="closeModal('modal-edit')" class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 transition"><i class="bi bi-x-lg text-sm"></i></button>
        </div>

        <!-- Tabs -->
        <div class="flex border-b border-gray-100 px-6 shrink-0">
            <button id="tab-btn-data" onclick="switchTab('data')"
                    class="px-4 py-3 text-sm font-semibold border-b-2 border-primary text-primary transition">
                <i class="bi bi-person me-1"></i> Data Siswa
            </button>
            <button id="tab-btn-account" onclick="switchTab('account')"
                    class="px-4 py-3 text-sm font-semibold border-b-2 border-transparent text-gray-400 hover:text-gray-600 transition">
                <i class="bi bi-key me-1"></i> Akun Siswa
            </button>
            <button id="tab-btn-parent" onclick="switchTab('parent')"
                    class="px-4 py-3 text-sm font-semibold border-b-2 border-transparent text-gray-400 hover:text-gray-600 transition">
                <i class="bi bi-person-heart me-1"></i> Akun Orang Tua
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-6">

            <!-- Tab: Data Siswa -->
            <div id="tab-data">
                <?php if ($openModal === 'edit' && $activeTab === 'data' && $editErrors): ?>
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-xs text-red-600 space-y-1">
                    <?php foreach ($editErrors as $e): ?><p><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($e) ?></p><?php endforeach; ?>
                </div>
                <?php endif; ?>
                <form method="POST" enctype="multipart/form-data" class="space-y-4" novalidate>
                    <input type="hidden" name="_action" value="edit">
                    <input type="hidden" name="id" id="edit-id">
                    <input type="hidden" name="current_foto" id="edit-current-foto">

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widests mb-1.5">Nama Siswa *</label>
                            <input type="text" name="nama_siswa" id="edit-nama" required
                                   class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widests mb-1.5">NIS *</label>
                            <input type="text" name="nis" id="edit-nis" required
                                   class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widests mb-1.5">Jenis Kelamin *</label>
                            <select name="jenis_kelamin" id="edit-jk" required class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                                <option value="L">Laki-laki</option>
                                <option value="P">Perempuan</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widests mb-1.5">Kelas</label>
                            <select name="kelas_id" id="edit-kelas" class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                                <option value="">— Pilih kelas —</option>
                                <?php foreach ($kelasList as $k): ?>
                                <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widests mb-1.5">No. HP</label>
                            <input type="text" name="no_hp" id="edit-nohp"
                                   class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widests mb-1.5">Status</label>
                            <select name="status" id="edit-status" class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                                <option value="aktif">Aktif</option>
                                <option value="nonaktif">Nonaktif</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widests mb-1.5">Alamat</label>
                        <textarea name="alamat" id="edit-alamat" rows="2" class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition resize-none"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widests mb-1.5">Foto Profil</label>
                        <div id="edit-foto-preview" class="hidden flex items-center gap-3 mb-2">
                            <img id="edit-foto-img" src="" class="w-12 h-12 rounded-xl object-cover border border-gray-200" alt="">
                            <p class="text-xs text-gray-400">Upload foto baru untuk mengganti.</p>
                        </div>
                        <input type="file" name="foto" accept="image/jpeg,image/png,image/webp"
                               class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary file:text-white hover:file:bg-primary/90 cursor-pointer">
                    </div>
                    <div class="flex gap-3 pt-2 border-t border-gray-100">
                        <button type="submit" class="px-5 py-2.5 text-sm font-semibold text-white bg-primary rounded-xl hover:bg-primary/90 transition">
                            <i class="bi bi-check-lg me-1"></i> Simpan Perubahan
                        </button>
                        <button type="button" onclick="closeModal('modal-edit')" class="px-5 py-2.5 text-sm font-medium text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition">Batal</button>
                    </div>
                </form>
            </div>

            <!-- Tab: Akun Siswa -->
            <div id="tab-account" class="hidden">
                <?php if ($openModal === 'edit' && $activeTab === 'account' && $accountErrors): ?>
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-xs text-red-600 space-y-1">
                    <?php foreach ($accountErrors as $e): ?><p><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($e) ?></p><?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div id="account-status-badge" class="mb-4 hidden"></div>

                <form method="POST" class="space-y-4" novalidate>
                    <input type="hidden" name="_action" value="siswa_account">
                    <input type="hidden" name="id" id="account-siswa-id">
                    <input type="hidden" name="siswa_user_id" id="account-user-id">

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widests mb-1.5">Email Login *</label>
                        <input type="email" name="email_siswa" id="account-email"
                               class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widests mb-1.5">
                            Password <span id="account-pw-note" class="font-normal text-gray-400 normal-case tracking-normal"></span>
                        </label>
                        <div class="relative">
                            <input type="password" name="password_siswa" id="account-pw" placeholder="Min. 6 karakter"
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
                            <i class="bi bi-key me-1"></i> Buat Akun Siswa
                        </button>
                        <button type="button" onclick="closeModal('modal-edit')" class="px-5 py-2.5 text-sm font-medium text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition">Batal</button>
                    </div>
                </form>
            </div>

            <!-- Tab: Akun Orang Tua -->
            <div id="tab-parent" class="hidden">
                <?php if ($openModal === 'edit' && $activeTab === 'parent' && $parentErrors): ?>
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-xs text-red-600 space-y-1">
                    <?php foreach ($parentErrors as $e): ?><p><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($e) ?></p><?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Status badge (populated by JS) -->
                <div id="parent-status-badge" class="mb-4 hidden"></div>

                <form method="POST" class="space-y-4" novalidate>
                    <input type="hidden" name="_action" value="parent">
                    <input type="hidden" name="id" id="parent-siswa-id">
                    <input type="hidden" name="parent_id" id="parent-id">
                    <input type="hidden" name="user_id_ot" id="parent-user-id">

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widests mb-1.5">Nama Orang Tua *</label>
                            <input type="text" name="nama_orang_tua" id="parent-nama"
                                   class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widests mb-1.5">Hubungan *</label>
                            <select name="hubungan" id="parent-hubungan" class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                                <option value="">Pilih</option>
                                <option value="ayah">Ayah</option>
                                <option value="ibu">Ibu</option>
                                <option value="wali">Wali</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widests mb-1.5">Email Login *</label>
                            <input type="email" name="email_ot" id="parent-email"
                                   class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widests mb-1.5">No. HP</label>
                            <input type="text" name="no_hp_ot" id="parent-hp"
                                   class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widests mb-1.5">
                                Password <span id="parent-pw-note" class="font-normal text-gray-400 normal-case tracking-normal"></span>
                            </label>
                            <div class="relative">
                                <input type="password" name="password_ot" id="parent-pw" placeholder="Min. 6 karakter"
                                       class="w-full px-4 pr-10 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition">
                                <button type="button" tabindex="-1" onclick="togglePw('parent-pw', this)"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition">
                                    <i class="bi bi-eye text-sm"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-3 pt-2 border-t border-gray-100">
                        <button type="submit" id="parent-submit-btn" class="px-5 py-2.5 text-sm font-semibold text-white bg-purple-600 rounded-xl hover:bg-purple-700 transition">
                            <i class="bi bi-person-check me-1"></i> Buat Akun Orang Tua
                        </button>
                        <button type="button" onclick="closeModal('modal-edit')" class="px-5 py-2.5 text-sm font-medium text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition">Batal</button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
function openModal(id) { const el=document.getElementById(id); el.classList.remove('hidden'); el.style.display='flex'; document.body.style.overflow='hidden'; }
function closeModal(id) { const el=document.getElementById(id); el.classList.add('hidden'); el.style.display=''; document.body.style.overflow=''; }

function switchTab(tab) {
    ['data','account','parent'].forEach(t => {
        document.getElementById('tab-'+t).classList.toggle('hidden', t !== tab);
        const btn = document.getElementById('tab-btn-'+t);
        btn.classList.toggle('border-primary', t === tab);
        btn.classList.toggle('text-primary', t === tab);
        btn.classList.toggle('border-transparent', t !== tab);
        btn.classList.toggle('text-gray-400', t !== tab);
    });
}

function openEditModal(d) {
    // Populate title
    document.getElementById('edit-modal-title').textContent = 'Edit Siswa';
    document.getElementById('edit-modal-sub').textContent   = d.nama_siswa;

    // Student fields
    document.getElementById('edit-id').value           = d.id;
    document.getElementById('edit-current-foto').value = d.foto;
    document.getElementById('edit-nama').value         = d.nama_siswa;
    document.getElementById('edit-nis').value          = d.nis;
    document.getElementById('edit-jk').value           = d.jenis_kelamin;
    document.getElementById('edit-kelas').value        = d.kelas_id;
    document.getElementById('edit-nohp').value         = d.no_hp;
    document.getElementById('edit-alamat').value       = d.alamat;
    document.getElementById('edit-status').value       = d.status;

    // Foto preview
    const fotoPreview = document.getElementById('edit-foto-preview');
    const fotoImg     = document.getElementById('edit-foto-img');
    if (d.foto) {
        fotoImg.src = '/absensi/uploads/students/' + d.foto;
        fotoPreview.classList.remove('hidden');
        fotoPreview.classList.add('flex');
    } else {
        fotoPreview.classList.add('hidden');
    }

    // Siswa account fields
    document.getElementById('account-siswa-id').value = d.id;
    document.getElementById('account-user-id').value  = d.siswa_user_id;
    document.getElementById('account-email').value    = d.siswa_email;
    document.getElementById('account-pw').value       = '';

    const hasAccount = !!d.siswa_user_id;
    document.getElementById('account-pw-note').textContent = hasAccount ? '(kosongkan jika tidak diganti)' : '*';
    document.getElementById('account-submit-btn').innerHTML = hasAccount
        ? '<i class="bi bi-check-lg me-1"></i> Perbarui Akun Siswa'
        : '<i class="bi bi-key me-1"></i> Buat Akun Siswa';

    const acctBadge = document.getElementById('account-status-badge');
    acctBadge.innerHTML = hasAccount
        ? '<span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold bg-green-50 text-green-700 border border-green-200 rounded-full"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Akun Aktif · ' + d.siswa_email + '</span>'
        : '<span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold bg-gray-100 text-gray-500 rounded-full"><span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span> Belum Ada Akun Login</span>';
    acctBadge.classList.remove('hidden');

    // Parent fields
    document.getElementById('parent-siswa-id').value = d.id;
    document.getElementById('parent-id').value       = d.parent_id;
    document.getElementById('parent-user-id').value  = d.parent_user_id;
    document.getElementById('parent-nama').value     = d.parent_nama;
    document.getElementById('parent-hubungan').value = d.parent_hubungan;
    document.getElementById('parent-email').value    = d.parent_email;
    document.getElementById('parent-hp').value       = d.parent_hp;
    document.getElementById('parent-pw').value       = '';

    const hasParent = !!d.parent_id;
    document.getElementById('parent-pw-note').textContent     = hasParent ? '(kosongkan jika tidak diganti)' : '*';
    document.getElementById('parent-submit-btn').textContent  = hasParent ? '✓ Perbarui Akun Orang Tua' : '+ Buat Akun Orang Tua';
    document.getElementById('parent-submit-btn').className    = 'px-5 py-2.5 text-sm font-semibold text-white rounded-xl transition bg-purple-600 hover:bg-purple-700';

    const badge = document.getElementById('parent-status-badge');
    badge.innerHTML = hasParent
        ? '<span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold bg-green-50 text-green-700 border border-green-200 rounded-full"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Akun Aktif · ' + d.parent_email + '</span>'
        : '<span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold bg-gray-100 text-gray-500 rounded-full"><span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span> Belum Ada Akun Orang Tua</span>';
    badge.classList.remove('hidden');

    switchTab('data');
    openModal('modal-edit');
}

function showQR(nama, barcode) {
    document.getElementById('qr-name').textContent     = nama;
    document.getElementById('qr-code-text').textContent = barcode || '(belum ada barcode)';
    const container = document.getElementById('qr-container');
    container.innerHTML = '';
    if (barcode) {
        new QRCode(container, { text: barcode, width: 184, height: 184, colorDark: '#1a1a2e', colorLight: '#f9fafb', correctLevel: QRCode.CorrectLevel.M });
    }
    document.getElementById('qr-modal').classList.remove('hidden');
}
function closeQR() { document.getElementById('qr-modal').classList.add('hidden'); }
document.getElementById('qr-modal').addEventListener('click', closeQR);

function confirmDelete(id, nama) {
    if (!confirm(`Hapus siswa "${nama}"?\nTindakan ini tidak dapat dibatalkan.`)) return;
    document.getElementById('delete-id').value = id;
    document.getElementById('delete-form').submit();
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeModal('modal-create'); closeModal('modal-edit'); }
});

<?php if ($openModal): ?>
openModal('modal-<?= $openModal ?>');
<?php if ($openModal === 'edit' && $editStudent): ?>
openEditModal(<?= json_encode([
    'id'             => $editStudent['id'],
    'nis'            => $editStudent['nis'],
    'nama_siswa'     => $editStudent['nama_siswa'],
    'jenis_kelamin'  => $editStudent['jenis_kelamin'],
    'kelas_id'       => (string)($editStudent['kelas_id'] ?? ''),
    'no_hp'          => $editStudent['no_hp'] ?? '',
    'alamat'         => $editStudent['alamat'] ?? '',
    'status'         => $editStudent['status'],
    'foto'           => $editStudent['foto'] ?? '',
    'parent_id'      => (string)($editStudent['parent_id'] ?? ''),
    'parent_user_id' => (string)($editStudent['parent_user_id'] ?? ''),
    'parent_nama'    => $editStudent['parent_nama'] ?? '',
    'parent_hubungan'=> $editStudent['parent_hubungan'] ?? '',
    'parent_hp'      => $editStudent['parent_hp'] ?? '',
    'parent_email'   => $editStudent['parent_email'] ?? '',
]) ?>);
<?php if ($activeTab === 'parent'): ?>switchTab('parent');<?php elseif ($activeTab === 'account'): ?>switchTab('account');<?php endif; ?>
<?php endif; ?>
<?php endif; ?>
</script>

<?php require_once $basePath . 'includes/footer.php'; ?>
