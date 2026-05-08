<?php
require_once '../../includes/auth_check.php';
require_auth(['admin', 'guru', 'orang_tua']);

$basePath = '../../';
require_once $basePath . 'config/database.php';

$bulanIndo = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

$bulan       = max(1, min(12, (int) ($_GET['bulan'] ?? date('n'))));
$tahun       = max(2020, (int) ($_GET['tahun'] ?? date('Y')));
$filterKelas = (int) ($_GET['kelas_id'] ?? 0);
$search      = trim($_GET['q'] ?? '');
$perPage     = 12;
$page        = max(1, (int) ($_GET['page'] ?? 1));

$students    = [];
$totalRows   = 0;
$totalPages  = 1;
$kelasList   = [];
$avgKehadiran = 0;
$dbError     = null;

try {
    $pdo = getDB();

    $kelasList = $pdo->query('SELECT id, nama_kelas FROM kelas ORDER BY tingkat, nama_kelas')->fetchAll();

    $conditions = ["s.status = 'aktif'"];
    $params     = [];

    if ($filterKelas > 0) { $conditions[] = 's.kelas_id = ?'; $params[] = $filterKelas; }
    if ($search !== '')   { $conditions[] = '(s.nama_siswa LIKE ? OR s.nis LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }

    $where = 'WHERE ' . implode(' AND ', $conditions);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM siswa s $where");
    $countStmt->execute($params);
    $totalRows  = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($totalRows / $perPage));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * $perPage;

    // Attendance params include bulan/tahun for the LEFT JOIN
    $stmt = $pdo->prepare("
        SELECT s.id, s.nama_siswa, s.nis, s.foto, s.no_hp,
               k.nama_kelas, k.tingkat,
               SUM(CASE WHEN a.status_kehadiran = 'hadir' THEN 1 ELSE 0 END) AS hadir,
               SUM(CASE WHEN a.status_kehadiran = 'izin'  THEN 1 ELSE 0 END) AS izin,
               SUM(CASE WHEN a.status_kehadiran = 'sakit' THEN 1 ELSE 0 END) AS sakit,
               SUM(CASE WHEN a.status_kehadiran = 'alfa'  THEN 1 ELSE 0 END) AS alfa,
               COUNT(a.id) AS total_absensi
        FROM siswa s
        LEFT JOIN kelas k ON s.kelas_id = k.id
        LEFT JOIN absensi a ON a.siswa_id = s.id
            AND MONTH(a.tanggal_absensi) = $bulan AND YEAR(a.tanggal_absensi) = $tahun
        $where
        GROUP BY s.id
        ORDER BY s.nama_siswa
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($params);
    $students = $stmt->fetchAll();

    // Average attendance for the period
    $avgStmt = $pdo->prepare("
        SELECT AVG(sub.pct) FROM (
            SELECT s.id,
                CASE WHEN COUNT(a.id) > 0
                     THEN SUM(CASE WHEN a.status_kehadiran = 'hadir' THEN 1 ELSE 0 END) * 100.0 / COUNT(a.id)
                     ELSE NULL END AS pct
            FROM siswa s
            LEFT JOIN absensi a ON a.siswa_id = s.id
                AND MONTH(a.tanggal_absensi) = ? AND YEAR(a.tanggal_absensi) = ?
            WHERE s.status = 'aktif'
            GROUP BY s.id
            HAVING pct IS NOT NULL
        ) sub
    ");
    $avgStmt->execute([$bulan, $tahun]);
    $avgKehadiran = round((float) $avgStmt->fetchColumn(), 1);

} catch (PDOException $e) {
    $dbError = $e->getMessage();
}

$queryBase = http_build_query(array_filter([
    'bulan'    => $bulan != date('n') ? $bulan : null,
    'tahun'    => $tahun != date('Y') ? $tahun : null,
    'kelas_id' => $filterKelas ?: null,
    'q'        => $search,
]));
$sep = $queryBase ? '&' : '';

$pageTitle = 'Pantauan Orang Tua';
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
                    <h2 class="text-2xl font-bold text-gray-800">Pantauan Orang Tua</h2>
                    <p class="text-gray-400 text-sm mt-0.5">Rekap kehadiran siswa per individu — <?= $bulanIndo[$bulan] . ' ' . $tahun ?></p>
                </div>
                <div class="flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm text-gray-500">
                    <i class="bi bi-people-fill text-primary"></i>
                    <span><span class="font-semibold text-gray-800"><?= number_format($totalRows, 0, ',', '.') ?></span> siswa aktif</span>
                </div>
            </div>

            <?php if ($dbError): ?>
            <div class="px-4 py-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
                <p class="font-semibold"><i class="bi bi-database-exclamation me-1"></i> Database belum siap</p>
                <p class="text-xs text-red-400 mt-1 font-mono"><?= htmlspecialchars($dbError) ?></p>
            </div>
            <?php endif; ?>

            <!-- Filter -->
            <form method="GET" class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4 flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1">Bulan</label>
                    <select name="bulan" class="px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $m === $bulan ? 'selected' : '' ?>><?= $bulanIndo[$m] ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1">Tahun</label>
                    <select name="tahun" class="px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition">
                        <?php for ($y = date('Y') + 1; $y >= 2023; $y--): ?>
                        <option value="<?= $y ?>" <?= $y === $tahun ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1">Kelas</label>
                    <select name="kelas_id" class="px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition">
                        <option value="">Semua Kelas</option>
                        <?php foreach ($kelasList as $kls): ?>
                        <option value="<?= $kls['id'] ?>" <?= $filterKelas === (int)$kls['id'] ? 'selected' : '' ?>><?= htmlspecialchars($kls['nama_kelas']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1">Cari Siswa</label>
                    <div class="relative">
                        <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Nama atau NIS..."
                               class="pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary w-48 transition">
                    </div>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="px-5 py-2 text-sm font-semibold text-white bg-primary rounded-lg hover:bg-primary/90 transition">
                        <i class="bi bi-funnel me-1"></i> Filter
                    </button>
                    <?php if ($search || $filterKelas || $bulan != date('n') || $tahun != date('Y')): ?>
                    <a href="index.php" class="px-3 py-2 text-sm text-gray-500 hover:text-gray-700 transition">Reset</a>
                    <?php endif; ?>
                </div>
            </form>

            <!-- Summary bar -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center text-primary text-xl shrink-0">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide font-medium">Rata-rata Kehadiran</p>
                        <p class="text-3xl font-bold text-gray-800"><?= $avgKehadiran ?>%</p>
                        <p class="text-xs text-gray-400">seluruh siswa aktif bulan ini</p>
                    </div>
                </div>
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-green-50 flex items-center justify-center text-green-500 text-xl shrink-0">
                        <i class="bi bi-calendar3-week"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide font-medium">Periode</p>
                        <p class="text-xl font-bold text-gray-800"><?= $bulanIndo[$bulan] . ' ' . $tahun ?></p>
                        <p class="text-xs text-gray-400">data rekap kehadiran siswa</p>
                    </div>
                </div>
            </div>

            <!-- Student Cards Grid -->
            <?php if (empty($students)): ?>
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-16 text-center text-gray-300">
                <i class="bi bi-people text-5xl block mb-3"></i>
                <p class="text-sm"><?= $search || $filterKelas ? 'Tidak ada siswa yang cocok.' : 'Belum ada data siswa aktif.' ?></p>
            </div>
            <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <?php foreach ($students as $s):
                    $tot = (int) $s['total_absensi'];
                    $pct = $tot > 0 ? round(($s['hadir'] / $tot) * 100) : 0;
                    $pctColor = $pct >= 80 ? 'text-green-600' : ($pct >= 60 ? 'text-yellow-600' : 'text-red-500');
                    $ringColor = $pct >= 80 ? '#22c55e' : ($pct >= 60 ? '#eab308' : '#ef4444');
                    $dash = round($pct * 1.26); // circumference ≈ 126 for r=20
                ?>
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 hover:shadow-md hover:border-primary/20 transition-all group">

                    <!-- Top: avatar + name -->
                    <div class="flex items-center gap-3 mb-4">
                        <?php if ($s['foto']): ?>
                        <img src="/absensi/uploads/students/<?= htmlspecialchars($s['foto']) ?>"
                             class="w-12 h-12 rounded-full object-cover border-2 border-gray-100 shrink-0" alt="">
                        <?php else: ?>
                        <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold text-lg shrink-0">
                            <?= mb_strtoupper(mb_substr($s['nama_siswa'], 0, 1)) ?>
                        </div>
                        <?php endif; ?>
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-800 text-sm truncate"><?= htmlspecialchars($s['nama_siswa']) ?></p>
                            <p class="text-xs text-gray-400 font-mono"><?= htmlspecialchars($s['nis']) ?></p>
                            <?php if ($s['nama_kelas']): ?>
                            <span class="inline-block mt-0.5 px-2 py-0.5 text-xs font-semibold text-primary bg-blue-50 rounded-full">
                                <?= htmlspecialchars($s['nama_kelas']) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Ring + percentage -->
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <svg width="52" height="52" viewBox="0 0 52 52" class="shrink-0 -rotate-90">
                                <circle cx="26" cy="26" r="20" fill="none" stroke="#f3f4f6" stroke-width="5"/>
                                <circle cx="26" cy="26" r="20" fill="none"
                                        stroke="<?= $ringColor ?>" stroke-width="5"
                                        stroke-linecap="round"
                                        stroke-dasharray="<?= $dash ?> 126"
                                        stroke-dashoffset="0"/>
                            </svg>
                            <div>
                                <p class="text-2xl font-bold <?= $pctColor ?>"><?= $pct ?>%</p>
                                <p class="text-xs text-gray-400">kehadiran</p>
                            </div>
                        </div>
                        <?php if ($tot === 0): ?>
                        <span class="text-xs text-gray-300 italic">Belum ada catatan</span>
                        <?php else: ?>
                        <div class="text-right text-xs space-y-1 text-gray-500">
                            <div><span class="inline-block w-2 h-2 rounded-full bg-green-400 me-1"></span><?= $s['hadir'] ?> hadir</div>
                            <div><span class="inline-block w-2 h-2 rounded-full bg-blue-400 me-1"></span><?= $s['izin'] ?> izin</div>
                            <div><span class="inline-block w-2 h-2 rounded-full bg-yellow-400 me-1"></span><?= $s['sakit'] ?> sakit</div>
                            <div><span class="inline-block w-2 h-2 rounded-full bg-red-400 me-1"></span><?= $s['alfa'] ?> alfa</div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Detail button -->
                    <button onclick="showDetail(<?= $s['id'] ?>, '<?= htmlspecialchars($s['nama_siswa'], ENT_QUOTES) ?>')"
                            class="w-full py-2 text-xs font-semibold text-primary border border-primary/30 rounded-xl hover:bg-primary hover:text-white transition">
                        <i class="bi bi-eye me-1"></i> Lihat Detail
                    </button>

                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="flex items-center justify-between flex-wrap gap-4">
                <p class="text-sm text-gray-400">
                    Halaman <span class="font-semibold text-gray-700"><?= $page ?></span> dari <span class="font-semibold text-gray-700"><?= $totalPages ?></span>
                </p>
                <div class="flex items-center gap-1">
                    <a href="?<?= $queryBase . $sep ?>page=<?= max(1, $page - 1) ?>"
                       class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-100 transition <?= $page <= 1 ? 'opacity-40 pointer-events-none' : '' ?>">
                        <i class="bi bi-chevron-left text-xs"></i>
                    </a>
                    <?php
                    $pStart = max(1, $page - 2); $pEnd = min($totalPages, $pStart + 4); $pStart = max(1, $pEnd - 4);
                    for ($p = $pStart; $p <= $pEnd; $p++): ?>
                    <a href="?<?= $queryBase . $sep ?>page=<?= $p ?>"
                       class="w-9 h-9 flex items-center justify-center rounded-lg text-sm font-medium transition
                              <?= $p === $page ? 'bg-primary text-white border border-primary' : 'border border-gray-200 text-gray-600 hover:bg-gray-100' ?>">
                        <?= $p ?>
                    </a>
                    <?php endfor; ?>
                    <a href="?<?= $queryBase . $sep ?>page=<?= min($totalPages, $page + 1) ?>"
                       class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-100 transition <?= $page >= $totalPages ? 'opacity-40 pointer-events-none' : '' ?>">
                        <i class="bi bi-chevron-right text-xs"></i>
                    </a>
                </div>
            </div>
            <?php endif; ?>

        </main>
    </div>
</div>

<!-- Detail Modal -->
<div id="detail-modal" class="fixed inset-0 z-50 flex items-center justify-center hidden">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeDetail()"></div>
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-xl mx-4 max-h-[90vh] flex flex-col">

        <!-- Modal header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 shrink-0">
            <div>
                <h3 id="modal-title" class="font-bold text-gray-800">Detail Kehadiran</h3>
                <p class="text-xs text-gray-400 mt-0.5" id="modal-subtitle">—</p>
            </div>
            <button onclick="closeDetail()" class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 transition">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>

        <!-- Modal body (scrollable) -->
        <div id="modal-body" class="flex-1 overflow-y-auto p-6">
            <div class="flex items-center justify-center py-12 text-gray-300">
                <div class="text-center">
                    <i class="bi bi-arrow-repeat text-3xl animate-spin block mb-2"></i>
                    <p class="text-sm">Memuat data...</p>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
const BULAN = <?= $bulan ?>;
const TAHUN = <?= $tahun ?>;

const statusMap = {
    hadir: { label: 'Hadir',  cls: 'bg-green-50 text-green-700',  dot: 'bg-green-500' },
    izin:  { label: 'Izin',   cls: 'bg-blue-50 text-blue-700',    dot: 'bg-blue-500'  },
    sakit: { label: 'Sakit',  cls: 'bg-yellow-50 text-yellow-700',dot: 'bg-yellow-500'},
    alfa:  { label: 'Alfa',   cls: 'bg-red-50 text-red-600',      dot: 'bg-red-500'   },
};

const hariIndo = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
const bulanIndo = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

function showDetail(siswaId, nama) {
    document.getElementById('modal-title').textContent   = nama;
    document.getElementById('modal-subtitle').textContent = bulanIndo[BULAN] + ' ' + TAHUN;
    document.getElementById('modal-body').innerHTML = `
        <div class="flex items-center justify-center py-12 text-gray-300">
            <div class="text-center">
                <i class="bi bi-arrow-repeat text-3xl block mb-2" style="animation:spin 1s linear infinite"></i>
                <p class="text-sm">Memuat data...</p>
            </div>
        </div>`;
    document.getElementById('detail-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    fetch(`student_detail.php?siswa_id=${siswaId}&bulan=${BULAN}&tahun=${TAHUN}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) { renderError(data.message); return; }
            renderDetail(data);
        })
        .catch(() => renderError('Gagal menghubungi server.'));
}

function renderDetail(data) {
    const s   = data.student;
    const st  = data.stats;
    const pct = data.pct;
    const pctColor = pct >= 80 ? '#22c55e' : pct >= 60 ? '#eab308' : '#ef4444';
    const dash = Math.round(pct * 1.26);

    const foto = s.foto
        ? `<img src="/absensi/uploads/students/${s.foto}" class="w-16 h-16 rounded-full object-cover border-2 border-gray-100" alt="">`
        : `<div class="w-16 h-16 rounded-full bg-primary/10 flex items-center justify-center text-primary text-2xl font-bold">${s.nama_siswa.charAt(0).toUpperCase()}</div>`;

    // Records list
    let recordsHtml = '';
    if (data.records.length === 0) {
        recordsHtml = `<div class="text-center py-8 text-gray-300">
            <i class="bi bi-journal-x text-3xl block mb-2"></i>
            <p class="text-sm">Belum ada catatan absensi bulan ini</p>
        </div>`;
    } else {
        recordsHtml = data.records.map(r => {
            const cfg  = statusMap[r.status_kehadiran] || { label: r.status_kehadiran, cls: 'bg-gray-100 text-gray-600', dot: 'bg-gray-400' };
            const date = new Date(r.tanggal_absensi);
            const hari = hariIndo[date.getDay()];
            const tgl  = date.toLocaleDateString('id-ID', { day:'numeric', month:'short' });
            const waktu = r.waktu_scan ? r.waktu_scan.slice(0,5) + ' WIB' : '—';
            const metode = r.metode_absensi === 'barcode'
                ? '<span class="text-gray-400"><i class="bi bi-qr-code-scan me-0.5"></i>Scan</span>'
                : '<span class="text-gray-400"><i class="bi bi-pencil me-0.5"></i>Manual</span>';
            return `<div class="flex items-center gap-3 py-2.5 border-b border-gray-50 last:border-0">
                <div class="w-10 text-center shrink-0">
                    <p class="text-xs text-gray-400">${hari}</p>
                    <p class="font-bold text-gray-700 text-sm">${tgl}</p>
                </div>
                <div class="flex-1 flex items-center gap-2">
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-full ${cfg.cls}">
                        <span class="w-1.5 h-1.5 rounded-full ${cfg.dot}"></span>${cfg.label}
                    </span>
                    <span class="text-xs">${metode}</span>
                </div>
                <span class="text-xs font-semibold text-gray-600 shrink-0">${waktu}</span>
            </div>`;
        }).join('');
    }

    // Parents info
    let parentsHtml = '';
    if (data.parents.length > 0) {
        parentsHtml = data.parents.map(p => `
            <div class="flex items-center gap-2 text-xs text-gray-600">
                <i class="bi bi-person-heart text-purple-400"></i>
                <span class="font-medium">${htmlEsc(p.nama_orang_tua)}</span>
                <span class="text-gray-400">(${p.hubungan})</span>
                ${p.no_hp ? `· <a href="tel:${p.no_hp}" class="text-primary">${p.no_hp}</a>` : ''}
            </div>`).join('');
    }

    document.getElementById('modal-title').textContent = s.nama_siswa;
    document.getElementById('modal-body').innerHTML = `
        <!-- Student info -->
        <div class="flex items-center gap-4 mb-5">
            ${foto}
            <div>
                <p class="font-bold text-gray-800">${htmlEsc(s.nama_siswa)}</p>
                <p class="text-xs text-gray-400 font-mono">${htmlEsc(s.nis)}</p>
                ${s.nama_kelas ? `<span class="inline-block mt-1 px-2 py-0.5 text-xs font-semibold text-primary bg-blue-50 rounded-full">${htmlEsc(s.nama_kelas)}</span>` : ''}
                ${parentsHtml ? `<div class="mt-2 space-y-1">${parentsHtml}</div>` : ''}
            </div>
        </div>

        <!-- Stats row -->
        <div class="grid grid-cols-4 gap-2 mb-5">
            ${[
                ['Hadir',  st.hadir,  'bg-green-50 text-green-700'],
                ['Izin',   st.izin,   'bg-blue-50 text-blue-700'],
                ['Sakit',  st.sakit,  'bg-yellow-50 text-yellow-700'],
                ['Alfa',   st.alfa,   'bg-red-50 text-red-600'],
            ].map(([l, v, c]) => `
                <div class="rounded-xl ${c} text-center py-3">
                    <p class="text-2xl font-bold">${v}</p>
                    <p class="text-xs font-semibold uppercase tracking-wide opacity-80">${l}</p>
                </div>`).join('')}
        </div>

        <!-- Progress -->
        <div class="flex items-center gap-3 mb-5 px-4 py-3 bg-gray-50 rounded-xl">
            <svg width="48" height="48" viewBox="0 0 52 52" class="-rotate-90 shrink-0">
                <circle cx="26" cy="26" r="20" fill="none" stroke="#e5e7eb" stroke-width="6"/>
                <circle cx="26" cy="26" r="20" fill="none" stroke="${pctColor}" stroke-width="6"
                        stroke-linecap="round" stroke-dasharray="${dash} 126"/>
            </svg>
            <div>
                <p class="text-2xl font-bold" style="color:${pctColor}">${pct}%</p>
                <p class="text-xs text-gray-400">Tingkat kehadiran — ${bulanIndo[BULAN]} ${TAHUN}</p>
            </div>
        </div>

        <!-- Records -->
        <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3">Riwayat Harian</h4>
        <div>${recordsHtml}</div>
    `;
}

function renderError(msg) {
    document.getElementById('modal-body').innerHTML = `
        <div class="text-center py-12 text-gray-400">
            <i class="bi bi-exclamation-triangle text-3xl block mb-2 text-red-300"></i>
            <p class="text-sm">${msg}</p>
        </div>`;
}

function closeDetail() {
    document.getElementById('detail-modal').classList.add('hidden');
    document.body.style.overflow = '';
}

function htmlEsc(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDetail(); });
</script>

<?php require_once $basePath . 'includes/footer.php'; ?>
