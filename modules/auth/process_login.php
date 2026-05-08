<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

require_once '../../config/database.php';

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    header('Location: login.php?error=Email+dan+password+wajib+diisi');
    exit;
}

$pdo  = getDB();
$stmt = $pdo->prepare(
    'SELECT id, nama, password, role, foto_profil FROM users WHERE email = ? LIMIT 1'
);
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    header('Location: login.php?error=Email+atau+password+salah');
    exit;
}

session_regenerate_id(true);

$_SESSION['user_id'] = $user['id'];
$_SESSION['role']    = $user['role'];
$_SESSION['nama']    = $user['nama'];
$_SESSION['foto']    = $user['foto_profil'];

// Store siswa_id in session for profile page
if ($user['role'] === 'siswa') {
    $sStmt = $pdo->prepare('SELECT id FROM siswa WHERE user_id = ?');
    $sStmt->execute([$user['id']]);
    $siswaRow = $sStmt->fetch();
    if ($siswaRow) $_SESSION['siswa_id'] = (int) $siswaRow['id'];
}

$redirect = match($user['role']) {
    'orang_tua' => '/absensi/modules/parents/dashboard.php',
    'siswa'     => '/absensi/modules/students/profile.php',
    default     => '/absensi/dashboard.php',
};
header('Location: ' . $redirect);
exit;
