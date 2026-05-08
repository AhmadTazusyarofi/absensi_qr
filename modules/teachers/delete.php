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
    $chk = $pdo->prepare('SELECT COUNT(*) FROM kelas WHERE wali_kelas_id = ?');
    $chk->execute([$id]);
    if ((int) $chk->fetchColumn() > 0) {
        header('Location: index.php?delete_failed=1');
        exit;
    }

    $del = $pdo->prepare('DELETE FROM guru WHERE id = ?');
    $del->execute([$id]);
}

header('Location: index.php?deleted=1');
exit;
