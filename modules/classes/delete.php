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
    $del = $pdo->prepare('DELETE FROM kelas WHERE id = ?');
    $del->execute([$id]);
}

header('Location: index.php?deleted=1');
exit;
