<?php
require_once '../../includes/auth_check.php';
require_auth(['admin', 'guru']);

header('Content-Type: application/json');

$barcode = trim($_POST['barcode'] ?? '');

if ($barcode === '') {
    echo json_encode(['success' => false, 'message' => 'Barcode tidak valid.']);
    exit;
}

require_once '../../config/database.php';

try {
    $pdo = getDB();

    // Find active student by barcode
    $stmt = $pdo->prepare('
        SELECT s.id, s.nama_siswa, s.nis, s.foto, k.nama_kelas
        FROM siswa s
        LEFT JOIN kelas k ON s.kelas_id = k.id
        WHERE s.barcode = ? AND s.status = "aktif"
    ');
    $stmt->execute([$barcode]);
    $siswa = $stmt->fetch();

    if (!$siswa) {
        try {
            $pdo->prepare('INSERT INTO log_scan (siswa_id, barcode, hasil_scan, pesan) VALUES (NULL, ?, "gagal", "Barcode tidak ditemukan")')
                ->execute([$barcode]);
        } catch (PDOException $e) {}

        echo json_encode(['success' => false, 'error' => 'not_found', 'message' => 'Siswa tidak ditemukan atau tidak aktif.']);
        exit;
    }

    $today = date('Y-m-d');
    $time  = date('H:i:s');

    // Check duplicate attendance today
    $chk = $pdo->prepare('SELECT id, waktu_scan FROM absensi WHERE siswa_id = ? AND tanggal_absensi = ?');
    $chk->execute([$siswa['id'], $today]);
    $existing = $chk->fetch();

    if ($existing) {
        echo json_encode([
            'success'          => true,
            'already_attended' => true,
            'message'          => 'Siswa sudah absen hari ini.',
            'student'          => [
                'nama'  => $siswa['nama_siswa'],
                'nis'   => $siswa['nis'],
                'kelas' => $siswa['nama_kelas'] ?? '—',
                'foto'  => $siswa['foto'],
            ],
            'attendance' => [
                'waktu'  => date('H:i', strtotime($existing['waktu_scan'])) . ' WIB',
                'status' => 'hadir',
            ],
        ]);
        exit;
    }

    // Resolve guru_id if role is guru
    $guruId = null;
    if ($_SESSION['role'] === 'guru') {
        $g = $pdo->prepare('SELECT id FROM guru WHERE user_id = ?');
        $g->execute([$_SESSION['user_id']]);
        $guruId = $g->fetchColumn() ?: null;
    }

    // Insert attendance
    $pdo->prepare('
        INSERT INTO absensi (siswa_id, guru_id, tanggal_absensi, waktu_scan, status_kehadiran, metode_absensi)
        VALUES (?, ?, ?, ?, "hadir", "barcode")
    ')->execute([$siswa['id'], $guruId, $today, $time]);

    // Log success
    try {
        $pdo->prepare('INSERT INTO log_scan (siswa_id, barcode, hasil_scan, pesan) VALUES (?, ?, "berhasil", "Absensi tercatat")')
            ->execute([$siswa['id'], $barcode]);
    } catch (PDOException $e) {}

    echo json_encode([
        'success'          => true,
        'already_attended' => false,
        'message'          => 'Kehadiran berhasil dicatat.',
        'student'          => [
            'nama'  => $siswa['nama_siswa'],
            'nis'   => $siswa['nis'],
            'kelas' => $siswa['nama_kelas'] ?? '—',
            'foto'  => $siswa['foto'],
        ],
        'attendance' => [
            'waktu'  => date('H:i', strtotime($time)) . ' WIB',
            'status' => 'hadir',
        ],
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
