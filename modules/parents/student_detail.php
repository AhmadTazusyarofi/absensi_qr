<?php
require_once '../../includes/auth_check.php';
require_auth(['admin', 'guru', 'orang_tua']);

header('Content-Type: application/json');

$siswaId = (int) ($_GET['siswa_id'] ?? 0);
$bulan   = max(1, min(12, (int) ($_GET['bulan'] ?? date('n'))));
$tahun   = max(2020, (int) ($_GET['tahun'] ?? date('Y')));

if (!$siswaId) {
    echo json_encode(['success' => false, 'message' => 'ID siswa tidak valid.']);
    exit;
}

require_once '../../config/database.php';

try {
    $pdo = getDB();

    $siswa = $pdo->prepare("
        SELECT s.id, s.nama_siswa, s.nis, s.foto, s.no_hp, s.alamat, s.jenis_kelamin,
               k.nama_kelas, k.tingkat
        FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id
        WHERE s.id = ? AND s.status = 'aktif'
    ");
    $siswa->execute([$siswaId]);
    $student = $siswa->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Siswa tidak ditemukan.']);
        exit;
    }

    // Monthly stats
    $statsStmt = $pdo->prepare("
        SELECT status_kehadiran, COUNT(*) AS total
        FROM absensi
        WHERE siswa_id = ? AND MONTH(tanggal_absensi) = ? AND YEAR(tanggal_absensi) = ?
        GROUP BY status_kehadiran
    ");
    $statsStmt->execute([$siswaId, $bulan, $tahun]);
    $stats = ['hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alfa' => 0];
    foreach ($statsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $stats[$row['status_kehadiran']] = (int) $row['total'];
    }

    // Attendance records for the month
    $recStmt = $pdo->prepare("
        SELECT a.tanggal_absensi, a.waktu_scan, a.status_kehadiran, a.metode_absensi, a.keterangan,
               g.nama_guru
        FROM absensi a
        LEFT JOIN guru g ON a.guru_id = g.id
        WHERE a.siswa_id = ? AND MONTH(a.tanggal_absensi) = ? AND YEAR(a.tanggal_absensi) = ?
        ORDER BY a.tanggal_absensi ASC
    ");
    $recStmt->execute([$siswaId, $bulan, $tahun]);
    $records = $recStmt->fetchAll(PDO::FETCH_ASSOC);

    // Parent info
    $parentStmt = $pdo->prepare("
        SELECT nama_orang_tua, no_hp, hubungan FROM orang_tua WHERE siswa_id = ?
    ");
    $parentStmt->execute([$siswaId]);
    $parents = $parentStmt->fetchAll(PDO::FETCH_ASSOC);

    $total = array_sum($stats);
    $pct   = $total > 0 ? round(($stats['hadir'] / $total) * 100, 1) : 0;

    echo json_encode([
        'success' => true,
        'student' => $student,
        'stats'   => $stats,
        'total'   => $total,
        'pct'     => $pct,
        'records' => $records,
        'parents' => $parents,
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
