<?php
require_once '../../includes/auth_check.php';
require_auth(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

require_once '../../config/database.php';

$id  = (int) ($_POST['id'] ?? 0);
$pdo = getDB();

if ($id) {
    $row = $pdo->prepare('SELECT foto FROM siswa WHERE id = ?');
    $row->execute([$id]);
    $student = $row->fetch();

    if ($student) {
        if ($student['foto']) {
            @unlink($_SERVER['DOCUMENT_ROOT'] . '/absensi/uploads/students/' . $student['foto']);
        }
        $del = $pdo->prepare('DELETE FROM siswa WHERE id = ?');
        $del->execute([$id]);
    }
}

header('Location: index.php?deleted=1');
exit;
